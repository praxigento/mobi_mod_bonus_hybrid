<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast;

use Praxigento\BonusBase\Api\Service\Period\Calc\Get\All\Request as AGetPeriodRequest;
use Praxigento\BonusBase\Api\Service\Period\Calc\Get\All\Response as AGetPeriodResponse;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;
use Praxigento\BonusHybrid\Repo\Data\Downline\Inactive as EInact;
use Praxigento\BonusHybrid\Repo\Query\GetInactive as QGetInact;
use Praxigento\BonusHybrid\Service\Calc\Forecast\Unqualified\Request as ARequest;
use Praxigento\BonusHybrid\Service\Calc\Forecast\Unqualified\Response as AResponse;
use Praxigento\Core\Api\Helper\Period as HPeriod;

/**
 * Unqualified customers forecast calc for the given period (not closed).
 */
class Unqualified
{
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $daoBonDwnl;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline\Inactive */
    private $daoInact;
    /** @var \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusHybrid\Repo\Query\GetInactive */
    private $qGetInact;
    /** @var \Praxigento\BonusBase\Api\Service\Period\Calc\Get\All */
    private $servPeriodGet;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoBonDwnl,
        \Praxigento\BonusHybrid\Repo\Dao\Downline\Inactive $daoInact,
        \Praxigento\BonusHybrid\Repo\Query\GetInactive $qGetInact,
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        \Praxigento\BonusBase\Api\Service\Period\Calc\Get\All $servPeriodGet
    ) {
        $this->logger = $logger;
        $this->daoBonDwnl = $daoBonDwnl;
        $this->daoInact = $daoInact;
        $this->qGetInact = $qGetInact;
        $this->hlpPeriod = $hlpPeriod;
        $this->servPeriodGet = $servPeriodGet;
    }

    /**
     * Process current period downline (forecast) and compare with inactive data from previous period
     * (PV write off or forecast).
     *
     * @param array $prev
     * @param EBonDwnl[] $current
     * @return array
     */
    private function composeInactive($prev, $current)
    {
        $result = [];
        foreach ($current as $one) {
            $custId = $one->getCustomerRef();
            $treeEntryId = $one->getId();
            $pv = $one->getPv();
            if ($pv <= 0) {
                if (isset($prev[$custId])) {
                    $months = $prev[$custId][QGetInact::A_MONTHS] + 1;
                } else {
                    $months = 1;
                }
                /* this customer is inactive in the current month */
                $entry = new EInact();
                $entry->setTreeEntryRef($treeEntryId);
                $entry->setInactMonths($months);
                $result[] = $entry;
            }
        }
        return $result;
    }

    /**
     * @param ARequest $request
     * @return AResponse
     * @throws \Exception
     */
    public function exec($request)
    {
        assert($request instanceof ARequest);
        /** define local working data */
        $period = $request->getPeriod();
        $this->logger->info("Forecast Unqualified calculation is started for period $period.");

        /** perform processing */
        list($prevCalcData, $currCalcData) = $this->getCalcData($period);
        $prevCalcId = $prevCalcData->getId();
        $currCalcId = $currCalcData->getId();

        $inactPrev = $this->getInactivePrev($prevCalcId);
        $treeCurr = $this->daoBonDwnl->getByCalcId($currCalcId);
        $inactCurr = $this->composeInactive($inactPrev, $treeCurr);

        $this->saveInactiveCurr($inactCurr);

        $this->logger->info("Forecast Unqualified calculation is completed.");

        $result = new AResponse();
        $result->markSucceed();
        return $result;
    }

    /**
     * Get calculation data for previous period (PV Write Off or Forecast Plain)
     * and for current period (Forecast Plain).
     *
     * @param string $period YYYYMM
     * @return array [$prevCalcData, $currentCalcData]
     * @throws \Exception
     */
    private function getCalcData($period)
    {
        /**
         * Get previous period data (forecast or PV write off).
         */
        $prev = $this->hlpPeriod->getPeriodPrev($period, HPeriod::TYPE_MONTH);
        $req = new AGetPeriodRequest();
        $req->setPeriod($prev);
        /** @var AGetPeriodResponse $resp */
        $resp = $this->servPeriodGet->exec($req);
        $calcs = $resp->getCalcs();
        if (isset($calcs[Cfg::CODE_TYPE_CALC_FORECAST_PLAIN])) {
            $calcData = $calcs[Cfg::CODE_TYPE_CALC_FORECAST_PLAIN];
        } else {
            $calcData = $calcs[Cfg::CODE_TYPE_CALC_PV_WRITE_OFF];
        }
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $prevCalcData */
        $prevCalcData = $calcData;
        /**
         * Get current period data (forecast).
         */
        $req = new AGetPeriodRequest();
        $req->setPeriod($period);
        /** @var AGetPeriodResponse $resp */
        $resp = $this->servPeriodGet->exec($req);
        $calcs = $resp->getCalcs();
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $currentCalcData */
        $currentCalcData = $calcs[Cfg::CODE_TYPE_CALC_FORECAST_PLAIN];
        /**
         * Compose result.
         */
        $result = [$prevCalcData, $currentCalcData];
        return $result;
    }

    /**
     * Get inactivity statistics for previous period.
     *
     * @param int $calcId
     * @return array
     */
    private function getInactivePrev($calcId)
    {
        $result = [];

        $query = $this->qGetInact->build();
        $conn = $query->getConnection();
        $bind = [
            QGetInact::BND_CALC_ID => $calcId
        ];
        $rs = $conn->fetchAll($query, $bind);
        foreach ($rs as $one) {
            $custId = $one[QGetInact::A_CUST_REF];
            $result[$custId] = $one;
        }
        return $result;
    }

    /**
     * @param EInact[] $items
     */
    private function saveInactiveCurr($items)
    {
        foreach ($items as $item) {
            $this->daoInact->create($item);
        }
    }
}