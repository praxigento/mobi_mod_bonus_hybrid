<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\Aggregate\A\Repo\Query\GetBonusTotals as QBGetTotals;
use Praxigento\BonusHybrid\Service\Calc\Aggregate\Request as ARequest;
use Praxigento\BonusHybrid\Service\Calc\Aggregate\Response as AResponse;

/**
 * Aggregate all bonus payments (BONUS asset) as one payment (WALLET asset).
 */
class Aggregate
{
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Aggregate\A\Repo\Query\GetBonusTotals */
    private $qbGetBonusTotals;
    /** @var \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent */
    private $servPeriodGet;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent $servPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\Aggregate\A\Repo\Query\GetBonusTotals $qbGetBonusTotals
    ) {
        $this->logger = $logger;
        $this->servPeriodGet = $servPeriodGet;
        $this->qbGetBonusTotals = $qbGetBonusTotals;
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

        $totals = $this->getBonusTotals($dsBegin, $dsEnd);

        /** compose result */
        $this->logger->info("Bonus aggregation calculation is completed.");
        $result = new AResponse();
        return $result;
    }


    private function getBonusTotals($dsBegin, $dsEnd)
    {
        $query = $this->qbGetBonusTotals->build();
        $conn = $query->getConnection();
        $bind = [
            QBGetTotals::BND_PERIOD_BEGIN => $dsBegin,
            QBGetTotals::BND_PERIOD_END => $dsEnd
        ];
        $rs = $conn->fetchAll($query, $bind);
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
}