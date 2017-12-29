<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast;

use Praxigento\Accounting\Repo\Entity\Account as RAccount;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc\Clean as PCleanCalcData;
use Praxigento\BonusHybrid\Service\Calc\Forecast\Plain\Calc as SubCalc;
use Praxigento\BonusHybrid\Service\Calc\Forecast\Plain\GetDownline as PGetDownline;

class Plain
    implements \Praxigento\BonusHybrid\Service\Calc\Forecast\IPlain
{
    /** @var \Praxigento\Accounting\Api\Service\Balance\Get\Turnover */
    private $callBalanceGetTurnover;
    /** @var  \Praxigento\Core\Tool\IPeriod */
    private $hlpPeriod;
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc\Clean */
    private $procCleanCalc;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\IAdd */
    private $procPeriodAdd;
    /** @var \Praxigento\Accounting\Repo\Entity\Account */
    private $repoAcc;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoBonDwnl;
    /** @var \Praxigento\BonusBase\Repo\Entity\Calculation */
    private $repoCalc;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\Forecast\Plain\Calc */
    private $subCalc;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\Plain\GetDownline */
    private $subGetDownline;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Praxigento\Core\Tool\IPeriod $hlpPeriod,
        RAccount $repoAcc,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoBonDwnl,
        \Praxigento\BonusBase\Repo\Entity\Calculation $repoCalc,
        \Praxigento\Accounting\Api\Service\Balance\Get\Turnover $callBalanceGetTurnover,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\Plain\Calc $subCalc,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\Plain\GetDownline $subGetDownline,
        \Praxigento\BonusBase\Service\Period\Calc\IAdd $procPeriodAdd,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc\Clean $procCleanCalc
    )
    {
        $this->logger = $logger;
        $this->hlpPeriod = $hlpPeriod;
        $this->repoAcc = $repoAcc;
        $this->repoBonDwnl = $repoBonDwnl;
        $this->repoCalc = $repoCalc;
        $this->callBalanceGetTurnover = $callBalanceGetTurnover;
        $this->subCalc = $subCalc;
        $this->subGetDownline = $subGetDownline;
        $this->procPeriodAdd = $procPeriodAdd;
        $this->procCleanCalc = $procCleanCalc;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        $this->logger->info("'Forecast Plain' calculation is started.");

        $period = $ctx->get(self::CTX_IN_PERIOD);

        /* clean up existing forecast calculation data */
        $ctxClean = new \Praxigento\Core\Data();
        $ctxClean->set(PCleanCalcData::IN_CALC_TYPE_CODE, Cfg::CODE_TYPE_CALC_FORECAST_PLAIN);
        $this->procCleanCalc->exec($ctxClean);

        /* get calculation period (begin, end dates) */
        list($dateFrom, $dateTo) = $this->getPeriod($period);

        /* register new calculation for period */
        $calcId = $this->registerNewCalc($dateFrom, $dateTo);

        /* get customers downline for $dateTo */
        $ctxDwnl = new \Praxigento\Core\Data();
        $ctxDwnl->set(PGetDownline::CTX_IN_CALC_ID, $calcId);
        $ctxDwnl->set(PGetDownline::CTX_IN_DATE_ON, $dateTo);
        $this->subGetDownline->exec($ctxDwnl);
        /** @var \Praxigento\BonusHybrid\Repo\Entity\Data\Downline[] $dwnlTree */
        $dwnlTree = $ctxDwnl->get(PGetDownline::CTX_OUT_DWNL);

        /* get representative customer */
        $custRepresId = $this->repoAcc->getRepresentativeCustomerId();

        /* get PV turnover for period */
        $entries = $this->getPvTurnover($dateFrom, $dateTo);

        /* extract only positive turnovers */
        $positiveTurnover = [];
        /** @var \Praxigento\Accounting\Api\Service\Balance\Get\Turnover\Response\Entry $entry */
        foreach ($entries as $entry) {
            $turnover = $entry->turnover;
            $customerId = $entry->customerId;
            if (
                ($turnover > Cfg::DEF_ZERO) &&
                ($customerId != $custRepresId)
            ) {
                $positiveTurnover[$customerId] = $entry;
                /** @var \Praxigento\BonusHybrid\Repo\Entity\Data\Downline $dwnlEntry */
                $dwnlEntry = $dwnlTree[$customerId];
                $dwnlEntry->setPv($turnover);
            }
        }

        /* perform calculation */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(SubCalc::CTX_DWNL_TREE, $dwnlTree);
        $this->subCalc->exec($ctx);

        /* replace actual data in repository */
        $this->saveDownline($dwnlTree);

        /* finalize calculation */
        $this->repoCalc->markComplete($calcId);
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
        if ($requested) {
            /* convert $requested to MONTH period */
            $month = $this->hlpPeriod->getPeriodNext($requested, \Praxigento\Core\Tool\IPeriod::TYPE_MONTH);
            $month = $this->hlpPeriod->getPeriodPrev($month, \Praxigento\Core\Tool\IPeriod::TYPE_MONTH);
            /* get period end */
            $end = $this->hlpPeriod->getPeriodLastDate($month);
        } else {
            /* get current month as MONTH period */
            $month = $this->hlpPeriod->getPeriodCurrent(null, 0, \Praxigento\Core\Tool\IPeriod::TYPE_MONTH);
            /* get current date then get yesterday date (end of period) */
            $today = $this->hlpPeriod->getPeriodCurrent();
//            $end = $this->hlpPeriod->getPeriodPrev($today);
            $end = $today;
        }
        $begin = $this->hlpPeriod->getPeriodFirstDate($month);
        $result = [$begin, $end];
        return $result;
    }

    private function getPvTurnover($dateFrom, $dateTo)
    {
        $reqTurnover = new \Praxigento\Accounting\Api\Service\Balance\Get\Turnover\Request();
        $reqTurnover->assetTypeCode = Cfg::CODE_TYPE_ASSET_PV;
        $reqTurnover->dateFrom = $dateFrom;
        $reqTurnover->dateTo = $dateTo;
        $respTurnover = $this->callBalanceGetTurnover->exec($reqTurnover);
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
        $ctx->set($this->procPeriodAdd::CTX_IN_DSTAMP_BEGIN, $from);
        $ctx->set($this->procPeriodAdd::CTX_IN_DSTAMP_END, $to);
        $ctx->set($this->procPeriodAdd::CTX_IN_CALC_TYPE_CODE, Cfg::CODE_TYPE_CALC_FORECAST_PLAIN);
        $this->procPeriodAdd->exec($ctx);
        $success = $ctx->get($this->procPeriodAdd::CTX_OUT_SUCCESS);
        if ($success) {
            $result = $ctx->get($this->procPeriodAdd::CTX_OUT_CALC_ID);
        }
        return $result;
    }

    private function saveDownline($items)
    {
        foreach ($items as $item) {
            $this->repoBonDwnl->create($item);
        }
    }
}