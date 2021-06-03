<?php

namespace Paquery\Shipping\Model\Carrier;

use Exception;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Xml\Security;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Shipping\Model\Simplexml\ElementFactory;
use Magento\Shipping\Model\Tracking\Result\StatusFactory;
use Paquery\Shipping\Helper\CarrierData;
use Paquery\Shipping\Helper\Data;
use Paquery\Shipping\Model\Rest\RatesClient;
use Psr\Log\LoggerInterface;

/**
 * Class Paquery
 *
 * @package Paquery\Shipping\Model\Carrier
 */
class Paquery extends AbstractCarrierOnline implements CarrierInterface
{

    const CODE = 'paquery';

    /**
     * Code of the carrier
     *
     * @var string
     */
    protected $_code = self::CODE;
    /**
     * @var TimezoneInterface
     */
    protected $_timezone;
    /**
     * @var
     */
    protected $_request;
    /**
     * @var ResultFactory
     */
    protected $_rateResultFactory;
    /**
     * @var MethodFactory
     */
    protected $_rateMethodFactory;
    /**
     * @var Data
     */
    protected $paqueryHelper;
    /**
     * @var \Magento\Shipping\Model\Tracking\ResultFactory
     */
    protected $resultFactory;
    /**
     * @var \Magento\Shipping\Model\Tracking\Result\ErrorFactory
     */
    protected $errorFactory;
    /**
     * @var StatusFactory
     */
    protected $statusFactory;
    /**
     * @var CarrierData
     */
    protected $helperCarrierData;
    /**
     * @var Registry
     */
    protected $registry;
    /**
     * @var RatesClient
     */
    protected $ratesClient;

    /**
     * Paquery constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $rateErrorFactory
     * @param LoggerInterface $logger
     * @param Security $xmlSecurity
     * @param ElementFactory $xmlElFactory
     * @param ResultFactory $rateFactory
     * @param MethodFactory $rateMethodFactory
     * @param \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory
     * @param \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory
     * @param StatusFactory $trackStatusFactory
     * @param RegionFactory $regionFactory
     * @param CountryFactory $countryFactory
     * @param CurrencyFactory $currencyFactory
     * @param \Magento\Directory\Helper\Data $directoryData
     * @param CarrierData $helperCarrierData
     * @param Data $paqueryHelper
     * @param StockRegistryInterface $stockRegistry
     * @param Registry $registry
     * @param RatesClient $ratesClient
     * @param array $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        Security $xmlSecurity,
        ElementFactory $xmlElFactory,
        ResultFactory $rateFactory,
        MethodFactory $rateMethodFactory,
        \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory,
        \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory,
        StatusFactory $trackStatusFactory,
        RegionFactory $regionFactory,
        CountryFactory $countryFactory,
        CurrencyFactory $currencyFactory,
        \Magento\Directory\Helper\Data $directoryData,
        CarrierData $helperCarrierData,
        Data $paqueryHelper,
        StockRegistryInterface $stockRegistry,
        Registry $registry,
        RatesClient $ratesClient,
        array $data = []
    )
    {
        parent::__construct(
            $scopeConfig,
            $rateErrorFactory,
            $logger,
            $xmlSecurity,
            $xmlElFactory,
            $rateFactory,
            $rateMethodFactory,
            $trackFactory,
            $trackErrorFactory,
            $trackStatusFactory,
            $regionFactory,
            $countryFactory,
            $currencyFactory,
            $directoryData,
            $stockRegistry,
            $data
        );
        $this->helperCarrierData = $helperCarrierData;
        $this->paqueryHelper = $paqueryHelper;
        $this->registry = $registry;
        $this->ratesClient = $ratesClient;
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods(): array
    {
        return array(self::CODE => self::CODE);
    }

    public function isTrackingAvailable(): bool
    {
        return true;
    }

    /**
     * Processing additional validation to check is carrier applicable.
     *
     * @param DataObject $request
     * @return $this|DataObject|boolean
     */
    public function processAdditionalValidation(DataObject $request)
    {
        $errors = array();
        //Skip by item validation if there is no items in request
        if (empty($this->getAllItems($request))) {
            $errors[] = __('There is no items in this order');
        }

        $shippingCountry = $request->getDestCountryId();

        if ('AR' != $shippingCountry) { // This shipping method is only available for Argentina
            $errors[] = __('This shipping method is only available in Argentina');
        }

        if (!empty($errors)) {
            $this->debugErrors($errors);

            return false;
        }

        return $this;
    }


