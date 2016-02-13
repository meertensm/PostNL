<?php 

namespace MCS;

use Exception;
use Soapclient;
use SoapFault;
use SOAPHeader;
use SoapVar;

class PostNL
{

    const TEST_WSDL_BASE = 'https://testservice.postnl.com/CIF_SB/';
    const WSDL_BASE = 'https://service.postnl.com/CIF/';
    const WSDL_BARCODE = 'BarcodeWebService/1_1/?wsdl';
    const WSDL_LABELLING = 'LabellingWebService/1_9/?wsdl';
    const TRACK_AND_TRACE_NL_BASE_URL = 'https://jouw.postnl.nl/#!/track-en-trace/';
    const TRACK_AND_TRACE_INT_BASE_URL = 'https://www.internationalparceltracking.com/Main.aspx#/track/';
    const HEADER_SECURITY_NAMESPACE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';

    protected $printer = 'GraphicFile|PDF';

    protected $wsdl_base = null;

    protected $parcels = [];
    
    protected $soapOptions = [];

    protected $orderEnvelope = [
        'Customer' => [],
        'Shipment' => [
            'Barcode' => null,
            'ProductCodeDelivery' => null,
            'Dimension' => [],
            'Contacts' => [
                'Contact' => [
                    'ContactType' => '01'
                ]
            ],
            'Dimension' => [],
            'Groups' => [
                'Group' => [
                    'GroupType' => '01'    
                ]
            ],
            'Reference' => null,
            'Addresses' => [
                'Address' => [
                    'FirstName' => null,
                    'Name' => null,
                    'CompanyName' => null,
                    'Street' => null,
                    'HouseNr' => null,
                    'Zipcode' => null,
                    'City' => null,
                    'Countrycode' => null,
                    'HouseNrExt' => null,
                    'Region' => null,
                    'AddressType' => '01'  
                ]
            ]
        ]
    ];

    public function __construct(array $credentials)
    {
        $required = [
            'sandbox',
            'customer_number',
            'customer_code',
            'customer_name',
            'username',
            'password',
            'collection_location',
            'globalpack'
        ];

        foreach($required as $param){
            if(!isset($credentials[$param])){
                throw new Exception($param . ' not set!');    
            }
        }

        $credentials['password'] = sha1($credentials['password']);
        $this->credentials = $credentials;
        $this->wsdl_base = ($credentials['sandbox'] ? self::TEST_WSDL_BASE : self::WSDL_BASE);
    }

    /**
     * Set the sender's address
     */
    public function setSender(array $array)
    {
        $this->orderEnvelope['Customer'] = [
            'CustomerCode' => $this->credentials['customer_code'],
            'CustomerNumber' => $this->credentials['customer_number'],
            'CollectionLocation' => $this->credentials['collection_location'],
            'Address' => [
                'AddressType' => '02'
            ]
        ];

        $param = [
            'FirstName',
            'Name',
            'CompanyName',
            'Street',
            'HouseNr',
            'Zipcode',
            'City',
            'Countrycode',
            'HouseNrExt',
            'StreetHouseNrExt',
            'Region',
            'Area',
            'Buildingname',
            'Department',
            'Doorcode',
            'Floor',
            'Remark'
        ];

        foreach($array as $key => $value){
            if(in_array($key, $param)){
                $this->orderEnvelope['Customer']['Address'][$key] = $value;
            }
        }
    }

    /**
     * Set the parcel's reference code
     * @param string $reference
     */
    public function setReference($reference)
    {
        $this->orderEnvelope['Shipment']['Reference'] = $reference;
    }

    /**
     * Set the receiver's address
     */
    public function setReceiver(array $array)
    {

        $param = [
            'FirstName',
            'Name',
            'CompanyName',
            'Street',
            'HouseNr',
            'Zipcode',
            'City',
            'Countrycode',
            'HouseNrExt',
            'StreetHouseNrExt',
            'Region',
            'Area',
            'Buildingname',
            'Department',
            'Doorcode',
            'Floor',
            'Remark'
        ];

        $contact = [
            'Email',
            'SMSNr',
            'TelNr'
        ];

        foreach($array as $key => $value){
            if(in_array($key, $param)){
                $this->orderEnvelope['Shipment']['Addresses']['Address'][$key] = $value;
            }else if(in_array($key, $contact)){
                $this->orderEnvelope['Shipment']['Contacts']['Contact'][$key] = $value;
            }
        }
    }

