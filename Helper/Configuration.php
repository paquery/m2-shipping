<?php

namespace Paquery\Shipping\Helper;

use Paquery\Shipping\Config\Source\Mode;
use Paquery\Shipping\Lib\RestClient;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Class ItemData
 *
 * @package Paquery\Shipping\Helper
 */
class Configuration
{
    /*
    Todas las URL se construyen en el formato [URL BASE] + [Endpoint]
    Ej:
    [http://motos.epresis.com/single_carrier] + [retiro.php] = http://motos.epresis.com/single_carrier/retiro.php
     */

    // URL Base, todos los endpoints usarán esta URL para construirse
    // NO colocar slash (/) al final
    const PAQUERY_ROOT_URL = 'http://desarrollo.epresis.com/demo-epresis';

    // Endpoint para consultar servicios disponibles para un carrito
    const PAQUERY_SERVICES_ENDPOINT = '/web/api/v1/servicios.json';

    // Endpoint para consultar precios disponibles para un carrito
    const PAQUERY_PRICES_ENDPOINT = '/web/api/v1/precios.json';

    // Endpoint para enviar y confirmar un pedido una vez creado
    const PAQUERY_CONFIRM_ENDPOINT = '/web/api/v1/retiros.json';

    // Endpoint para consultar el voucher de un pedido
    const PAQUERY_VOUCHER_ENDPOINT = '/web/api/v1/vouchers.json';

    // Endpoint para consultar la etiqueta de un pedido
    const PAQUERY_SHIPPING_LABEL_ENDPOINT = '/web/api/v1/etis.json';

    // Endpoint para consultar el tracking de un pedido
    const PAQUERY_TRACKING_ENDPOINT = '/getInfo.php';

    // Nombre del método de envío
    const PAQUERY_NAME = 'Custom';

    const KEY_URL   = 'url';
    const KEY_USER  = 'user';
    const KEY_PASS  = 'pass';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var EncryptorInterface
     */
    protected $enc;

    /**
     * Configuration constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $enc
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $enc
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->enc = $enc;
    }


    /**
     * Return url, user and password depending in the execution mode
     *
     * @return array
     */
    public function getConnectionData():array
    {
        if (Mode::PROD == $this->scopeConfig->getValue(RestClient::CARRIERS_PAQUERY_MODE)) {
            return array(
                $this->scopeConfig->getValue(RestClient::CARRIERS_PAQUERY_URL),
                $this->scopeConfig->getValue(RestClient::CARRIERS_PAQUERY_USERNAME),
                $this->enc->decrypt($this->scopeConfig->getValue(RestClient::CARRIERS_PAQUERY_PASSWORD))
            );
        } else {
            return array (
                $this->scopeConfig->getValue(RestClient::CARRIERS_PAQUERY_URL_TEST),
                $this->scopeConfig->getValue(RestClient::CARRIERS_PAQUERY_USERNAME_TEST),
                $this->enc->decrypt($this->scopeConfig->getValue(RestClient::CARRIERS_PAQUERY_PASSWORD_TEST))
            );
        }
    }
}