    /**
     * @param RateRequest $request
     * @return Result
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function collectRates(RateRequest $request): Result
    {

        $this->_request = $request;
        $result = $this->_rateFactory->create();

        // Get shipping address
        $quote = $this->helperCarrierData->getQuote();
        $shippingAddress = $quote->getShippingAddress();

        $shipping_methods = $this->getShippingMethodsAvailable($shippingAddress);
        if (count($shipping_methods) == 0) return $result;

        $products_dimensions = $this->helperCarrierData->getDimensions($this->helperCarrierData->getAllItems($this->_request->getAllItems()));
        $total_weight = $products_dimensions['total_weight'];

        foreach ($shipping_methods as $code => $data) {
            if ($data['price'] == '0') continue;

            $price = $data['price'];
            $shipping_name = $this->_scopeConfig->getValue('carriers/paquery/title').' - '.$this->_scopeConfig->getValue('carriers/paquery/'.$data['name'].'_title');

            $method = $this->_rateMethodFactory->create();
            $method_id = '|' . $code;
            $method->setCarrier(self::CODE);
            $method->setCarrierTitle('Paquery');
            $method->setMethod($method_id);
            $method->setMethodTitle($shipping_name);

            // Price by package size
            $chargePercent = 0;
            if ($total_weight <= 5) {
                $chargePercent = 0;
            } else if ($total_weight <= 22) {
                $chargePercent = 30;
            } else {
                $chargePercent = 70;
            }
            if($chargePercent != 0) $price = $price + (($price / 100) * $chargePercent);
            $method->setPrice($price);

            $result->append($method);
        }

        return $result;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function getTrackingInfo($tracking)
    {
        $result = $this->getTracking($tracking);

        if ($result instanceof \Magento\Shipping\Model\Tracking\Result) {
            $this->registry->register('current_shipping_carrier', self::CODE);
            return $result;
        } elseif (is_string($result) && !empty($result)) {
            return $result;
        }

        return false;
    }

    /**
     * Return Tracking Information
     *
     * @param $trackingNumber
     * @return mixed
     * @throws Exception
     */
    public function getTracking($trackingNumber)
    {
        $paquery = $this->paqueryHelper->getApiInstance();
        $username = $this->_scopeConfig->getValue('carriers/paquery/username');
        $response = $paquery->get('/caronte/integration/' . $username . '/package/' . $trackingNumber);

        if ($response['status'] === 200) {
            $response = $response['response']['data'];
            $result = $this->_trackFactory->create();

            if (isset($response['statusLog'])) {
                foreach (array_reverse($response['statusLog']) as $tracking_status) {
                    $tracking = $this->_trackStatusFactory
                        ->create()
                        ->setCarrier($this->_code)
                        ->setCarrierTitle($this->_scopeConfig->getValue('carriers/paquery/title'))
                        ->setTracking($trackingNumber)
                        ->addData($tracking_status);

                    $result->append($tracking);
                }
            } else {
                $tracking = $this->_trackStatusFactory
                    ->create()
                    ->setCarrier($this->_code)
                    ->setCarrierTitle($this->_scopeConfig->getValue('carriers/paquery/title'))
                    ->setTracking($trackingNumber)
                    ->addData(array(
                        'status'=> "No carrier Info"
                    ));

                $result->append($tracking);
            }

            return $result;

        } else {
            return $this->_trackErrorFactory
                ->create()
                ->setCarrier($this->getCarrierCode())
                ->setCarrierTitle($this->_scopeConfig->getValue('carriers/paquery/title'))
                ->setTracking($trackingNumber);
        }
    }

