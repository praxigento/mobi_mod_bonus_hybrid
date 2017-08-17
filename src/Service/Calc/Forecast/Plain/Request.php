<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast\Plain;

class Request
    extends \Praxigento\Core\Service\Base\Request
{
    const A_PERIOD = 'period';

    /**
     * @return string 'YYYY', 'YYYYMM' or 'YYYYMMDD'
     */
    public function getPeriod()
    {
        $result = parent::get(self::A_PERIOD);
        return $result;
    }

    /**
     * @param string $data 'YYYY', 'YYYYMM' or 'YYYYMMDD'
     */
    public function setPeriod($data)
    {
        parent::set(self::A_PERIOD, $data);
    }
}