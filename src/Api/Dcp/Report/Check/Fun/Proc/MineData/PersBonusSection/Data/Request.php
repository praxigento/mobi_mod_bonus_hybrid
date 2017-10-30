<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc\MineData\PersBonusSection\Data;

class Request
    extends \Praxigento\Core\Data
{
    const A_CUSTOMER_ID = 'customerId';
    const A_PERIOD = 'period';

    /**
     * @return int
     */
    public function getCustomerId()
    {
        $result = parent::get(self::A_CUSTOMER_ID);
        return $result;
    }

    /**
     * 'YYYYMM'
     *
     * @return string
     */
    public function getPeriod()
    {
        $result = parent::get(self::A_PERIOD);
        return $result;
    }

    /**
     * @param int $data
     */
    public function setCustomerId($data)
    {
        parent::set(self::A_CUSTOMER_ID, $data);
    }

    /**
     * @param string $data 'YYYYMM'
     */
    public function setPeriod($data)
    {
        parent::set(self::A_PERIOD, $data);
    }
}