    /**
     * @param Address $shippingAddress
     * @return array|array[]|false
     * @throws Exception
     */
    public function getShippingMethodsAvailable(Address $shippingAddress)
    {
        if(empty($shippingAddress)) return false;
        $postcode = $shippingAddress->getPostcode();

        $zones_config = $this->_scopeConfig->getValue('carriers/paquery/zonemapping');
        $zones = (array)json_decode($zones_config, true);

        $ratesFromApi = $this->ratesClient->getRates(
            $shippingAddress->getCountry(),
            $shippingAddress->getCity(),
            $shippingAddress->getStreetFull(),
            1,
            1,
            1
        );

        if (!empty($ratesFromApi)) {
            return [
                3 => array('name' => 'ship12hs', 'price' => $shippingAddress[0])
            ];
        }

        $shipping_methods = array();

        foreach($zones as $zone){
            if(in_array($postcode, explode(',',$zone['cp']))){
                // Shipping Methods Available
               $shipping_methods = array(
                    '3' => array('name' => 'ship12hs', 'price' => $zone['ship12hs']),
                    '2' => array('name' => 'ship24hs', 'price' => $zone['ship24hs']),
                    '1' => array('name' => 'ship48hs', 'price' => $zone['ship48hs']),
                    '4' => array('name' => 'shipPickup', 'price' => $zone['shipPickup']),
                    '5' => array('name' => 'shipSeller', 'price' => $zone['shipSeller'])
               );
            }
        }

        return $shipping_methods;
    }

    public function getAddressForPaquery($orderAddress)
    {
        if (!$orderAddress) return false;
        $shipping_line_1 = $orderAddress->getStreetLine(1);
        $shipping_line_2 = $orderAddress->getStreetLine(2);
        $street_name = $street_number = $floor = $apartment = "";
        if (!empty($shipping_line_2)) {
            //there is something in the second line. Let's find out what
            $fl_apt_array = $this->get_floor_and_apt($shipping_line_2);
            $floor = $fl_apt_array[0];
            $apartment = $fl_apt_array[1];
        }

        //Now let's work on the first line
        preg_match('/(^\d*[\D]*)(\d+)(.*)/i', $shipping_line_1, $res);
        $line1 = $res;

        if ((isset($line1[1]) && !empty($line1[1]) && $line1[1] !== " ") && !empty($line1)) {
            //everything's fine. Go ahead
            if (empty($line1[3]) || $line1[3] === " ") {
                //the user just wrote the street name and number, as he should
                $street_name = trim($line1[1]);
                $street_number = trim($line1[2]);
                unset($line1[3]);
            } else {
                //there is something extra in the first line. We'll save it in case it's important
                $street_name = trim($line1[1]);
                $street_number = trim($line1[2]);
                $shipping_line_2 = trim($line1[3]);

                if (empty($floor) && empty($apartment)) {
                    //if we don't have either the floor or the apartment, they should be in our new $shipping_line_2
                    $fl_apt_array = $this->get_floor_and_apt($shipping_line_2);
                    $floor = $fl_apt_array[0];
                    $apartment = $fl_apt_array[1];

                } elseif (empty($apartment)) {
                    //we've already have the floor. We just need the apartment
                    $apartment = trim($line1[3]);
                } else {
                    //we've got the apartment, so let's just save the floor
                    $floor = trim($line1[3]);
                }
            }
        } else {
            //the user didn't write the street number. Maybe it's in the second line
            //given the fact that there is no street number in the fist line, we'll asume it's just the street name
            $street_name = $shipping_line_1;

            if (!empty($floor) && !empty($apartment)) {
                //we are in a pickle. It's a risky move, but we'll move everything one step up
                $street_number = $floor;
                $floor = $apartment;
                $apartment = "";
            } elseif (!empty($floor) && empty($apartment)) {
                //it seems the user wrote only the street number in the second line. Let's move it up
                $street_number = $floor;
                $floor = "";
            } elseif (empty($floor) && !empty($apartment)) {
                //I don't think there's a chance of this even happening, but let's write it to be safe
                $street_number = $apartment;
                $apartment = "";
            }
        }
        return array('street' => $street_name, 'number' => $street_number, 'floor' => $floor, 'apartment' => $apartment);
    }

