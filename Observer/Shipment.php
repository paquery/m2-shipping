<?php
namespace Paquery\Shipping\Observer;

use Paquery\Shipping\Helper\CarrierData;
use Paquery\Shipping\Helper\Configuration;
use Paquery\Shipping\Helper\Data;
use Paquery\Shipping\Model\Carrier\Paquery;
use Exception;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Convert\Order;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\Order\ShipmentFactory;
use Psr\Log\LoggerInterface;

/**
 * Class Shipment
 *
 * @package Paquery\Shipping\Observer
 */
class Shipment implements ObserverInterface
{
    /**
     *
     */
    const CODE = 'Paquery';

    /**
     * @var Data
     */
    protected $_shipmentHelper;
    /**
     * @var ShipmentFactory
     */
    protected $_shipmentFactory;
    /**
     * @var \Magento\Sales\Model\Order\Shipment
     */
    protected $_shipment;
    /**
     * @var TrackFactory
     */
    protected $_trackFactory;
    /**
     * @var Transaction
     */
    protected $_transaction;
    /**
     * @var LoggerInterface
     */
    protected $_logger;
    /**
     * @var Data
     */
    protected $_helperCarrierData;
    /**
     * @var Order
     */
    protected $orderConverter;
    /**
     * @var Order
     */
    protected $_orderConverter;
    /**
     * @var Configuration
     */
    protected $configurationHelper;

    /**
     * Shipment constructor.
     *
     * @param Data $shipmentHelper
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @param ShipmentFactory $shipmentFactory
     * @param TrackFactory $trackFactory
     * @param Transaction $transaction
     * @param LoggerInterface $logger
     * @param CarrierData $helperCarrierData
     * @param Order $orderConverter
     * @param Configuration $configurationHelper
     */
    public function __construct(
        Data $shipmentHelper,
        \Magento\Sales\Model\Order\Shipment $shipment,
        ShipmentFactory $shipmentFactory,
        TrackFactory $trackFactory,
        Transaction $transaction,
        LoggerInterface $logger,
        CarrierData $helperCarrierData,
        Order $orderConverter,
        Configuration $configurationHelper
    ) {
        $this->_shipmentHelper = $shipmentHelper;
        $this->_shipmentFactory = $shipmentFactory;
        $this->_shipment = $shipment;
        $this->_trackFactory = $trackFactory;
        $this->_transaction = $transaction;
        $this->_logger = $logger;
        $this->_helperCarrierData = $helperCarrierData;
        $this->_orderConverter = $orderConverter;
        $this->configurationHelper = $configurationHelper;
    }

