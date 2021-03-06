<?php
namespace Paquery\Shipping\Block\Adminhtml\System\Config\Fieldset;

/**
 * Class Mapping
 *
 * @package Paquery\Shipping\Block\Adminhtml\System\Config\Fieldset
 */
class Mapping
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
        $this->addColumn('paquery', array(
            'label' => __('Paquery'),
            'style' => 'width:120px',
        ));
        $this->addColumn('magentoproduct', array(
            'label' => __('Atributo del Producto'),
            'style' => 'width:120px',
        ));

        $this->addColumn('unit', array(
            'label' => __('Unidad del Atributo'),
            'style' => 'width:120px',
        ));

        $this->setTemplate('array_dropdown.phtml');
        $this->helper = $helper;
        $this->attributeCollection = $attributeCollection;

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