    private function get_floor_and_apt($fl_apt)
    {
        //firts we'll asume the user did things right. Something like "piso 24, depto. 5h"
        preg_match('/(piso|p|p.) ?(\w+),? ?(departamento|depto|dept|dpto|dpt|dpt.º|depto.|dept.|dpto.|dpt.|apartamento|apto|apt|apto.|apt.) ?(\w+)/i', $fl_apt, $res);
        $line2 = $res;

        if (!empty($line2)) {
            //everything was written great. Now lets grab what matters
            $floor = trim($line2[2]);
            $apartment = trim($line2[4]);
        } else {
            //maybe the user wrote something like "depto. 5, piso 24". Let's try that
            preg_match('/(departamento|depto|dept|dpto|dpt|dpt.º|depto.|dept.|dpto.|dpt.|apartamento|apto|apt|apto.|apt.) ?(\w+),? ?(piso|p|p.) ?(\w+)/i', $fl_apt, $res);
            $line2 = $res;
        }

        if (!empty($line2) && empty($apartment) && empty($floor)) {
            //apparently, that was the case. Guess some people just like to make things difficult
            $floor = trim($line2[4]);
            $apartment = trim($line2[2]);
        } else {
            //something is wrong. Let's be more specific. First we'll try with only the floor
            preg_match('/^(piso|p|p.) ?(\w+)$/i', $fl_apt, $res);
            $line2 = $res;
        }

        if (!empty($line2) && empty($floor)) {
            //now we've got it! The user just wrote the floor number. Now lets grab what matters
            $floor = trim($line2[2]);
        } else {
            //still no. Now we'll try with the apartment
            preg_match('/^(departamento|depto|dept|dpto|dpt|dpt.º|depto.|dept.|dpto.|dpt.|apartamento|apto|apt|apto.|apt.) ?(\w+)$/i', $fl_apt, $res);
            $line2 = $res;
        }

        if (!empty($line2) && empty($apartment) && empty($floor)) {
            //success! The user just wrote the apartment information. No clue why, but who am I to judge
            $apartment = trim($line2[2]);
        } else {
            //ok, weird. Now we'll try a more generic approach just in case the user missplelled something
            preg_match('/(\d+),? [a-zA-Z.,!*]* ?([a-zA-Z0-9 ]+)/i', $fl_apt, $res);
            $line2 = $res;
        }

        if (!empty($line2) && empty($floor) && empty($apartment)) {
            //finally! The user just missplelled something. It happens to the best of us
            $floor = trim($line2[1]);
            $apartment = trim($line2[2]);
        } else {
            //last try! This one is in case the user wrote the floor and apartment together ("12C")
            preg_match('/(\d+)(\D*)/i', $fl_apt, $res);
            $line2 = $res;
        }

        if (!empty($line2) && empty($floor) && empty($apartment)) {
            //ok, we've got it. I was starting to panic
            $floor = trim($line2[1]);
            $apartment = trim($line2[2]);
        } elseif (empty($floor) && empty($apartment)) {
            //I give up. I can't make sense of it. We'll save it in case it's something useful
            $floor = $fl_apt;
        }
        return array($floor, $apartment);
    }

    protected function _doShipmentRequest(DataObject $request)
    {
        // Nothing to do
    }
}
