<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Unqualified;

use Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Request as AGetPeriodRequest;
use Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Response as AGetPeriodResponse;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;
use Praxigento\BonusHybrid\Repo\Data\Downline\Inactive as EBonInact;
use Praxigento\BonusHybrid\Repo\Query\GetInactive as QGetInact;

/**
 * Collect stats for unqualified customers.
 *
 * This is internal service (for this module only), so it has no own interface.
 */
class Collect
    implements \Praxigento\Core\Api\App\Service\Process
{
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $daoBonDwnl;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline\Inactive */
    private $daoBonInact;
    /** @var \Praxigento\BonusBase\Repo\Dao\Calculation */
    private $daoCalc;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusHybrid\Repo\Query\GetInactive */
    private $qGetInact;
    /** @var \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent */
    private $servPeriodGet;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\BonusBase\Repo\Dao\Calculation $daoCalc,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoBonDwnl,
        \Praxigento\BonusHybrid\Repo\Dao\Downline\Inactive $daoBonInact,
        \Praxigento\BonusHybrid\Repo\Query\GetInactive $qGetInact,
        \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent $servPeriodGet
    )
    {
        $this->logger = $logger;
        $this->daoCalc = $daoCalc;
        $this->daoBonDwnl = $daoBonDwnl;
        $this->daoBonInact = $daoBonInact;
        $this->qGetInact = $qGetInact;
        $this->servPeriodGet = $servPeriodGet;
    }

    /**
     * Collect inactive statistics for current period.
     *
     * @param array $inactPrev [custId => monthsInact]
     * @param EBonDwnl[] $treePlain PV Write Off calculation tree.
     * @return EBonInact[]
     * @throws \Exception
     */
    private function calc($inactPrev, $treePlain)
    {
        $result = [];
        foreach ($treePlain as $item) {
            $custId = $item->getCustomerRef();
            $pv = $item->getPv();
            if ($pv <= Cfg::DEF_ZERO) {
                /* the customer is not active in the current period */
                if (isset($inactPrev[$custId])) {
                    $months = $inactPrev[$custId] + 1;
                } else {
                    $months = 1;
                }
                $entry = new EBonInact();
                $entryId = $item->getId();
                $entry->setTreeEntryRef($entryId);
                $entry->setInactMonths($months);
                $result[] = $entry;
            }
        }
        return $result;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        $this->logger->info("'Inactive Stats Collection' calculation is started.");
        /**
         * perform processing
         */
        $ctx->set(self::CTX_OUT_SUCCESS, false);
        /* get dependent calculation data */
        list($writeOffCalc, $writeOffCalcPrev, $phase1Calc, $unqCollCalc) = $this->getCalcData();
        $writeOffCalcId = $writeOffCalc->getId();
        $phase1CalcId = $phase1Calc->getId();
        $treePlain = $this->daoBonDwnl->getByCalcId($writeOffCalcId);
        $treePhase1 = $this->daoBonDwnl->getByCalcId($phase1CalcId);
        $inactPrev = [];
        if ($writeOffCalcPrev) {
            $writeOffCalcIdPrev = $writeOffCalcPrev->getId();
            $inactPrev = $this->getPrevInactStats($writeOffCalcIdPrev);
        }
        /* $treePlain will be populated with new values for unqualified months */
        $inactCurr = $this->calc($inactPrev, $treePlain);
        $this->saveInactive($inactCurr);
        /* mark this calculation complete */
        $calcId = $unqCollCalc->getId();
        $this->daoCalc->markComplete($calcId);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("'Inactive Stats Collection' calculation is completed.");
    }

    /**
     * Get data for periods & calculations.
     *
     * @return array [$writeOffCalc, $writeOffCalcPrev, $phase1Calc, $unqCollCalc]
     * @throws \Exception
     */
    private function getCalcData()
    {
        /**
         * Get PW Write Off data & Phase1 Compression data - to access plain tree & qualified customers data.
         */
        $req = new AGetPeriodRequest();
        $req->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $req->setDepCalcTypeCode(Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1);
        $req->setDepIgnoreComplete(true);
        /** @var AGetPeriodResponse $resp */
        $resp = $this->servPeriodGet->exec($req);
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $writeOffCalc */
        $writeOffCalc = $resp->getBaseCalcData();
        $pwWriteOffPeriod = $resp->getBasePeriodData();
        $phase1Calc = $resp->getDepCalcData();
        /**
         * Create Unqualified Collection calculation.
         */
        $req = new AGetPeriodRequest();
        $req->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1);
        $req->setDepCalcTypeCode(Cfg::CODE_TYPE_CALC_UNQUALIFIED_COLLECT);
        /** @var AGetPeriodResponse $resp */
        $resp = $this->servPeriodGet->exec($req);
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $unqCollCalc */
        $unqCollCalc = $resp->getDepCalcData();
        /**
         * Get previous PV Write Off data to access stats history.
         */
        $periodPrev = $pwWriteOffPeriod->getDstampBegin();
        $req = new AGetPeriodRequest();
        $req->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $req->setDepCalcTypeCode(Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1);
        $req->setPeriodEnd($periodPrev);
        $req->setDepIgnoreComplete(true);
        /** @var AGetPeriodResponse $resp */
        $resp = $this->servPeriodGet->exec($req);
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $phase1CalcPrev */
        $writeOffCalcPrev = $resp->getBaseCalcData();
        /**
         * Compose result.
         */
        $result = [$writeOffCalc, $writeOffCalcPrev, $phase1Calc, $unqCollCalc];
        return $result;
    }

    /**
     * @param int $calcId
     * @return array [custId=>months]
     */
    private function getPrevInactStats($calcId)
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
            $months = $one[QGetInact::A_MONTHS];
            $result[$custId] = $months;
        }
        return $result;
    }

    /**
     * @param EBonInact[] $items
     */
    private function saveInactive($items)
    {
        foreach ($items as $one) {
            $this->daoBonInact->create($one);
        }
    }
}