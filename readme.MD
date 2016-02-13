# PostNL Webservice
[![Latest Stable Version](https://poser.pugx.org/mcs/postnl/v/stable)](https://packagist.org/packages/mcs/postnl) [![Total Downloads](https://poser.pugx.org/mcs/postnl/downloads)](https://packagist.org/packages/mcs/postnl) [![Latest Unstable Version](https://poser.pugx.org/mcs/postnl/v/unstable)](https://packagist.org/packages/mcs/postnl) [![License](https://poser.pugx.org/mcs/postnl/license)](https://packagist.org/packages/mcs/postnl)

Installation:
```bash
$ composer require mcs/postnl
```
Submit a shipment to the postnl webservice and retrieve it's label and tracking information

```php
require_once 'vendor/autoload.php';

use MCS\PostNL;

try{

    $postnl = new PostNL([
        'sandbox' => true,
        'customer_number' => '',
        'customer_code' => '',
        'customer_name' => '',
        'username' => '',
        'password' => '',
        'collection_location' => '',
        'globalpack' => ''
    ]);

    // For development / debugging
    $postnl->soapOptions([
        'trace' => true
    ]);

    $postnl->setSender([
        'FirstName' => '',
        'Name' => '',
        'CompanyName' => '',
        'Street' => '',
        'HouseNr' => '',
        'Zipcode' => '',
        'City' => '',
        'Countrycode' => '',
    ]);

    $postnl->setReceiver([
        'FirstName' => '',
        'Name' => '',
        'CompanyName' => '',
        'Street' => '',
        'HouseNr' => '',
        'Zipcode' => '',
        'City' => '',
        'Countrycode' => '',
        'Email' => '',
        'SMSNr' => ''
    ]);

    // Add as many parcels as you want
    $postnl->addParcel([
        'Height' => 100, // Centimeter
        'Length' => 100, // Centimeter
        'Width' => 100, // Centimeter
        'Weight' => 1000, // Gram
    ]);
    
    $postnl->addParcel([
        'Height' => 100, // Centimeter
        'Length' => 100, // Centimeter
        'Width' => 100, // Centimeter
        'Weight' => 1000, // Gram
    ]);

    $postnl->setReference('100003454');

    $postnl->setProductCodeDelivery(3089);

    $postnl->ship();

    foreach($postnl->getParcels() as $parcel){
        file_put_contents($parcel['barcode'] . '.pdf', $parcel['labelData']);    
        echo $parcel['trackingLink'] . PHP_EOL;
    }

}
catch(Exception $e){
    dump($e->getMessage());
}
```

