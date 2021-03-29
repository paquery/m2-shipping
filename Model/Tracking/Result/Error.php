<?php
/**
 * @author mdenda
 * @date 19/03/2021
 */

namespace Paquery\Shipping\Model\Tracking\Result;


use Magento\Framework\Phrase;
use Magento\Shipping\Model\Tracking\Result\AbstractResult;

class Error extends AbstractResult
{
    /**
     * @return Phrase
     */
    public function getErrorMessage(): Phrase
    {
        return __('Error connecting to Paquery API');
    }
}
