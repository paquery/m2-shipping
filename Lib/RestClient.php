<?php
namespace Paquery\Shipping\Lib;

use Paquery\Shipping\Helper\Configuration;
use Paquery\Shipping\Helper\Data;
use Exception;
use InvalidArgumentException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * cURL RestClient
 */


class RestClient
{
    const CARRIERS_PAQUERY_MODE = 'carriers/paquery/mode';

    const CARRIERS_PAQUERY_URL = 'carriers/paquery/api_url';
    const CARRIERS_PAQUERY_USERNAME = 'carriers/paquery/username';
    const CARRIERS_PAQUERY_PASSWORD = 'carriers/paquery/password';

    const CARRIERS_PAQUERY_URL_TEST = 'carriers/paquery/api_url_test';
    const CARRIERS_PAQUERY_USERNAME_TEST = 'carriers/paquery/username_test';
    const CARRIERS_PAQUERY_PASSWORD_TEST = 'carriers/paquery/password_test';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var Json
     */
    protected $json;
    /**
     * @var Data
     */
    protected $helper;

    /**
     * RestClient constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param Json $json
     * @param Data $helper
     * @param LoggerInterface $logger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Json $json,
        Configuration $helper,
        LoggerInterface $logger
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->json = $json;
        $this->helper = $helper;
    }

    /**
     *API URL
     */
//    const API_BASE_URL = 'https://az-api.paquery.com/caronte/';

    /**
     * @param       $uri
     * @param       $method
     * @param       $content_type
     * @param array $extra_params
     *
     * @return resource
     * @throws Exception
     */
    protected function get_connect($uri, $method, $content_type, $extra_params = array())
    {
        if (!extension_loaded("curl")) {
            throw new Exception("cURL extension not found. You need to enable cURL in your php.ini or another configuration you have.");
        }

        list($url, $username, $password) = $this->helper->getConnectionData();

        if (empty($url) || empty($username) || empty($password)) {
            throw new Exception("Please configure URL, Username and Password");
        }

        $connect = curl_init($url . $uri);

        curl_setopt($connect, CURLOPT_USERAGENT, "Paquery Shipping Magento 2");
        curl_setopt($connect, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($connect, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($connect, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($connect, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($connect, CURLOPT_SSL_VERIFYPEER, 0);

        $header_opt = array("Accept: application/json", "Content-Type: " . $content_type);
        $auth_header = 'Authorization: Basic ';

        $auth_header .= base64_encode($username . ':' . $password);
        $header_opt[] = $auth_header;


        if (count($extra_params) > 0) {
            $header_opt = array_merge($header_opt, $extra_params);
        }

        curl_setopt($connect, CURLOPT_HTTPHEADER, $header_opt);

        return $connect;
    }

    /**
     * @param $connect
     * @param $data
     * @param $content_type
     *
     * @throws Exception
     */
    protected function set_data(&$connect, $data, $content_type)
    {
        if ($content_type === "application/json") {
            try {
                if (gettype($data) === "string") {
                    $data = $this->json->unserialize($data);
                } else {
                    $data = $this->json->serialize($data);
                }
            } catch (InvalidArgumentException $e) {
                throw new Exception("JSON Error -  Data: {$data}");
            }
        }

        curl_setopt($connect, CURLOPT_POSTFIELDS, $data);
    }

    /**
     * @param $method
     * @param $uri
     * @param $data
     * @param $content_type
     * @param $extra_params
     *
     * @return array
     * @throws Exception
     */
    protected function exec($method, $uri, $data, $content_type, $extra_params): array
    {

        $connect = $this->get_connect($uri, $method, $content_type, $extra_params);
        if ($data) {
            $this->set_data($connect, $data, $content_type);
        }

        $api_result = curl_exec($connect);
        $api_http_code = curl_getinfo($connect, CURLINFO_HTTP_CODE);

        if ($api_result === false) {
            throw new Exception(curl_error($connect));
        }

        $response = array(
            "status" => $api_http_code,
            "response" => $this->json->unserialize($api_result)
        );

        $this->logger->info(print_r(curl_getinfo($connect, CURLINFO_EFFECTIVE_URL), true));
        $this->logger->info(print_r($data, true));
        $this->logger->info(print_r($response, true));

        curl_close($connect);

        return $response;
    }

    /**
     * @param        $uri
     * @param string $content_type
     * @param array  $extra_params
     *
     * @return array
     * @throws Exception
     */
    public function get($uri, $content_type = "application/json", $extra_params = array()): array
    {
        return $this->exec("GET", $uri, null, $content_type, $extra_params);
    }

    /**
     * @param        $uri
     * @param        $data
     * @param string $content_type
     * @param array  $extra_params
     *
     * @return array
     * @throws Exception
     */
    public function post($uri, $data, $content_type = "application/json", $extra_params = array()): array
    {
        return $this->exec("POST", $uri, $data, $content_type, $extra_params);
    }

    /**
     * @param        $uri
     * @param        $data
     * @param string $content_type
     * @param array  $extra_params
     *
     * @return array
     * @throws Exception
     */
    public function put($uri, $data, $content_type = "application/json", $extra_params = array()): array
    {
        return $this->exec("PUT", $uri, $data, $content_type, $extra_params);
    }

    /**
     * @param        $uri
     * @param string $content_type
     * @param array  $extra_params
     *
     * @return array
     * @throws Exception
     */
    public function delete($uri, $content_type = "application/json", $extra_params = array()): array
    {
        return $this->exec("DELETE", $uri, null, $content_type, $extra_params);
    }
}
