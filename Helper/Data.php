<?php
namespace Paquery\Shipping\Helper;


use Paquery\Shipping\Lib\Api;
use Magento\Catalog\Model\ProductFactory;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Registry;
use Magento\Framework\View\Asset\Repository;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class Data
 *
 * @package Paquery\Shipping\Helper
 */
class Data extends AbstractHelper
{

    /**
     *
     */
    const XML_PATH_ATTRIBUTES_MAPPING = 'carriers/paquery/attributesmapping';
    /**
     *
     */
    const PAQUERY_LENGTH_UNIT = 'cm';
    /**
     *
     */
    const PAQUERY_WEIGHT_UNIT = 'gr';

    /**
     * @var
     */
    protected $_mapping;
    /**
     * @var array
     */
    protected $_products = [];

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var ItemData
     */
    protected $helperItem;

    /**
     * @var TrackFactory
     */
    protected $trackFactory;
    /**
     * @var Shipment
     */
    protected $shipment;

    /**
     * @var Registry
     */
    protected $registry;

    /*
     * @var \Magento\Framework\Module\Dir\Reader
     */
    protected $dirReader;

    /*
     * @var \Magento\Framework\View\Asset\Repository
     */
    protected $assetRepository;

    /*
     * @var \Magento\Framework\Filesystem
     */
    protected $fileSystem;

    /*
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /*
     * @var \Magento\Framework\App\Filesystem\DirectoryList
     */
    protected $directoryList;

    /*
     * @var \Magento\Framework\Filesystem\Io\File
     */
    protected $file;
    /**
     * @var Api
     */
    protected $api;

    /**
     * Data constructor.
     *
     * @param Context $context
     * @param Session $checkoutSession
     * @param ProductFactory $productFactory
     * @param ItemData $helperItem
     * @param TrackFactory $trackFactory
     * @param Shipment $shipment
     * @param Api $api
     * @param Registry $registry
     * @param LoggerInterface $logger
     * @param Reader $dirReader
     * @param Repository $assetRepository
     * @param Filesystem $fileSystem
     * @param StoreManagerInterface $storeManager
     * @param DirectoryList $directoryList
     * @param File $file
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        ProductFactory $productFactory,
        ItemData $helperItem,
        TrackFactory $trackFactory,
        Shipment $shipment,
        Api $api,
        Registry $registry,
        LoggerInterface $logger,
        Reader $dirReader,
        Repository $assetRepository,
        Filesystem $fileSystem,
        StoreManagerInterface $storeManager,
        DirectoryList $directoryList,
        File $file

    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->productFactory = $productFactory;
        $this->helperItem = $helperItem;
        $this->trackFactory = $trackFactory;
        $this->shipment = $shipment;
        $this->registry = $registry;
        $this->_logger = $logger;
        $this->_dirReader = $dirReader;
        $this->_assetRepository = $assetRepository;
        $this->_fileSystem = $fileSystem;
        $this->_storeManager = $storeManager;
        $this->_directoryList = $directoryList;
        $this->_file = $file;
        $this->api = $api;
    }


    /**
     * Retrieves Quote
     *
     * @return Quote
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getQuote(): Quote
    {
        return $this->checkoutSession->getQuote();
    }

    /**
     * @param $method
     *
     * @return bool
     */
    public function isPaqueryMethod($method): bool
    {
        $shippingMethod = substr($method, 0, strpos($method, '_'));

        return ($shippingMethod === \Paquery\Shipping\Model\Carrier\Paquery::CODE);
    }

    /**
     * Return items for further shipment rate evaluation. We need to pass children of a bundle instead passing the
     * bundle itself, otherwise we may not get a rate at all (e.g. when total weight of a bundle exceeds max weight
     * despite each item by itself is not)
     *
     * @param $allItems
     * @return array
     */
    public function getAllItems($allItems): array
    {
        $items = [];
        foreach ($allItems as $item) {
            /* @var $item Item */
            if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                // Don't process children here - we will process (or already have processed) them below
                continue;
            }
            if ($item->getHasChildren() && $item->isShipSeparately()) {
                foreach ($item->getChildren() as $child) {
                    if (!$child->getFreeShipping() && !$child->getProduct()->isVirtual()) {
                        $items[] = $child;
                    }
                }
            } else {
                // Ship together - count compound item as one solid
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Return Api instance given AccessToken or ClientId and Secret
     *
     * @return Api
     */
    public function getApiInstance(): Api
    {
        return $this->api;
    }
}
