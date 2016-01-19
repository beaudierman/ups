<?php namespace Beaudierman\Ups;

class Ups {

	
	private $xml;
	protected $xml_result;
	
	/**
	 * @var access_key string
	 * The access key from your UPS account
	 **/
	private $access_key;
	
	/**
	 * @var username string
	 * The username you log in to ups.com with
	 **/
	private $username;
	
	/**
	 * @var password string
	 * The password for the above username
	 **/
	private $password;
	
	/**
	 * @var account_number string
	 * Your UPS account number(numbers only, no special characters such as dashes)
	 **/
	private $account_number;
	
	/**
	 * @var negotiated_rates boolean
	 * Negotiated rates flag - Some UPS accounts have special negotiated rates enabled
	 **/
	private $negotiated_rates;

	/**
	 * @var commercial_rates boolean
	 * If true, returns rate for commercial address. If false, returns rate for residential address
	 */
	private $commercial_rates;

	/**
	 * @var url string
	 * The location of the UPS API endpoint
	 **/
	private $url = 'https://onlinetools.ups.com/ups.app/xml/Rate';

	/**
	 * Load UPS.com credentials from the config file
	 * located at beaudierman/ups/src/config/config.php
	 *
	 * @param credentials array
	 * 
	 * @return void
	 **/
	public function loadCredentials($credentials)
	{
		$this->access_key = $credentials['access_key'];
		$this->username = $credentials['username'];
		$this->password = $credentials['password'];
		$this->account_number = $credentials['account_number'];
	}

	/**
	 * Send an XML request to the UPS API and return
	 * an array containing all available shipping
	 * options with rates
	 *
	 * @param credentials array
	 * @param options array
	 *
	 * @return array
	 **/
	public function getQuote($credentials, $options)
	{
		// Load the credentials
		$this->loadCredentials($credentials);

		// Run the options array through the default check
		$options = $this->checkDefaults($options);

		$residential_flag = ($this->commercial_rates) ? '' : '<ResidentialAddressIndicator/>';

		$negotiated_flag = ($this->negotiated_rates) ? '<RateInformation><NegotiatedRatesIndicator/></RateInformation>' : '';

		$this->xml = '<?xml version="1.0"?>
		<AccessRequest xml:lang="en-US">
			<AccessLicenseNumber>' . $this->access_key . '</AccessLicenseNumber>
			<UserId>' . $this->username . '</UserId>
			<Password>' . $this->password . '</Password>
		</AccessRequest>
		<?xml version="1.0"?>
		<RatingServiceSelectionRequest xml:lang="en-US">
			<Request>
				<TransactionReference>
					<CustomerContext>Rate Request</CustomerContext>
					<XpciVersion>1.0001</XpciVersion>
				</TransactionReference>
				<RequestAction>Rate</RequestAction>
				<RequestOption>' . $options['request_option'] . '</RequestOption>
			</Request>
			<PickupType>
				<Code>01</Code>
			</PickupType>
			<Shipment>
				<Shipper>
					<ShipperNumber>' . $this->account_number . '</ShipperNumber>
					<Address>
						<PostalCode>' . $options['from_zip'] . '</PostalCode>
						<StateProvinceCode>' . $options['from_state'] . '</StateProvinceCode>
						<CountryCode>' . $options['from_country'] . '</CountryCode>
					</Address>
				</Shipper>
				<ShipTo>
					<Address>
						<PostalCode>' . $options['to_zip'] . '</PostalCode>
						<StateProvinceCode>' . $options['to_state'] . '</StateProvinceCode>
						<CountryCode>' . $options['to_country'] . '</CountryCode>
						' . $residential_flag .'
					</Address>
				</ShipTo>
				<Service>
					<Code>' . $options['service_type'] . '</Code>
					<Description>Package</Description>
				</Service>
				<ShipmentServiceOptions/>
				' . $this->buildPackages($options['packages'], $options['weight'], $options['measurement']) . $negotiated_flag . '
			</Shipment>
		</RatingServiceSelectionRequest>';

		return $this->send();
	}

	/**
	 * Build an XML block for the packages
	 * to be included in the shipping rate
	 *
	 * @param number integer
	 * @param weight float
	 * @param measurement string
	 *
	 * @return string
	 **/
	private function buildPackages($number, $weight, $measurement = 'LBS')
	{
		$packages = array();
		if($number > 1)
		{
			$individual_weight = $weight / $number;
			for($i = 0; $i < $number; $i++)
			{
				$packages[] = '<Package>
					<PackagingType>
						<Code>02</Code>
					</PackagingType>
					<PackageWeight>
						<UnitOfMeasurement>
							<Code>' . $measurement . '</Code>
						</UnitOfMeasurement>
						<Weight>' . $individual_weight . '</Weight>
					</PackageWeight>
				</Package>';
			}
		}
		else
		{
			$packages[] = '<Package>
				<PackagingType>
					<Code>02</Code>
				</PackagingType>
				<PackageWeight>
					<UnitOfMeasurement>
						<Code>' . $measurement . '</Code>
					</UnitOfMeasurement>
					<Weight>' . $weight . '</Weight>
				</PackageWeight>
			</Package>';
		}

		return implode('', $packages);
	}

