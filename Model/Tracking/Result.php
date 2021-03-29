<?php
/**
 * @author mdenda
 * @date 19/03/2021
 */

namespace Paquery\Shipping\Model\Tracking;

class Result extends \Magento\Shipping\Model\Tracking\Result
{
    protected $tracking;

    protected $carrierTitle;

    protected $trackSummary;

    /**
     * @return mixed
     */
    public function getTracking()
    {
        return $this->tracking;
    }

    /**
     * @param mixed $tracking
     * @return Result
     */
    public function setTracking($tracking): Result
    {
        $this->tracking = $tracking;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCarrierTitle()
    {
        return $this->carrierTitle;
    }

    /**
     * @param mixed $carrierTitle
     * @return Result
     */
    public function setCarrierTitle($carrierTitle): Result
    {
        $this->carrierTitle = $carrierTitle;
        return $this;
    }


    /**
     * @return array|null
     */
    public function getErrorMessage(): ?array
    {
        return $this->_error;
    }

    public function getTrackSummary()
    {
        return "";
    }

    public function getUrl()
    {
        return "http://google.com";
    }

    public function getProgressdetail()
    {

    }

}
