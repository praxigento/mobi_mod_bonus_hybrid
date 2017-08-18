<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\Forecast\Calc as SubCalc;
use Praxigento\BonusHybrid\Service\Calc\Forecast\GetDownline as ProcGetDownline;

class Plain
    extends \Praxigento\Core\Service\Base\Call
    implements \Praxigento\BonusHybrid\Service\Calc\Forecast\IPlain
{
    /** @var \Praxigento\Accounting\Service\Balance\Get\ITurnover */
    protected $callBalanceGetTurnover;
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
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\CleanCalcData */
    protected $procCleanCalcData;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\Core\Tool\IPeriod $toolPeriod,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoDwnl,
        \Praxigento\Accounting\Service\Balance\Get\ITurnover $callBalanceGetTurnover,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\Calc $subCalc,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\GetDownline $subGetDownline,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\GetRanks $subGetRanks,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\CleanCalcData $procCleanCalcData
    )
    {
        parent::__construct($logger, $manObj);
        $this->toolPeriod = $toolPeriod;
        $this->repoDwnl = $repoDwnl;
        $this->callBalanceGetTurnover = $callBalanceGetTurnover;
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
        $this->procCleanCalcData->exec($ctxClean);

        /* get calculation period (begin, end dates) */
        list($dateFrom, $dateTo) = $this->getPeriod($req);

        /* get customers downline for $dateTo */
        $ctx = new \Flancer32\Lib\Data();
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
        $this->cleanCachedData();
        $this->saveDownline($dwnlTree);

        $this->logMemoryUsage();
        $this->logger->info("'Forecast Plain' calculation is completed.");
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

    protected function saveDownline($items)
    {
        foreach ($items as $item) {
            $this->repoDwnl->create($item);
        }
    }
}