	/**
	 * Send the API request to the UPS webservice
	 * and return the results if there was no error.
	 * If there was an error, return false.
	 *
	 * @param void
	 *
	 * @return array
	 * @return boolean
	 **/
	private function send()
	{
		$ch = curl_init($this->url);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $this->xml);
		$result = curl_exec($ch);
		$this->xml = strstr($result, '<?');

		$this->xml_result = new \SimpleXMLElement($this->xml);

		return $this->parseResult();
	}

	/**
	 * Parse the returned XML object from
	 * the UPS webservice.
	 *
	 * @param void
	 *
	 * @return array
	 **/
	private function parseResult()
	{
		if($this->xml_result->Response->ResponseStatusCode != '1')
		{
			return array(
				'Error' => array(
					'ErrorSeverity' => "{$this->xml_result->Response->Error->ErrorSeverity}",
					'ErrorCode' => "{$this->xml_result->Response->Error->ErrorCode}",
					'ErrorDescription' => "{$this->xml_result->Response->Error->ErrorDescription}"
				)
			);
			return $this->xml_result;
		}

		$simplified = array();
		$shipping_choices = array();

		foreach($this->xml_result->RatedShipment as $service)
		{
			$simplified[] = '{' . $service->TotalCharges->MonetaryValue . '}';
		}

		foreach($simplified as $key => $value)
		{
			$service = $this->xml_result->RatedShipment[$key]->children();

			if($this->negotiated_rates && $service->NegotiatedRates->NetSummaryCharges->GrandTotal->MonetaryValue)
			{
				$rate = number_format((double)($service->NegotiatedRates->NetSummaryCharges->GrandTotal->MonetaryValue), 2);
			}
			else
			{
				$rate = number_format((double)($service->TransportationCharges->MonetaryValue), 2);
			}

			$shipping_choices["{$service->Service->Code}"] = array(
				'service' => $this->shipperCodes("{$service->Service->Code}"),
				'rate' => "{$rate}"
			);
		}

		return $shipping_choices;
	}

	/**
	 * Return the human-readable version of
	 * a UPS service code.
	 *
	 * @param service_code string
	 *
	 * @return string
	 **/
	private function shipperCodes($service_code)
	{
		switch($service_code)
		{
			case '01':
				return 'UPS Next Day Air';
				break;
			case '02':
				return 'UPS 2nd Day Air';
				break;
			case '03':
				return 'UPS Ground';
				break;
			case '07':
				return 'UPS Worldwide Express';
				break;
			case '08':
				return 'UPS Worldwide Expedited';
				break;
			case '11':
				return 'UPS Standard';
				break;
			case '12':
				return 'UPS 3 Day Select';
				break;
			case '13':
				return 'Next Day Air Saver';
				break;
			case '14':
				return 'UPS Next Day Air Early AM';
				break;
			case '54':
				return 'UPS Worldwide Express Plus';
				break;
			case '59':
				return 'UPS Second Day Air AM';
				break;
			case '65':
				return 'UPS Saver';
				break;
			default:
				return false;
				break;
		}
	}

	/**
	 * Make sure the options array contains all
	 * of the required fields.  If not, populate
	 * them with the default values.
	 *
	 * @param options array
	 *
	 * @return array
	 **/
	private function checkDefaults($options)
	{
		if(!isset($options['request_option']))
		{
			$options['request_option'] = 'Shop';
		}
		if(!isset($options['from_country']))
		{
			$options['from_country'] = 'US';
		}
		if(!isset($options['to_country']))
		{
			$options['to_country'] = 'US';
		}
		if(!isset($options['service_type']))
		{
			$options['service_type'] = '03';
		}
		if(!isset($options['from_state']))
		{
			$options['from_state'] = '';
		}
		if(!isset($options['to_state']))
		{
			$options['to_state'] = '';
		}

		$this->commercial_rates = (isset($options['commercial']) && $options['commercial']) ? true : false;

		$this->negotiated_rates = (isset($options['negotiated_rates']) && $options['negotiated_rates']) ? true : false;
			
		return $options;
	}
}