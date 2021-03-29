<?php
/**
 * @author mdenda
 * @date 24/03/2021
 */
namespace Paquery\Shipping\Plugin\Block\Tracking;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Paquery\Shipping\Model\Carrier\Paquery;

class PopupPlugin
{
    /**
     * @var Registry
     */
    protected $registry;

    public function __construct(
        Registry $registry
    )
    {
        $this->registry = $registry;
    }


    /**
     * @param Template $subject
     * @param $template
     * @return string
     */
    public function beforeSetTemplate(Template $subject, $template): string
    {
        if (Paquery::CODE == $this->registry->registry('current_shipping_carrier')) {
            return 'Paquery_Shipping::tracking/popup.phtml';
        } else {
            return $template;
        }
    }
}
