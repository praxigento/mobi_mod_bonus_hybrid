<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\Forecast\Plain\Calc as SubCalc;
use Praxigento\BonusHybrid\Service\Calc\Forecast\Plain\GetDownline as PGetDownline;

class Plain
    implements \Praxigento\Core\Api\App\Service\Process
{
    /** string 'YYYY', 'YYYYMM' or 'YYYYMMDD' */
    const CTX_IN_PERIOD = 'in.period';
    /** @var \Praxigento\Accounting\Repo\Dao\Account */
    private $daoAcc;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $daoBonDwnl;
    /** @var \Praxigento\BonusBase\Repo\Dao\Calculation */
    private $daoCalc;
    /** @var  \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\Accounting\Api\Service\Balance\Get\Turnover */
    private $servBalanceGetTurnover;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\IAdd */
    private $servPeriodAdd;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\Forecast\Plain\Calc */
    private $subCalc;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\Plain\GetDownline */
    private $subGetDownline;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        \Praxigento\Accounting\Repo\Dao\Account $daoAcc,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoBonDwnl,
        \Praxigento\BonusBase\Repo\Dao\Calculation $daoCalc,
        \Praxigento\Accounting\Api\Service\Balance\Get\Turnover $servBalanceGetTurnover,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\Plain\Calc $subCalc,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\Plain\GetDownline $subGetDownline,
        \Praxigento\BonusBase\Service\Period\Calc\IAdd $servPeriodAdd
    )
    {
        $this->logger = $logger;
        $this->hlpPeriod = $hlpPeriod;
        $this->daoAcc = $daoAcc;
        $this->daoBonDwnl = $daoBonDwnl;
        $this->daoCalc = $daoCalc;
        $this->servBalanceGetTurnover = $servBalanceGetTurnover;
        $this->subCalc = $subCalc;
        $this->subGetDownline = $subGetDownline;
        $this->servPeriodAdd = $servPeriodAdd;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        $this->logger->info("'Forecast Plain' calculation is started.");

        $period = $ctx->get(self::CTX_IN_PERIOD);

        /* get calculation period (begin, end dates) */
        list($dateFrom, $dateTo) = $this->getPeriod($period);

        /* register new calculation for period */
        $calcId = $this->registerNewCalc($dateFrom, $dateTo);
        $this->logger->info("New 'Forecast Plain' calculation is registered: #$calcId ($dateFrom-$dateTo).");

        /* get customers downline for $dateTo */
        $ctxDwnl = new \Praxigento\Core\Data();
        $ctxDwnl->set(PGetDownline::CTX_IN_CALC_ID, $calcId);
        $ctxDwnl->set(PGetDownline::CTX_IN_DATE_ON, $dateTo);
        $this->subGetDownline->exec($ctxDwnl);
        /** @var \Praxigento\BonusHybrid\Repo\Data\Downline[] $dwnlTree */
        $dwnlTree = $ctxDwnl->get(PGetDownline::CTX_OUT_DWNL);

        /* get system customer */
        $custSysId = $this->daoAcc->getSystemCustomerId();

        /* get PV turnover for period */
        $entries = $this->getPvTurnover($dateFrom, $dateTo);

        /* extract only not zero turnovers */
        $filteredTurnover = [];
        $totalPvPositive = $totalPvNegative = 0;
        /** @var \Praxigento\Accounting\Api\Service\Balance\Get\Turnover\Response\Entry $entry */
        foreach ($entries as $entry) {
            $turnover = $entry->turnover;
            $customerId = $entry->customerId;
            if (
                (abs($turnover) > Cfg::DEF_ZERO) &&
                ($customerId != $custSysId)
            ) {
                $filteredTurnover[$customerId] = $entry;
                /** @var \Praxigento\BonusHybrid\Repo\Data\Downline $dwnlEntry */
                $dwnlEntry = $dwnlTree[$customerId];
                $dwnlEntry->setPv($turnover);
                if ($turnover > 0) {
                    $totalPvPositive += $turnover;
                } else {
                    $totalPvNegative += $turnover;
                }
            }
        }
        $this->logger->info("Total positive turnover: $totalPvPositive PV; total negative turnover: $totalPvNegative PV.");


        /* perform calculation */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(SubCalc::CTX_DWNL_TREE, $dwnlTree);
        $this->subCalc->exec($ctx);

        /* replace actual data in repository */
        $this->saveDownline($dwnlTree);

        /* finalize calculation */
        $this->daoCalc->markComplete($calcId);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("'Forecast Plain' calculation is completed.");
    }

    /**
     * Return 2 dates (period being/end): first day of the month and today.
     *
     * @param string $requested 'YYYY', 'YYYYMM' or 'YYYYMMDD'
     * @return array
     */
    private function getPeriod($requested)
    {
        $month = substr($requested, 0, 6);
        $begin = $this->hlpPeriod->getPeriodFirstDate($month);
        $end = $this->hlpPeriod->getPeriodLastDate($month);
        $result = [$begin, $end];
        return $result;
    }

    private function getPvTurnover($dateFrom, $dateTo)
    {
        $reqTurnover = new \Praxigento\Accounting\Api\Service\Balance\Get\Turnover\Request();
        $reqTurnover->assetTypeCode = Cfg::CODE_TYPE_ASSET_PV;
        $reqTurnover->dateFrom = $dateFrom;
        $reqTurnover->dateTo = $dateTo;
        $respTurnover = $this->servBalanceGetTurnover->exec($reqTurnover);
        $result = $respTurnover->entries;
        return $result;
    }

    /**
     * Register new period and related calculation.
     *
     * @param string $from begin of the period (YYYYMMDD)
     * @param string $to end of the period (YYYYMMDD)
     * @return int registered calculation ID
     *
     */
    private function registerNewCalc($from, $to)
    {
        $result = null;
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->servPeriodAdd::CTX_IN_DSTAMP_BEGIN, $from);
        $ctx->set($this->servPeriodAdd::CTX_IN_DSTAMP_END, $to);
        $ctx->set($this->servPeriodAdd::CTX_IN_CALC_TYPE_CODE, Cfg::CODE_TYPE_CALC_FORECAST_PLAIN);
        $this->servPeriodAdd->exec($ctx);
        $success = $ctx->get($this->servPeriodAdd::CTX_OUT_SUCCESS);
        if ($success) {
            $result = $ctx->get($this->servPeriodAdd::CTX_OUT_CALC_ID);
        }
        return $result;
    }

    private function saveDownline($items)
    {
        foreach ($items as $item) {
            $this->daoBonDwnl->create($item);
        }
    }
}