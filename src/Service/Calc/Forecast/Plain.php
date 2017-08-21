<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\Forecast\Calc as SubCalc;
use Praxigento\BonusHybrid\Service\Calc\Forecast\CleanCalcData as ProcCleanCalcData;
use Praxigento\BonusHybrid\Service\Calc\Forecast\GetDownline as ProcGetDownline;

class Plain
    extends \Praxigento\Core\Service\Base\Call
    implements \Praxigento\BonusHybrid\Service\Calc\Forecast\IPlain
{
    /** @var \Praxigento\Accounting\Service\Balance\Get\ITurnover */
    protected $callBalanceGetTurnover;
    /** @var \Praxigento\BonusBase\Service\IPeriod */
    protected $callPeriod;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\CleanCalcData */
    protected $procCleanCalcData;
    /** @var \Praxigento\BonusBase\Repo\Entity\Calculation */
    protected $repoCalc;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    protected $repoDwnl;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\Forecast\Calc */
    protected $subCalc;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\GetDownline */
    protected $subGetDownline;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\GetRanks */
    protected $subGetRanks;
    /** @var  \Praxigento\Core\Tool\IPeriod */
    protected $toolPeriod;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\Core\Tool\IPeriod $toolPeriod,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoDwnl,
        \Praxigento\BonusBase\Repo\Entity\Calculation $repoCalc,
        \Praxigento\Accounting\Service\Balance\Get\ITurnover $callBalanceGetTurnover,
        \Praxigento\BonusBase\Service\IPeriod $callPeriod,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\Calc $subCalc,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\GetDownline $subGetDownline,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\GetRanks $subGetRanks,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\CleanCalcData $procCleanCalcData
    )
    {
        parent::__construct($logger, $manObj);
        $this->toolPeriod = $toolPeriod;
        $this->repoDwnl = $repoDwnl;
        $this->repoCalc = $repoCalc;
        $this->callBalanceGetTurnover = $callBalanceGetTurnover;
        $this->callPeriod = $callPeriod;
        $this->subCalc = $subCalc;
        $this->subGetDownline = $subGetDownline;
        $this->subGetRanks = $subGetRanks;
        $this->procCleanCalcData = $procCleanCalcData;
    }

    public function exec(\Praxigento\BonusHybrid\Service\Calc\Forecast\Plain\Request $req)
    {
        $result = new \Praxigento\BonusHybrid\Service\Calc\Forecast\Plain\Response();
        $this->logger->info("'Forecast Plain' calculation is started.");

        /* clean up existing forecast calculation data */
        $ctxClean = new \Flancer32\Lib\Data();
        $ctxClean->set(ProcCleanCalcData::CTX_IN_CALC_TYPE_CODE, Cfg::CODE_TYPE_CALC_FORECAST_PLAIN);
        $this->procCleanCalcData->exec($ctxClean);

        /* get calculation period (begin, end dates) */
        list($dateFrom, $dateTo) = $this->getPeriod($req);

        /* register new calculation for period */
        $calcId = $this->registerNewCalc($dateFrom, $dateTo);

        /* get customers downline for $dateTo */
        $ctx = new \Flancer32\Lib\Data();
        $ctx->set(ProcGetDownline::CTX_IN_CALC_ID, $calcId);
        $ctx->set(ProcGetDownline::CTX_IN_DATE_ON, $dateTo);
        $this->subGetDownline->exec($ctx);
        /** @var \Praxigento\BonusHybrid\Repo\Entity\Data\Downline[] $dwnlTree */
        $dwnlTree = $ctx->get(ProcGetDownline::CTX_OUT_DWNL);

        /* get the last ranks for customers (date before $dateFrom) */
        $ctxRanks = new \Flancer32\Lib\Data();
        $ctxRanks->set($this->subGetRanks::CTX_IN_DATE_ON, $dateFrom);
        $ctxRanks->set($this->subGetRanks::CTX_IO_TREE, $dwnlTree);
        $ranks = $this->subGetRanks->exec($ctxRanks);

        /* get PV turnover for period */
        $entries = $this->getPvTurnover($dateFrom, $dateTo);

        /* extract only positive turnovers */
        $positiveTurnover = [];
        /** @var \Praxigento\Accounting\Service\Balance\Get\Turnover\Data\Entry $entry */
        foreach ($entries as $entry) {
            $turnover = $entry->turnover;
            $customerId = $entry->customerId;
            if ($turnover > Cfg::DEF_ZERO) {
                $positiveTurnover[$customerId] = $entry;
                /** @var \Praxigento\BonusHybrid\Repo\Data\Entity\Actual\Downline\Plain $plainDo */
                $plainDo = $dwnlTree[$customerId];
                $plainDo->setPv($turnover);
                $rankCode = $ranks[$customerId];
                $plainDo->setRankCode($rankCode);
            }
        }

        /* perform calculation */
        $ctx = new \Flancer32\Lib\Data();
        $ctx->set(SubCalc::CTX_PLAIN_TREE, $dwnlTree);
        $this->subCalc->exec($ctx);

        /* replace actual data in repository */
        $this->saveDownline($dwnlTree);

        /* finalize calculation */
        $this->repoCalc->markComplete($calcId);

        $this->logMemoryUsage();
        $this->logger->info("'Forecast Plain' calculation is completed.");
        $result->markSucceed();
        return $result;
    }

    /**
     * Return 2 dates (period being/end): first day of the month and yesterday.
     *
     * @param \Praxigento\BonusHybrid\Service\Calc\Forecast\Plain\Request $req
     * @return array
     */
    protected function getPeriod(\Praxigento\BonusHybrid\Service\Calc\Forecast\Plain\Request $req)
    {
        $requested = $req->getPeriod();
        if ($requested) {
            /* convert $requested to MONTH period */
            $month = $this->toolPeriod->getPeriodNext($requested, \Praxigento\Core\Tool\IPeriod::TYPE_MONTH);
            $month = $this->toolPeriod->getPeriodPrev($month, \Praxigento\Core\Tool\IPeriod::TYPE_MONTH);
            /* get period end */
            $end = $this->toolPeriod->getPeriodLastDate($month);
        } else {
            /* get current month as MONTH period */
            $month = $this->toolPeriod->getPeriodCurrent(null, 0, \Praxigento\Core\Tool\IPeriod::TYPE_MONTH);
            /* get current date then get yesterday date (end of period) */
            $today = $this->toolPeriod->getPeriodCurrent();
            $end = $this->toolPeriod->getPeriodPrev($today);
        }
        $begin = $this->toolPeriod->getPeriodFirstDate($month);
        $result = [$begin, $end];
        return $result;
    }

    protected function getPvTurnover($dateFrom, $dateTo)
    {
        $reqTurnover = new \Praxigento\Accounting\Service\Balance\Get\Turnover\Request ();
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
    protected function registerNewCalc($from, $to)
    {
        $req = new \Praxigento\BonusBase\Service\Period\Request\RegisterPeriod();
        $req->setDateStampBegin($from);
        $req->setDateStampEnd($to);
        $req->setCalcTypeCode(Cfg::CODE_TYPE_CALC_FORECAST_PLAIN);
        $resp = $this->callPeriod->registerPeriod($req);
        $calcData = $resp->getCalcData();
        $result = $calcData->getId();
        return $result;
    }

    protected function saveDownline($items)
    {
        foreach ($items as $item) {
            $this->repoDwnl->create($item);
        }
    }
}