    /**
     * Submit the parcels to the postnl webservice
     */
    public function ship()
    {

        $counter = 1;
        $parcels = [];

        foreach($this->parcels as $parcel){

            $barcode = $this->barcode($this->orderEnvelope['Shipment']['Addresses']['Address']['Countrycode']);

            if (count($this->parcels) > 1){

                if (!isset($mc_barcode)){
                    $mc_barcode = $barcode;   
                }

                $this->orderEnvelope['Shipment']['Groups']['Group'] = [
                    'GroupCount' => count($this->parcels),
                    'GroupSequence' => $counter,
                    'MainBarcode' => $mc_barcode,
                    'GroupType' => '03'
                ];
                
                $counter++;
                
            }

            $this->orderEnvelope['Shipment']['Dimension'] = $parcel;
            $this->orderEnvelope['Shipment']['Barcode'] = $barcode;

            if($this->orderEnvelope['Shipment']['Addresses']['Address']['Countrycode'] == 'NL'){
                $trackingLink = self::TRACK_AND_TRACE_NL_BASE_URL 
                    . $barcode 
                    . 'NL' 
                    . urlencode($this->orderEnvelope['Shipment']['Addresses']['Address']['Zipcode']);
            }
            else{
                $trackingLink = self::TRACK_AND_TRACE_INT_BASE_URL 
                    . $barcode 
                    . '/'
                    . urlencode($this->orderEnvelope['Shipment']['Addresses']['Address']['Countrycode'])
                    . '/'
                    . urlencode($this->orderEnvelope['Shipment']['Addresses']['Address']['Zipcode']);
            }
            
            $parcels[] = array_merge($parcel, [
                'barcode' => $barcode,
                'trackingLink' => $trackingLink,
                'labelData' => $this->call_PostNL(self::WSDL_LABELLING, 'GenerateLabel', $this->orderEnvelope)->Labels->Label->Content
            ]);

        }
        $this->parcels = $parcels;
    }

    /**
     * Add a parcel
     */
    public function addParcel(array $parcel)
    {
        $parameters = [
            'Height',
            'Length',
            'Width',
            'Weight'
        ];

        $newParcel = [];
        foreach($parameters as $parameter){
            if(!isset($parcel[$parameter])){
                throw new Exception($parameter . ' not set!');    
            }
            else{
                $newParcel[$parameter] = (int)$parcel[$parameter];        
            }
        }
        $this->parcels[] = $newParcel;
    }

    /**
     * Set the productcode
     * @param integer
     */
    public function setProductCodeDelivery($code)
    {
        $this->orderEnvelope['Shipment']['ProductCodeDelivery'] = $code;
    }

    /**
     * Generate the parcel's barcode
     * @param  string $country [[Description]]
     */
    public function barcode($country)
    {
        $array = [
            'Customer' => [
                'CustomerCode' => $this->credentials['customer_code'],
                'CustomerNumber' => $this->credentials['customer_number']
            ],
            'Barcode' => [
                'Type'  => '3S',
                'Range' => $this->credentials['customer_code'],
                'Serie' => ($country == 'NL' ? '000000000-999999999' : '0000000-9999999')
            ]
        ];
        return $this->call_PostNL(self::WSDL_BARCODE, 'GenerateBarcode', $array)->Barcode;
    }

    /**
     * Set soapclient options
     */
    public function soapOptions(array $soapOptions)
    {
        $this->soapOptions = $soapOptions;    
    }
    
    /**
     * Set the printertype
     */
    public function setPrinter($printer)
    {
        $this->printer = $printer;    
    }

    /**
     * Get all parcels
     * @return array
     */
    public function getParcels()
    {
        return $this->parcels;    
    }

    /**
     * Call the postnl webservice
     */
    private function call_PostNL($endpoint, $function, $data){

        try{

            $data['Message'] = [
                'MessageID' => rand(),
                'MessageTimeStamp' => date('d-m-Y H:i:s'),
                'Printertype' => $this->printer
            ];

            $namespace = self::HEADER_SECURITY_NAMESPACE;
            $node1 = new SoapVar($this->credentials['username'], XSD_STRING, null, null, 'Username', $namespace);
            $node2 = new SoapVar($this->credentials['password'], XSD_STRING, null, null, 'Password', $namespace);
            $token = new SoapVar([$node1, $node2], SOAP_ENC_OBJECT, null, null, 'UsernameToken', $namespace);
            $security = new SoapVar([$token], SOAP_ENC_OBJECT, null, null, 'Security', $namespace);
            $header = new SOAPHeader($namespace, 'Security', $security, false);
            $client = new SoapClient($this->wsdl_base . $endpoint, $this->soapOptions);
            $client->__setSoapHeaders($header);

            return $client->$function($data);

        }
        catch(SoapFault $SoapFault){
            $msg = $SoapFault->detail->CifException->Errors->ExceptionData->ErrorMsg;
            if (!is_string($msg)){
                $msg = '';
                foreach ($SoapFault->detail->CifException->Errors->ExceptionData as $exception){
                    $msg .= $exception->ErrorMsg . PHP_EOL;   
                }
            }
            throw new Exception($msg);
        }
    }
}