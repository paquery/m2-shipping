<?php
namespace Paquery\Shipping\Observer;

use Paquery\Shipping\Helper\Data;
use Paquery\Shipping\Model\Carrier\Paquery;
use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\OrderRepository;
use Magento\Shipping\Model\InfoFactory;
use Psr\Log\LoggerInterface;

/**
 * Class TrackingPopup
 *
 * @package Paquery\Shipping\Observer
 */
class TrackingPopup
    implements ObserverInterface
{
    /**
     * @var Http
     */
    protected $request;
    /**
     * @var InfoFactory
     */
    protected $shippingInfoFactory;
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var Data
     */
    protected $paqueryHelper;
    /**
     * @var LoggerInterface $logger
     */
    protected $logger;
    /**
     * @var TrackFactory
     */
    protected $trackFactory;
    /**
     * @var Data
     */
    protected $helper;
    /**
     * @var TrackFactory
     */
    protected $_trackFactory;
    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * TrackingPopup constructor.
     *
     * @param Http $request
     * @param InfoFactory $shippingInfoFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param Data $paqueryHelper
     * @param LoggerInterface $logger
     * @param TrackFactory $trackFactory
     * @param OrderRepository $orderRepository
     */
    public function __construct(
        Http $request,
        InfoFactory $shippingInfoFactory,
        ScopeConfigInterface $scopeConfig,
        Data $paqueryHelper,
        LoggerInterface $logger,
        TrackFactory $trackFactory,
        OrderRepository $orderRepository
    ) {
        $this->request = $request;
        $this->shippingInfoFactory = $shippingInfoFactory;
        $this->scopeConfig = $scopeConfig;
        $this->helper = $paqueryHelper;
        $this->logger = $logger;
        $this->_trackFactory = $trackFactory;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Redirects tracking popup to specific URL
     * @param Observer $observer
     *
     * @return Observer
     * @throws Exception
     */
    public function execute(Observer $observer): Observer
    {
        $trackingNumber = false;
        $shippingInfoModel = $this->shippingInfoFactory->create()->loadByHash($this->request->getParam('hash'));
        $tracking = $this->_trackFactory->create();
        $orderId = $shippingInfoModel->getOrderId();

        $tracking = $tracking->getCollection()
            ->addFieldToFilter(
                ['entity_id', 'parent_id', 'order_id'],
                [
                    ['eq' => $shippingInfoModel->getTrackId()],
                    ['eq' => $shippingInfoModel->getShipId()],
                    ['eq' => $orderId],
                ]
            )
            ->setPageSize(1)
            ->setCurPage(1)
            ->load();
        foreach ($tracking->getData() as $track) {
            if ($track['carrier_code'] == Paquery::CODE) {
                $trackingNumber = $track['track_number'];
                if (empty($orderId)) {
                    $orderId = $track['order_id'];
                }
            }
        }

        if (!$trackingNumber) {
            echo 'No se pudo obtener el número de rastreo';
            return $observer;
        }

        $paquery = $this->helper->getApiInstance();
        $username = $this->scopeConfig->getValue('carriers/paquery/username');
        $response = $paquery->get('/integration/' . $username . '/package/' . $trackingNumber);
        if ($response['status'] === 200) {
            $response = $response['response']['data'];

            $order = $this->orderRepository->get($orderId);

            if (!empty($response['statusLog'])) {
                echo '<h3>Envío Nro: ' . $trackingNumber . '<br/></h3>';
                echo "<h3>Destino:<h3/>";
                echo "<h4>";
                for ($i = 1; $i < 3; $i++) {
                    $line = $order->getShippingAddress()->getStreetLine($i);
                    if (!empty($line)) {
                        echo "{$line} <br/>";
                    } else {
                        break;
                    }
                }
                echo "({$order->getShippingAddress()->getPostcode()}) {$order->getShippingAddress()->getCity()} <br/>";
                echo "{$order->getShippingAddress()->getRegion()}";
                echo '</h4>';
                echo "<table>";
                echo "<tr>";
                echo "<th width=\"30%\">Fecha</th>";
                echo "<th width=\"70%\">Estado actual</th>";
                echo "</tr>";
                foreach ($response['statusLog'] as $tracking_status) {
                    echo "<tr>";
                    echo "<td>" . $tracking_status['modificationDate'] . "</td>";
                    echo "<td>" . $tracking_status['statusDescription'] . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
                echo '<style>
                table {
                  border-collapse: collapse;
                  width: 100%;
                  margin-bottom: 20px;
                }

                th, td {
                  text-align: left;
                  padding: 8px;
                }

                tr:nth-child(even) {background-color: #f2f2f2;}
                </style>';
            } else {
                echo '<h2>Pedido sin movimientos</h2>';
            }
        } else {
            $this->logger->error('Request params: ' . $trackingNumber);
            $this->logger->error('Error response API: ' . print_r($response, true));
            echo '<h2>Hubo un error al contactar al servidor, por favor intenta nuevamente</h2>';
        }

        return $observer;
    }

}
