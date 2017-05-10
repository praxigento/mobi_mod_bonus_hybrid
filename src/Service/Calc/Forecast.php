<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\Forecast\Calc as SubCalc;
use Praxigento\BonusHybrid\Service\Calc\Forecast\GetDownline as SubGetDownline;

class Forecast
    extends \Praxigento\Core\Service\Base\Call
    implements \Praxigento\BonusHybrid\Service\Calc\IForecast
{
    protected $callBalanceGetTurnover;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Cache\Downline\IPlain */
    protected $repoCacheDwnlPlain;
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
        \Praxigento\BonusHybrid\Repo\Entity\Cache\Downline\IPlain $repoCacheDwnlPlain,
        \Praxigento\Accounting\Service\Balance\Get\ITurnover $callBalanceGetTurnover,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\Calc $subCalc,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\GetDownline $subGetDownline,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\GetRanks $subGetRanks
    ) {
        parent::__construct($logger, $manObj);
        $this->toolPeriod = $toolPeriod;
        $this->repoCacheDwnlPlain = $repoCacheDwnlPlain;
        $this->callBalanceGetTurnover = $callBalanceGetTurnover;
        $this->subCalc = $subCalc;
        $this->subGetDownline = $subGetDownline;
        $this->subGetRanks = $subGetRanks;
    }

    protected function cleanCachedData()
    {
        $this->repoCacheDwnlPlain->delete();
    }

    public function exec(\Praxigento\BonusHybrid\Service\Calc\Forecast\Request $req)
    {
        $result = new \Praxigento\BonusHybrid\Service\Calc\Forecast\Response();
        $this->_logger->info("'Forecast' calculation is started.");

        /* get calculation period (begin, end dates) */
        list($dateFrom, $dateTo) = $this->getPeriod();

        /* get customers */
        $ctx = new \Flancer32\Lib\Data();
        $ctx->set(SubGetDownline::CTX_DATE_ON, $dateTo);
        /** @var \Praxigento\BonusHybrid\Entity\Cache\Downline\Plain[] $plainItems */
        $plainItems = $this->subGetDownline->exec($ctx);

        /* get the last ranks for customers */
        $ranks = $this->subGetRanks->exec();

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
                /** @var \Praxigento\BonusHybrid\Entity\Cache\Downline\Plain $plainDo */
                $plainDo = $plainItems[$customerId];
                $plainDo->setPv($turnover);
                $rankCode = $ranks[$customerId];
                $plainDo->setRankCode($rankCode);
            }
        }

        /* perform calculation */
        $ctx = new \Flancer32\Lib\Data();
        $ctx->set(SubCalc::CTX_PLAIN_TREE, $plainItems);
        $this->subCalc->exec($ctx);

        /* replace actual data in repository */
        $this->cleanCachedData();
        $this->saveDwnlPlain($plainItems);

        $this->_logMemoryUsage();
        $this->_logger->info("'Forecast' calculation is completed.");
        return $result;
    }

    /**
     * Return 2 dates (period being/end): first day of the month and yesterday.
     * @return array
     */
    protected function getPeriod()
    {
        $today = $this->toolPeriod->getPeriodCurrent();
        $yesterday = $this->toolPeriod->getPeriodPrev($today);
        $month = $this->toolPeriod->getPeriodCurrent(null, 0, \Praxigento\Core\Tool\IPeriod::TYPE_MONTH);
        $begin = $this->toolPeriod->getPeriodFirstDate($month);
        $end = $yesterday;

        /* TODO: remove it */
//        $begin = '20170301';
//        $end = '20170310';

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
    protected function saveDwnlPlain($items)
    {
        foreach ($items as $item) {
            $this->repoCacheDwnlPlain->create($item);
        }
    }
}