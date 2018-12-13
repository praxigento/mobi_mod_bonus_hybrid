<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast\Z;

use Praxigento\BonusBase\Service\Period\Calc\IAdd as PPeriodAdd;

/**
 * Local process to register new period & calculation for forecast calculations (plain & compressed).
 */
class Register
    implements \Praxigento\Core\Api\App\Service\Process
{
    /** string  */
    const IN_CALC_TYPE_CODE = 'calcTypeCode';
    const IN_PERIOD = 'period';

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
        $period = $ctx->get(self::IN_PERIOD);

        /* get calculation period (begin, end dates) */
        list($dateFrom, $dateTo) = $this->getPeriod($period);

        /* register new calculation for period */
        $calcId = $this->registerNewCalc($dateFrom, $dateTo, $calcTypeCode);

        $result = new \Praxigento\Core\Data();
        $result->set(self::OUT_CALC_ID, $calcId);
        return $result;
    }

    /**
     * Return 2 dates (period being/end): first day of the month and yesterday.
     *
     * @param string|null $period 'YYYYMM'
     * @return array
     */
    private function getPeriod($period)
    {
        /* get current month as MONTH period */
        if (!$period) {
            $month = $this->hlpPeriod->getPeriodCurrent(null, 0, \Praxigento\Core\Api\Helper\Period::TYPE_MONTH);
            /* get current date then get yesterday date as end of period */
            $begin = $this->hlpPeriod->getPeriodFirstDate($month);
            $today = $this->hlpPeriod->getPeriodCurrent();
            $end = $this->hlpPeriod->getPeriodPrev($today);
        } else {
            $month = $period;
            $begin = $this->hlpPeriod->getPeriodFirstDate($month);
            $end = $this->hlpPeriod->getPeriodLastDate($month);
        }
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
     * @throws \Exception
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