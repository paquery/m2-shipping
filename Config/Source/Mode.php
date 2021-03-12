<?php
/**
 * @author mdenda
 * @date 04/03/2021
 */

namespace Paquery\Shipping\Config\Source;


use Magento\Framework\Data\OptionSourceInterface;

class Mode implements OptionSourceInterface
{

    const PROD = 1;
    const TEST = 2;

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::PROD,
                'label' => __('Prod')
            ],
            [
                'value' => self::TEST,
                'label' => __('Test')]
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            self::PROD => __('Prod'),
            self::TEST => __('Test')
        ];
    }
}
