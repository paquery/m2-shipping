<?php
namespace Paquery\Shipping\Block\Adminhtml\Order\View;

use Paquery\Shipping\Helper\Configuration;
use Paquery\Shipping\Helper\Data;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order;

class Custom extends Template
{
    /**
     * @var OrderFactory
     */
    protected $orderFactory;
    /**
     * @var Order
     */
    protected $orderResource;
    /**
     * @var Data
     */
    protected $helper;

    /**
     * Custom constructor.
     * @param Context $context
     * @param OrderFactory $orderFactory
     * @param Order $orderResource
     * @param Configuration $helper
     * @param array $data
     */
    public function __construct(
        Context $context,
        OrderFactory $orderFactory,
        Order $orderResource,
        Configuration $helper,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->orderFactory = $orderFactory;
        $this->orderResource = $orderResource;
        $this->helper = $helper;
    }


    /**
     * @return string
     */
    public function getExternalCode(): string
    {
        $order_id = $this->getRequest()->getParam('order_id');

        $order = $this->orderFactory->create();
        $this->orderResource->load($order, $order_id);

        $tracksCollection = $order->getTracksCollection();
        $tracking = false;
        foreach ($tracksCollection->getItems() as $track) {
            if(strpos($track->getTrackNumber(), 'MG-') !== false){
                $tracking = $track->getTrackNumber();
            }
        }
        return $tracking;
    }

    /**
     * Returns the API URL
     *
     * @return mixed
     */
    public function getApiUrl()
    {
        list($url,,) = $this->helper->getConnectionData();
        return $url;
    }
}
