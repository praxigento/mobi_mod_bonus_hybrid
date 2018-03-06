<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc;

use Praxigento\BonusBase\Service\Period\Calc\IAdd as PPeriodAdd;

/**
 * Local process to register new period & calculation for forecast calculations (plain & compressed).
 */
class Register
    implements \Praxigento\Core\App\Service\IProcess
{
    /** string  */
    const IN_CALC_TYPE_CODE = 'calcTypeCode';
    /** int */
    const OUT_CALC_ID = 'calcId';

    /** @var  \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\IAdd */
    private $procPeriodAdd;

    public function __construct(
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        \Praxigento\BonusBase\Service\Period\Calc\IAdd $procPeriodAdd
    )
    {
        $this->hlpPeriod = $hlpPeriod;
        $this->procPeriodAdd = $procPeriodAdd;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /* get working data from context */
        $calcTypeCode = $ctx->get(self::IN_CALC_TYPE_CODE);

        /* get calculation period (begin, end dates) */
        list($dateFrom, $dateTo) = $this->getPeriod();

        /* register new calculation for period */
        $calcId = $this->registerNewCalc($dateFrom, $dateTo, $calcTypeCode);

        $result = new \Praxigento\Core\Data();
        $result->set(self::OUT_CALC_ID, $calcId);
        return $result;
    }

    /**
     * Return 2 dates (period being/end): first day of the month and yesterday.
     *
     * @return array
     */
    private function getPeriod()
    {
        /* get current month as MONTH period */
        $month = $this->hlpPeriod->getPeriodCurrent(null, 0, \Praxigento\Core\Api\Helper\Period::TYPE_MONTH);
        /* get current date then get yesterday date (end of period) */
        $today = $this->hlpPeriod->getPeriodCurrent();
        $end = $this->hlpPeriod->getPeriodPrev($today);
        $begin = $this->hlpPeriod->getPeriodFirstDate($month);
        $result = [$begin, $end];
        return $result;
    }

    /**
     * Register new period and related calculation.
     *
     * @param string $from begin of the period (YYYYMMDD)
     * @param string $to end of the period (YYYYMMDD)
     * @param string $calcTypeCode
     * @return int registered calculation ID
     *
     */
    private function registerNewCalc($from, $to, $calcTypeCode)
    {
        $result = null;
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(PPeriodAdd::CTX_IN_DSTAMP_BEGIN, $from);
        $ctx->set(PPeriodAdd::CTX_IN_DSTAMP_END, $to);
        $ctx->set(PPeriodAdd::CTX_IN_CALC_TYPE_CODE, $calcTypeCode);
        $this->procPeriodAdd->exec($ctx);
        $success = $ctx->get(PPeriodAdd::CTX_OUT_SUCCESS);
        if ($success) {
            $result = $ctx->get(PPeriodAdd::CTX_OUT_CALC_ID);
        }
        return $result;
    }
}