<?php

namespace Paquery\Shipping\Helper;

/**
 * Class ItemData
 *
 * @package Paquery\Shipping\Helper
 */
class ItemData
    extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @param $item
     *
     * @return mixed
     */
    public function itemGetQty($item)
    {
        if ($item->getParentItem()) {
            $item = $item->getParentItem();
        }
        $qty = (in_array('getQty', get_class_methods($item))) ? $item->getQty() : $item->getQtyOrdered();

        return $qty;
    }
}