    /**
     * @param Observer $observer
     *
     * @return mixed
     * @throws Exception
     * @throws bool
     */
    public function execute(Observer $observer)
    {
        $shipment = $observer->getEvent()->getShipment();
        $order = $shipment->getOrder();
        $shipping_method = $order->getShippingMethod();
        $shipping_method = explode('|', $shipping_method);
        if ($shipping_method[0] !== 'paquery_' || !isset($shipping_method[1])) {
            return false;
        }
        $shippingAddress = $order->getShippingAddress();
        $paqueryAddress = $this->getAddressForPaquery($shippingAddress);
        $paquery = $this->_shipmentHelper->getApiInstance();

        list(,$username,) = $this->configurationHelper->getConnectionData();

        $name_items = '';
        foreach ($order->getAllVisibleItems() as $orderItem) {
            $name_items .= $orderItem->getName().', ';
        }
        $package_type = 0;
        switch($shipping_method[1]){
            case 1: case 2: case 3:  $package_type = 2; break;
            case 4: $package_type = 3; break;
            case 5: $package_type = 4; break;
        }
        $params = [
            'caption' => substr($name_items, 0, strlen($name_items)-2),
            'shipping_method' => $shipping_method[1],
            'cost' => $order->getGrandTotal(),
            'external_code' => 'MG-' . $order->getId(), //TODO Fix this to be unique
            'package_type' => $package_type,
            'schedule_destination' => [
                'name' => $shippingAddress->getFirstname() . ' ' . $shippingAddress->getLastname(),
                'shipping_address' => $paqueryAddress['street'] . ' ' . $paqueryAddress['number'] . '. ' . $shippingAddress->getCity() . ' ' . $shippingAddress->getPostcode() . ', ' . $shippingAddress->getRegionCode(),
                'comments' => $shippingAddress->getStreetLine(2),
                'address_comments' => $paqueryAddress['extra'],
                'phone' => $shippingAddress->getTelephone()
            ],
            'client' => [
                'name' => $shippingAddress->getFirstname(),
                'last_name' => $shippingAddress->getLastname(),
                'mail' => $order->getCustomerEmail()
            ]
        ];
        $items = $order->getAllVisibleItems();
        $dimensions = $this->_helperCarrierData->getDimensions($items);
        $total_weight = $dimensions['total_weight'];
        if ($total_weight <= 5) {
            $box_size = 1;
        } else if ($total_weight <= 22) {
            $box_size = 2;
        } else {
            $box_size = 3;
        }
        $params['package_size'] = $box_size;
        $response = $paquery->post('/caronte/integration/' . $username . '/package', $params);
        if (empty($response['response'] || empty($response['response']['data']['package']['external_code']))) {
            $this->_logger->error('Request params: ' . json_encode($params));
            $this->_logger->error('Error al procesar envio: ' . print_r($response, true));
            return false;
        }
        $response = $response['response'];
        if($response['code'] == '1'){
            $this->_logger->error('Already sent package. Email sent');
            return true;
        }
        if ($order->hasShipments()) {
            $shipment = $this->_shipment->load($order->getId(), 'order_id');
        } else {
            $shipment = $this->_shipmentFactory->create($order);
            $order->setIsInProcess(true);
        }
        $shipment->setShippingLabel($response['data']['package']['external_code']);
        $existingTracking = $this->_trackFactory->create()->load($shipment->getOrderId(), 'order_id');
        // Loop through order items
        foreach ($order->getAllItems() as $orderItem) {
            // Check if order item has qty to ship or is virtual
            if (!$orderItem->getQtyOrdered() || $orderItem->getIsVirtual()) continue;
            $qtyShipped = $orderItem->getQtyOrdered();

            // Create shipment item with qty
            $shipmentItem = $this->_orderConverter->itemToShipmentItem($orderItem)->setQty($qtyShipped);

            // Add shipment item to shipment
            $shipment->addItem($shipmentItem);
        }
        if ($existingTracking->getId()) {
            $track = $shipment->getTrackById($existingTracking->getId());
            $track->setNumber($response['data']['package']['external_code'])
                ->setDescription('Paquery')
                ->setTitle('Paquery')
                ->save();
        } else {
            $track = $this->_trackFactory->create()
                ->setShipment($shipment)
                ->setTitle('Paquery')
                ->setNumber($response['data']['package']['external_code'])
                ->setCarrierCode(Paquery::CODE)
                ->setOrderId($order->getId())
                ->save();
        }

        $this->_transaction->addObject($order)->save();
    }

    public function getAddressForPaquery($orderAddress)
    {
        if (!$orderAddress) return false;
        $address = array('street' => '', 'number' => '', 'extra' => '');
        if ($address2 = $orderAddress->getStreetLine(2)) {
            // We have something in the second shipping address, generally it's the department number, check this
            $exp_address1 = explode(' ', $orderAddress->getStreetLine(1));
            if (count($exp_address1) > 1) {
                // The first address already have a space, so maybe the address is something like [Belgrano 500] | [4A]
                $num_items = count($exp_address1);
                $i = 0;
                $tmp_street = array();
                // So, we take all the parts of the address except the last part which should be a numer (Ex: Rivadavia Indarte 500)
                foreach ($exp_address1 as $value) {
                    $i++;
                    if ($i === $num_items) {
                        $tmp_street[] = $value;
                    } else {
                        if (is_numeric($value)) {
                            $address['number'] = $value;
                        } else {
                            $tmp_street[] = $value;
                        }
                    }
                }
                $address['street'] = $address['street'] = implode(' ', $tmp_street);

                // Did we find the street number? or was it in the second address?
                if (empty($address['number'])) {
                    $address['number'] = $address2;
                } else {
                    // If we already got everything (street and number) then the second address is a plus
                    $address['extra'] = $address2;
                }
            } else {
                // If the first address doesn't have a space, we assume it is only the street, Ex: [Belgrano] | [400]
                $address['street'] = $exp_address1[0];
                $address['number'] = $address2;
            }
        } else if ($address1 = $orderAddress->getStreetLine(1)) {
            // We only have something in the first shipping address at this point
            $exp_address1 = explode(' ', $address1);
            if (count($exp_address1) > 1) {
                // This first address already have a space, so maybe the address is something like [Belgrano 500] | [4A]
                $num_items = count($exp_address1);
                $i = 0;
                $tmp_street = array();
                // So, we take all the parts of the address except the last part which should be a numer (Ex: Rivadavia Indarte 500)
                foreach ($exp_address1 as $value) {
                    $i++;
                    if ($i === $num_items) {
                        if (filter_var($value, FILTER_VALIDATE_INT)) {
                            $address['number'] = $value;
                        } else {
                            $tmp_street[] = $value;
                        }
                    } else {
                        $tmp_street[] = $value;
                    }
                }
                $address['street'] = $address['street'] = implode(' ', $tmp_street);
            } else {
                $address['street'] = $address1;
            }
        } else {
            $address = false;
        }
        return $address;
    }
}
