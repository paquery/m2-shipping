<?php
namespace Paquery\Shipping\Block\Adminhtml\System\Config\Fieldset;
/**
 * Class ZipCodeMap
 *
 * @package Paquery\Shipping\Block\Adminhtml\System\Config\Fieldset
 */
class ZipCodeMap
    extends \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
{

    /**
     * @var \Paquery\Shipping\Helper\Data
     */
    private $helper;
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection
     */
    private $attributeCollection;
    /**
     * @param \Magento\Backend\Block\Context      $context
     * @param \Magento\Backend\Model\Auth\Session $authSession
     * @param \Magento\Framework\View\Helper\Js   $jsHelper
     * @param array                               $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\Framework\View\Helper\Js $jsHelper,
        \Paquery\Shipping\Helper\Data $helper,
        \Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection $attributeCollection,
        array $data = []
    ) {
        $this->addColumn('zone', array(
            'label' => __('Zona'),
            'style' => 'width:100px',
        ));
        $this->addColumn('cp', array(
            'label' => __('CPs'),
            'style' => 'width:80px',
        ));
        $this->addColumn('ship12hs', array(
            'label' => __('Precio 12hs'),
            'style' => 'width:120px',
        ));
        $this->addColumn('ship24hs', array(
            'label' => __('Precio 24hs'),
            'style' => 'width:120px',
        ));
        $this->addColumn('ship48hs', array(
            'label' => __('Precio 48hs'),
            'style' => 'width:120px',
        ));
        $this->addColumn('shipPickup', array(
            'label' => __('Precio Pickup'),
            'style' => 'width:120px',
        ));
        $this->addColumn('shipSeller', array(
            'label' => __('Precio Seller'),
            'style' => 'width:120px',
        ));

        $this->helper = $helper;
        $this->attributeCollection = $attributeCollection;

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Añadir zona');

        parent::__construct($context);
    }

    /**
     * @return $this
     */
    public function _getAttributes()
    {
        $attributes = $this->attributeCollection
            ->addFieldToFilter('is_visible', 1)
            ->addFieldToFilter('frontend_input', ['nin' => ['boolean', 'date', 'datetime', 'gallery', 'image', 'media_image', 'select', 'multiselect', 'textarea']])
            ->load();


        return $attributes;
    }

    /**
     * @return array
     */
    public function _getStoredMappingValues()
    {
        $prevValues = [];
        foreach ($this->getArrayRows() as $key => $_row) {
            $prevValues[$key] = ['attribute_code' => $_row->getData('attribute_code'), 'unit' => $_row->getData('unit')];
        }

        return $prevValues;
    }

    /**
     * @return array
     */
    public function _getPaqueryLabel()
    {
        return [__('Longitud'), __('Ancho'), __('Alto'), __('Peso')];
    }
}
