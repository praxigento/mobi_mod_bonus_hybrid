<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Unqualified;

use Praxigento\BonusBase\Service\Period\Calc\Get\IDependent as SPeriodGetDep;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;

/**
 * Collect stats for unqualified customers.
 *
 * This is internal service (for this module only), so it has no own interface.
 */
class Collect
    implements \Praxigento\Core\Api\App\Service\Process
{
    /** @var \Praxigento\Downline\Api\Helper\Tree */
    private $hlpTree;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent */
    private $procPeriodGet;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $daoBonDwnl;
    /** @var \Praxigento\BonusBase\Repo\Dao\Calculation */
    private $daoCalc;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Downline\Api\Helper\Tree $hlpTree,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoBonDwnl,
        \Praxigento\BonusBase\Repo\Dao\Calculation $daoCalc,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet
    )
    {
        $this->logger = $logger;
        $this->hlpTree = $hlpTree;
        $this->daoBonDwnl = $daoBonDwnl;
        $this->daoCalc = $daoCalc;
        $this->procPeriodGet = $procPeriodGet;
    }

    /**
     * Collect unqualified statistics and update current plain tree (corresponds to PV Write Off calculation).
     *
     * @param EBonDwnl[] $treePlain this data will be updated with new values for unqualified months.
     * @param EBonDwnl[] $treePlainPrev
     * @param EBonDwnl[] $treePhase1
     */
    private function calc(&$treePlain, $treePlainPrev, $treePhase1)
    {
        /* map inactive statistics by customer ID */
        $mapMonths = $this->hlpTree->mapValueById($treePlainPrev, EBonDwnl::A_CUST_REF, EBonDwnl::A_UNQ_MONTHS);
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
        $treePlainPrev = [];
        if ($writeOffCalcPrev) {
            $writeOffCalcIdPrev = $writeOffCalcPrev->getId();
            $treePlainPrev = $this->daoBonDwnl->getByCalcId($writeOffCalcIdPrev);
        }
        /* $treePlain will be populated with new values for unqualified months */
        $this->calc($treePlain, $treePlainPrev, $treePhase1);
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
     */
    private function getCalcData()
    {
        /**
         * Get PW Write Off data & Phase1 Compression data - to access plain tree & qualified customers data.
         */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(SPeriodGetDep::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $ctx->set(SPeriodGetDep::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1);
        $ctx->set(SPeriodGetDep::CTX_IN_DEP_IGNORE_COMPLETE, true);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $writeOffCalc */
        $writeOffCalc = $ctx->get(SPeriodGetDep::CTX_OUT_BASE_CALC_DATA);
        $pwWriteOffPeriod = $ctx->get(SPeriodGetDep::CTX_OUT_BASE_PERIOD_DATA);
        $phase1Calc = $ctx->get(SPeriodGetDep::CTX_OUT_DEP_CALC_DATA);
        /**
         * Create Unqualified Collection calculation.
         */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(SPeriodGetDep::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1);
        $ctx->set(SPeriodGetDep::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_UNQUALIFIED_COLLECT);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $pwWriteOffCalc */
        $unqCollCalc = $ctx->get(SPeriodGetDep::CTX_OUT_DEP_CALC_DATA);
        /**
         * Get previous PV Write Off data to access stats history.
         */
        $periodPrev = $pwWriteOffPeriod->getDstampBegin();
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(SPeriodGetDep::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $ctx->set(SPeriodGetDep::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1);
        $ctx->set(SPeriodGetDep::CTX_IN_PERIOD_END, $periodPrev);
        $ctx->set(SPeriodGetDep::CTX_IN_DEP_IGNORE_COMPLETE, true);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $phase1CalcPrev */
        $writeOffCalcPrev = $ctx->get(SPeriodGetDep::CTX_OUT_BASE_CALC_DATA);
        /**
         * Compose result.
         */
        $result = [$writeOffCalc, $writeOffCalcPrev, $phase1Calc, $unqCollCalc];
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