<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Compress\Phase2\Data;

/**
 * Structure for legs data (OV and reference to "legged" customer).
 * Only max & second legs have related customers.
 */
class Legs
    extends \Praxigento\Core\Data
{
    const MAX_CUST_ID = 'max_cust_id';
    const MAX_OV = 'max_ov';
    const OTHERS_OV = 'others_ov';
    const SECOND_CUST_ID = 'second_cust_id';
    const SECOND_OV = 'second_ov';

    /**
     * @return int
     */
    public function getMaxCustId()
    {
        $result = parent::get(self::MAX_CUST_ID);
        return $result;
    }

    /**
     * @return float
     */
    public function getMaxOv()
    {
        $result = parent::get(self::MAX_OV);
        return $result;
    }

    /**
     * @return float
     */
    public function getOthersOv()
    {
        $result = parent::get(self::OTHERS_OV);
        return $result;
    }

    /**
     * @return int
     */
    public function getSecondCustId()
    {
        $result = parent::get(self::SECOND_CUST_ID);
        return $result;
    }

    /**
     * @return float
     */
    public function getSecondOv()
    {
        $result = parent::get(self::SECOND_OV);
        return $result;
    }

    public function setMaxCustId($data)
    {
        parent::set(self::MAX_CUST_ID, $data);
    }

    public function setMaxOv($data)
    {
        parent::set(self::MAX_OV, $data);
    }

    public function setOthersOv($data)
    {
        parent::set(self::OTHERS_OV, $data);
    }

    public function setSecondCustId($data)
    {
        parent::set(self::SECOND_CUST_ID, $data);
    }

    public function setSecondOv($data)
    {
        parent::set(self::SECOND_OV, $data);
    }
}