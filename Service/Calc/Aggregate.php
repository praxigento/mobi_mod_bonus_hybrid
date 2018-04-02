<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc;

use Praxigento\BonusBase\Repo\Data\Log\Opers as ELogOper;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\Aggregate\A\Data\Total as DTotal;
use Praxigento\BonusHybrid\Service\Calc\Aggregate\A\Repo\Query\GetBonusTotals as QBGetTotals;
use Praxigento\BonusHybrid\Service\Calc\Aggregate\Request as ARequest;
use Praxigento\BonusHybrid\Service\Calc\Aggregate\Response as AResponse;

/**
 * Aggregate all bonus payments (BONUS asset) as one payment (WALLET asset).
 */
class Aggregate
{
    /** @var \Praxigento\BonusBase\Repo\Dao\Log\Opers */
    private $daoLogOper;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Aggregate\A\CreateOper */
    private $ownCreateOper;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Aggregate\A\Repo\Query\GetBonusTotals */
    private $qbGetBonusTotals;
    /** @var \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent */
    private $servPeriodGet;
    /** @var \Praxigento\BonusBase\Repo\Dao\Calculation */
    private $daoCalc;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\BonusBase\Repo\Dao\Calculation $daoCalc,
        \Praxigento\BonusBase\Repo\Dao\Log\Opers $daoLogOper,
        \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent $servPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\Aggregate\A\Repo\Query\GetBonusTotals $qbGetBonusTotals,
        \Praxigento\BonusHybrid\Service\Calc\Aggregate\A\CreateOper $ownCreateOper
    ) {
        $this->logger = $logger;
        $this->daoCalc = $daoCalc;
        $this->daoLogOper = $daoLogOper;
        $this->servPeriodGet = $servPeriodGet;
        $this->qbGetBonusTotals = $qbGetBonusTotals;
        $this->ownCreateOper = $ownCreateOper;
    }

    /**
     * @param \Praxigento\BonusHybrid\Service\Calc\Aggregate\Request $request
     * @return \Praxigento\BonusHybrid\Service\Calc\Aggregate\Response
     * @throws \Exception
     */
    public function exec($request)
    {
        /** define local working data */
        assert($request instanceof ARequest);
        $this->logger->info("Bonus aggregation calculation is started.");


        /** perform processing */
        /**
         * @var \Praxigento\BonusBase\Repo\Data\Period $periodData
         * @var \Praxigento\BonusBase\Repo\Data\Calculation $calcData
         */
        list($periodData, $calcData) = $this->getCalcData();
        $dsBegin = $periodData->getDstampBegin();
        $dsEnd = $periodData->getDstampEnd();
        $calcId = $calcData->getId();
        $totals = $this->getBonusTotals($dsBegin, $dsEnd);
        $operId = $this->ownCreateOper->exec($totals, $dsEnd);
        /* register operation in log */
        $this->saveLog($operId, $calcId);
        /* mark this calculation complete */
        $this->daoCalc->markComplete($calcId);
        /** compose result */
        $this->logger->info("Bonus aggregation calculation is completed.");
        $result = new AResponse();
        $result->setOperId($operId);
        $result->setErrorCode(AResponse::ERR_NO_ERROR);
        return $result;
    }

    /**
     * Get summary for all bonus credits for all operation for period.
     *
     * @param $dsBegin
     * @param $dsEnd
     * @return DTotal[]
     */
    private function getBonusTotals($dsBegin, $dsEnd)
    {
        $query = $this->qbGetBonusTotals->build();
        $conn = $query->getConnection();
        $bind = [
            QBGetTotals::BND_PERIOD_BEGIN => $dsBegin,
            QBGetTotals::BND_PERIOD_END => $dsEnd
        ];
        $rs = $conn->fetchAll($query, $bind);
        $result = [];
        foreach ($rs as $one) {
            $accId = $one[QBGetTotals::A_ACC_ID];
            $custId = $one[QBGetTotals::A_CUST_ID];
            $total = $one[QBGetTotals::A_TOTAL];
            if ($custId) {
                $item = new DTotal();
                $item->accountId = $accId;
                $item->customerId = $custId;
                $item->total = $total;
                $result[$custId] = $item;
            }
        }
        return $result;
    }

    /**
     * Get data for dependent calculation.
     *
     * @return array [$periodData, $calcData]
     * @throws \Exception
     */
    private function getCalcData()
    {
        /* get period & calc data (first calc in the chain) */
        $req = new \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Request();
        $req->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_BONUS_INFINITY_EU);
        $req->setDepCalcTypeCode(Cfg::CODE_TYPE_CALC_BONUS_AGGREGATE);
        $resp = $this->servPeriodGet->exec($req);
        /** @var \Praxigento\BonusBase\Repo\Data\Period $periodData */
        $periodData = $resp->getDepPeriodData();
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $calcData */
        $calcData = $resp->getDepCalcData();
        $result = [$periodData, $calcData];
        return $result;
    }

    /**
     * Bind operation with calculation.
     *
     * @param int $operId
     * @param int $calcId
     * @throws \Exception
     */
    private function saveLog($operId, $calcId)
    {
        $entity = new ELogOper();
        $entity->setOperId($operId);
        $entity->setCalcId($calcId);
        $this->daoLogOper->create($entity);
    }
}