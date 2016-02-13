<?php

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