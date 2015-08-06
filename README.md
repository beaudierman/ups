UPS
===

This package is used to send shipment rate quote requests to the UPS webservice.

Install using Composer, edit your ```composer.json``` file to include:
```
"require": {
	"beaudierman/ups": "1.*"
}
```
Update composer from the command line:
```
composer update
```
Add a new Service Provider to the ```providers``` array in your ```app/config/app.php``` file:
```
'Beaudierman\Ups\UpsServiceProvider'
```
Add a class alias in the same configuration file to the ```aliases``` array:
```
'Ups'             => 'Beaudierman\Ups\Facades\Ups'
```
Example:
```
$return = Ups::getQuote(
    array(
        'access_key' => 'your key',
        'username' => 'your username',
        'password' => 'your password',
        'account_number' => 'your account number',
    ),
	array(
		'from_zip' => '37902',
        'from_state' => 'TN', // Optional, may yield a more accurate quote
        'from_country' => 'US', // Optional, defaults to US
		'to_zip' => '90210',
        'to_state' => 'CA', // Optional, may yield a more accurate quote
        'to_country' => 'US', // Optional, defaults to US
		'packages' => 1,
		'weight' => 50,
        'measurement' => 'LBS', // Currently the UPS API will only allow LBS and KG, default is LBS
        'negotiated_rates' => true // Optional, set true to return negotiated rates from UPS
	)
);
```
Returns:
```
Array
(
    [03] => Array
        (
            [service] => UPS Ground
            [rate] => 52.32
        )

    [12] => Array
        (
            [service] => UPS 3 Day Select
            [rate] => 145.09
        )

    [02] => Array
        (
            [service] => UPS 2nd Day Air
            [rate] => 235.40
        )

    [01] => Array
        (
            [service] => UPS Next Day Air
            [rate] => 301.46
        )

)
```
