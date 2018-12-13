<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Unqualified;

use Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Request as AGetPeriodRequest;
use Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Response as AGetPeriodResponse;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;
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
    /** @var \Praxigento\BonusBase\Repo\Dao\Calculation */
    private $daoCalc;
    /** @var \Praxigento\Downline\Api\Helper\Tree */
    private $hlpTree;
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
        \Praxigento\BonusHybrid\Repo\Query\GetInactive $qGetInact,
        \Praxigento\Downline\Api\Helper\Tree $hlpTree,
        \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent $servPeriodGet
    )
    {
        $this->logger = $logger;
        $this->daoCalc = $daoCalc;
        $this->daoBonDwnl = $daoBonDwnl;
        $this->qGetInact = $qGetInact;
        $this->hlpTree = $hlpTree;
        $this->servPeriodGet = $servPeriodGet;
    }

    /**
     * Collect unqualified statistics and update current plain tree (corresponds to PV Write Off calculation).
     *
     * @param EBonDwnl[] $treePlain this data will be updated with new values for unqualified months.
     * @param array $inactPrev [custId => monthsInact]
     * @param EBonDwnl[] $treePhase1
     */
    private function calc(&$treePlain, $inactPrev, $treePhase1)
    {
        /* map inactive statistics by customer ID */
        $mapMonths = $inactPrev;
        $mapQual = $this->hlpTree->mapValueById($treePhase1, EBonDwnl::A_CUST_REF, EBonDwnl::A_RANK_REF);
        foreach ($treePlain as $item) {
            $custId = $item->getCustomerRef();
            if (isset($mapQual[$custId])) {
                /* this customer is qualified in this period, reset counter */
                $item->setUnqMonths(0);
            } else {
                /* increment unqualified months counter */
                $months = $mapMonths[$custId] ?? 0;
                $months++;
                $item->setUnqMonths($months);
            }
        }
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        $this->logger->info("'Unqualified Stats Collection' calculation is started.");
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
        $this->calc($treePlain, $inactPrev, $treePhase1);
        $this->saveDownline($treePlain);
        /* mark this calculation complete */
        $calcId = $unqCollCalc->getId();
        $this->daoCalc->markComplete($calcId);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("'Unqualified Stats Collection' calculation is completed.");
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
     * @param \Praxigento\BonusHybrid\Repo\Data\Downline[] $tree
     */
    private function saveDownline($tree)
    {
        /** @var \Praxigento\BonusHybrid\Repo\Data\Downline $one */
        foreach ($tree as $one) {
            $id = $one->getId();
            $this->daoBonDwnl->updateById($id, $one);
        }
    }
}