<?php namespace Beaudierman\Ups;

class Ups
{
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

    /* constants for UPS service codes */
    const SC_UPS_STANDARD = '11';
    const SC_UPS_WORLDWIDE_EXPRESS = '07';
    const SC_UPS_WORLDWIDE_EXPEDITED = '08';
    const SC_UPS_WORLDWIDE_EXPRESS_PLUS = '54';
    const SC_UPS_WORLDWIDE_SAVER = '65';
    const SC_UPS_2ND_DAY_AIR = '02';
    const SC_UPS_2ND_DAY_AIR_AM = '59';
    const SC_UPS_3_DAY_SELECT = '12';
    const SC_UPS_GROUND = '03';
    const SC_UPS_NEXT_DAY_AIR = '01';
    const SC_UPS_NEXT_DAY_AIR_EARLY = '14';
    const SC_UPS_NEXT_DAY_AIR_SAVER = '13';

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
				<Code>03</Code>
			</PickupType>
      <CustomerClassification>
    	 <Code>00</Code>
     </CustomerClassification>
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
					<Code>' . $options['service_code'] . '</Code>
					<Description>Package</Description>
				</Service>
				<ShipmentServiceOptions/>
				' . $this->buildPackages($options['packages'], $options['weight'], $options['measurement'], $options['service_code'])  . $negotiated_flag . '
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
    private function buildPackages($number, $weight, $measurement = 'LBS', $service_code)
    {
        $packages = array();
        if ($number > 1) {
            $individual_weight = $weight / $number;
            for ($i = 0; $i < $number; $i++) {
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
        } else {
          switch($service_code) {
            case self::SC_UPS_WORLDWIDE_EXPEDITED: //DeliveryConfirmation not supported; hence, there won't be any ServiceOptionsCharges
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
             break;

            default: //SC_UPS_GROUND
                $packages[] = '<Package>
            <PackagingType>
              <Code>02</Code>
            </PackagingType>
            <PackageServiceOptions>
              <DeliveryConfirmation>
                <DCISType>2</DCISType>
              </DeliveryConfirmation>
            </PackageServiceOptions>
            <PackageWeight>
              <UnitOfMeasurement>
                <Code>' . $measurement . '</Code>
              </UnitOfMeasurement>
              <Weight>' . $weight . '</Weight>
            </PackageWeight>
           </Package>';
          }
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
        try {
            $ch = curl_init($this->url);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->xml);
            $result = curl_exec($ch);

            if (false === $result) {
                throw new \Exception(curl_error($ch), curl_errno($ch));
            }

            $this->xml = strstr($result, '<?');

            $this->xml_result = new \SimpleXMLElement($this->xml);
            
            return $this->parseResult();
        } catch (\Exception $e) {
            trigger_error(sprintf(
                            'Curl failed with error #%d: %s',
                            $e->getCode(), $e->getMessage()),
                            E_USER_ERROR);
        }
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
        if ($this->xml_result->Response->ResponseStatusCode != '1') {
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

        foreach ($this->xml_result->RatedShipment as $service) {
            $simplified[] = '{' . $service->TotalCharges->MonetaryValue . '}';
        }

        foreach ($simplified as $key => $value) {
            $service = $this->xml_result->RatedShipment[$key]->children();

            if ($this->negotiated_rates &&
            $service->NegotiatedRates->NetSummaryCharges &&
            $service->NegotiatedRates->NetSummaryCharges->GrandTotal->MonetaryValue) {
                $rate = number_format((double)($service->NegotiatedRates->NetSummaryCharges->GrandTotal->MonetaryValue), 2);
            } else {
                //Get TotalCharges to retrieve both TransportationCharges & ServiceOptionsCharges(delivery confirmation signature)
                $rate = number_format((double)($service->TotalCharges->MonetaryValue), 2);
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
        switch ($service_code) {
            /* service codes: Shipments originating in United States */
            case self::SC_UPS_STANDARD:
                return 'UPS Standard';
                break;
            case self::SC_UPS_WORLDWIDE_EXPRESS:
                    return 'UPS Worldwide Express';
                    break;
            case self::SC_UPS_WORLDWIDE_EXPEDITED:
                return 'UPS Worldwide Expedited';
                break;
            case self::SC_UPS_WORLDWIDE_EXPRESS_PLUS:
                return 'UPS Worldwide Express Plus';
                break;
            case self::SC_UPS_WORLDWIDE_SAVER:
                return 'UPS Worldwide Saver';
                break;

            /* service codes: United States domestic shipments */
            case self::SC_UPS_2ND_DAY_AIR:
                return 'UPS 2nd Day Air';
                break;
            case self::SC_UPS_2ND_DAY_AIR_AM:
                return 'UPS 2nd Day Air A.M.';
                break;
            case self::SC_UPS_3_DAY_SELECT:
                return 'UPS 3 Day Select';
                break;
            case self::SC_UPS_GROUND:
                return 'UPS Ground';
                break;
            case self::SC_UPS_NEXT_DAY_AIR:
                return 'UPS Next Day Air';
                break;
            case self::SC_UPS_NEXT_DAY_AIR_EARLY:
                return 'UPS Next Day Air Early';
                break;
            case self::SC_UPS_NEXT_DAY_AIR_SAVER:
                return 'UPS Next Day Air Saver';
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
        if (!isset($options['request_option'])) {
            //only valid request option for UPS Ground Freight Pricing requests
            $options['request_option'] = 'Rate';
        }
        if (!isset($options['from_country'])) {
            $options['from_country'] = 'US';
        }
        if (!isset($options['to_country'])) {
            $options['to_country'] = 'US';
        }
        if (!isset($options['service_type'])) {
            $options['service_type'] = '03';
        }
        if (!isset($options['from_state'])) {
            $options['from_state'] = '';
        }
        if (!isset($options['to_state'])) {
            $options['to_state'] = '';
        }

        $this->commercial_rates = (isset($options['commercial']) && $options['commercial']) ? true : false;

        $this->negotiated_rates = (isset($options['negotiated_rates']) && $options['negotiated_rates']) ? true : false;

        return $options;
    }
}
