<?php
namespace Paquery\Shipping\Helper;

/**
 * Class CarrierData
 *
 * @package Paquery\Shipping\Helper
 */
class CarrierData extends Data
{
    /**
     *
     */
    const XML_PATH_ATTRIBUTES_MAPPING = 'carriers/paquery/attributesmapping';
    /**
     *
     */
    const PAQUERY_LENGTH_UNIT = 'mt';
    /**
     *
     */
    const PAQUERY_WEIGHT_UNIT = 'kg';

    /**
     * @var array
     */
    protected $_products = [];
    /**
     * @var
     */
    protected $_mapping;
    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $productFactory;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;


    /**
     * @param $items
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getDimensions($items)
    {
        $dimensions = [];
        $total_weight = $volume = 0;
        foreach ($items as $item) {
            $product = [
                'height' => $this->_getShippingDimension($item, 'height'),
                'width' => $this->_getShippingDimension($item, 'width'),
                'length' => $this->_getShippingDimension($item, 'length'),
                'weight' => $this->_getShippingDimension($item, 'weight')
            ];
            $product['volume'] = $product['height'] * $product['width'] * $product['length'];
            $dimensions[] = $product;
            $total_weight += $product['weight'];
        }
        $dimensions['total_weight'] = $total_weight;
        return $dimensions;
    }

    /**
     * @param $item
     * @param $type
     *
     * @return int|string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function _getShippingDimension($item, $type)
    {
        $attributeMapped = $this->_getConfigAttributeMapped($type);
        if (!empty($attributeMapped)) {
            if (!isset($this->_products[$item->getProductId()])) {
                $this->_products[$item->getProductId()] = $this->productFactory->create()->load($item->getProductId());
            }
            $product = $this->_products[$item->getProductId()];
            $result = $product->getData($attributeMapped);
            $result = $this->getAttributesMappingUnitConversion($type, $result);

            return $result;
        }

        return 0;
    }

    /**
     * @param $type
     *
     * @return null
     */
    protected function _getConfigAttributeMapped($type)
    {
        $res = (isset($this->getAttributeMapping()[$type]['code'])) ? $this->getAttributeMapping()[$type]['code'] : null;
        return $res;
    }

    /**
     * @return array
     */
    public function getAttributeMapping()
    {
        if (empty($this->_mapping)) {
            $mapping = $this->scopeConfig->getValue(self::XML_PATH_ATTRIBUTES_MAPPING);
            $mapping = json_decode($mapping, true);
            $mappingResult = [];
            foreach ($mapping as $key => $map) {
                $mappingResult[$key] = ['code' => $map['attribute_code'], 'unit' => $map['unit']];
            }
            $this->_mapping = $mappingResult;
        }

        return $this->_mapping;
    }

    /**
     * @param $attributeType
     * @param $value
     *
     * @return int|string
     */
    public function getAttributesMappingUnitConversion($attributeType, $value)
    {
        $this->_getConfigAttributeMapped($attributeType);

        if ($attributeType === 'weight') {
            //check if needs conversion
            if ($this->_mapping[$attributeType]['unit'] !== self::PAQUERY_WEIGHT_UNIT) {
                if ($this->_mapping[$attributeType]['unit'] === 'gr') $zm_unit = 'GRAM';
                if ($this->_mapping[$attributeType]['unit'] === 'kg') $zm_unit = 'KILOGRAM';
                $unit = new \Zend_Measure_Weight((float)$value, $zm_unit);
                $unit->convertTo(\Zend_Measure_Weight::KILOGRAM);

                return $unit->getValue();
            }

        } elseif ($this->_mapping[$attributeType]['unit'] !== self::PAQUERY_LENGTH_UNIT) {
            if ($this->_mapping[$attributeType]['unit'] === 'cm') $zm_unit = 'CENTIMETER';
            if ($this->_mapping[$attributeType]['unit'] === 'mt') $zm_unit = 'METER';
            $unit = new \Zend_Measure_Length((float)$value, $zm_unit);
            $unit->convertTo(\Zend_Measure_Length::METER);

            return $unit->getValue();
        }

        return $value;
    }

}
