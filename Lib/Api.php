<?php
namespace Paquery\Shipping\Lib;
use Exception;

/**
 * Integration Library
 * Access for payments integration
 *
 * @author hcasatti
 *
 */


class Api
{
    /**
     * @var RestClient
     */
    protected $client;

    /**
     * Api constructor.
     * @param RestClient $client
     */
    public function __construct(
        RestClient $client
    )
    {
        $this->client = $client;
    }



    /* Generic resource call methods */

    /**
     * Generic resource get
     * @param string uri
     * @param array params
     * @param bool authenticate = true
     * @return array
     * @throws Exception
     */
    public function get($uri, $params = null, $authenticate = false): array
    {
        $params = is_array($params) ? $params : array();

        if (count($params) > 0) {
            $uri .= (strpos($uri, "?") === false) ? "?" : "&";
            $uri .= $this->build_query($params);
        }
        return $this->client->get($uri);
    }

    /**
     * Generic resource post
     * @param string uri
     * @param array data
     * @param array params
     * @return array
     * @throws Exception
     */
    public function post($uri, $data, $params = null): array
    {
        $params = is_array($params) ? $params : array();

        if (count($params) > 0) {
            $uri .= (strpos($uri, "?") === false) ? "?" : "&";
            $uri .= $this->build_query($params);
        }

        return $this->client->post($uri, $data, "application/json");
    }

    /**
     * Generic resource put
     * @param string uri
     * @param array data
     * @param array params
     * @return array
     * @throws Exception
     */
    public function put($uri, $data, $params = null)
    {
        $params = is_array($params) ? $params : array();

        if (count($params) > 0) {
            $uri .= (strpos($uri, "?") === false) ? "?" : "&";
            $uri .= $this->build_query($params);
        }

        return $this->client->put($uri, $data);
    }

    /**
     * Generic resource delete
     * @param string uri
     * @param array data
     * @param array params
     * @return array
     * @throws Exception
     */
    public function delete($uri, $params = null): array
    {
        $params = is_array($params) ? $params : array();

        if (count($params) > 0) {
            $uri .= (strpos($uri, "?") === false) ? "?" : "&";
            $uri .= $this->build_query($params);
        }

        return $this->client->delete($uri);
    }

    /* **************************************************************************************** */

    /**
     * @param $params
     *
     * @return string
     */
    protected function build_query($params): string
    {
        if (function_exists("http_build_query")) {
            return http_build_query($params, "", "&");
        } else {
            $elements = [];
            foreach ($params as $name => $value) {
                $elements[] = "{$name}=" . urlencode($value);
            }

            return implode("&", $elements);
        }
    }
}

