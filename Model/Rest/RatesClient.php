<?php
/**
 * @author mdenda
 * @date 02/06/2021
 */

namespace Paquery\Shipping\Model\Rest;


use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Paquery\Shipping\Helper\Configuration;
use Paquery\Shipping\Lib\Api;
use Psr\Log\LoggerInterface;

class RatesClient
{
    const ENV_STORE_ADDRESS_COUNTRY     = 'general/store_information/country_id';
    const ENV_STORE_ADDRESS_STATE       = 'general/store_information/region_id';
    const ENV_STORE_ADDRESS_CITY        = 'general/store_information/city';
    const ENV_STORE_ADDRESS_ADDRESS_1   = 'general/store_information/street_line1';
    const ENV_STORE_ADDRESS_ADDRESS_2   = 'general/store_information/street_line2';

    const ENDPOINT_GEOCODING            = 'geolocation/geocoding/resolve';
    const ENDPOINT_RATES                = 'rates/shipment/marketplace';

    /**
     * @var Json
     */
    protected $json;

    /**
     * @var Api
     */
    protected $api;

    protected $country;
    protected $city;
    protected $address;
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var Configuration
     */
    protected $configurationHelper;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Client constructor.
     * @param Json $json
     * @param Api $api
     * @param ScopeConfigInterface $scopeConfig
     * @param Configuration $configurationHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        Json $json,
        Api $api,
        ScopeConfigInterface $scopeConfig,
        Configuration $configurationHelper,
        LoggerInterface $logger
    )
    {
        $this->json = $json;
        $this->api = $api;
        $this->scopeConfig = $scopeConfig;
        $this->configurationHelper = $configurationHelper;
        $this->logger = $logger;

        $this->init();
    }

    protected function init()
    {
        $this->country = $this->scopeConfig->getValue(self::ENV_STORE_ADDRESS_COUNTRY);
        $this->city = $this->scopeConfig->getValue(self::ENV_STORE_ADDRESS_CITY);
        $this->address = implode(' ', [
            $this->scopeConfig->getValue(self::ENV_STORE_ADDRESS_ADDRESS_1),
            $this->scopeConfig->getValue(self::ENV_STORE_ADDRESS_ADDRESS_2)
        ]);

        //TODO Add getter for MarketPlace UID
    }

    /**
     * @param $country
     * @param $city
     * @param $address
     * @return string
     * @throws Exception
     */
    protected function getZoneUID($country, $city, $address): string
    {
        $params = [
            'address' => $address,
            'country' => $country,
            'district' => $city,
            'marketplaceUID' => $this->configurationHelper->getMarketPlaceUID()
        ];

        $response = $this->api->post(self::ENDPOINT_GEOCODING,$params);

        if (empty($response['response'] || $response['responseCode'] != 200)) {
            $this->logger->error('Request params: ' . $this->json->serialize($params));
            $this->logger->error('Error al obtener Zone ID: ' . print_r($response, true));

            return '';
        }

        $response = $response['response'];
        if ($response['responseCode'] == 200) {
            return $response['data']['zoneUID'];
        }

        return '';
    }


    /**
     * @param $country
     * @param $city
     * @param $address
     * @param $deliveryTerm
     * @param $packageType
     * @param $packageSize
     * @return array
     * @throws Exception
     */
    public function getRates($country, $city, $address, $deliveryTerm, $packageType, $packageSize): array
    {
        $originUID = $this->getZoneUID($this->country, $this->city, $this->address);
        $destinationUID = $this->getZoneUID($country, $city, $address);

        if (empty($originUID) || empty($destinationUID)) {
            return [];
        }

        $params = [
            "originZoneUID" => $originUID,
            "destinationZoneUID" => $destinationUID,
            "deliveryTerm" => $deliveryTerm,
            "packageType" => $packageType,
            "packageSize" => $packageSize,
            "marketplaceUID" => $this->configurationHelper->getMarketPlaceUID()
        ];

        $response = $this->api->post('/shipment/marketplace', $params);

        if (empty($response['response'] || $response['responseCode'] != 400)) {
            $this->logger->error('Request params: ' . $this->json->serialize($params));
            $this->logger->error('Error al obtener Rates: ' . print_r($response, true));

            return [];
        }

        $response = $response['response'];
        if ($response['responseCode'] == 200) {
            $rates = $response['data']['rate'];
            if (!is_array($rates)) {
                return [$rates];
            } else {
                return $rates;
            }
        }

        return [];
    }

}
