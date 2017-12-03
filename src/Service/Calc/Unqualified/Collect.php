<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Unqualified;

use Praxigento\BonusBase\Service\Period\Calc\Get\IDependent as SPeriodGetDep;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EBonDwnl;

/**
 * Collect stats for unqualified customers.
 *
 * This is internal service (for this module only), so it has no own interface.
 */
class Collect
    implements \Praxigento\Core\Service\IProcess
{
    /** Maximal end of base period to get data for */
    const CTX_IN_PERIOD_END = 'in.periodEnd';
    private $hlpTree;
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent */
    private $procPeriodGet;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoBonDwnl;
    /** @var \Praxigento\BonusBase\Repo\Entity\Calculation */
    private $repoCalc;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\Downline\Helper\Tree $hlpTree,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoBonDwnl,
        \Praxigento\BonusBase\Repo\Entity\Calculation $repoCalc,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet
    )
    {
        $this->logger = $logger;
        $this->hlpTree = $hlpTree;
        $this->repoBonDwnl = $repoBonDwnl;
        $this->repoCalc = $repoCalc;
        $this->procPeriodGet = $procPeriodGet;
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
        $treePlain = $this->repoBonDwnl->getByCalcId($writeOffCalcId);
        $treePhase1 = $this->repoBonDwnl->getByCalcId($phase1CalcId);
        $treePlainPrev = [];
        if ($writeOffCalcPrev) {
            $writeOffCalcIdPrev = $writeOffCalcPrev->getId();
            $treePlainPrev = $this->repoBonDwnl->getByCalcId($writeOffCalcIdPrev);
        }
        /* $treePlain will be populated with new values for unqualified months */
        $this->calc($treePlain, $treePlainPrev, $treePhase1);
        $this->saveDownline($treePlain);
        /* mark this calculation complete */
        $calcId = $unqCollCalc->getId();
        $this->repoCalc->markComplete($calcId);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("'Unqualified Stats Collection' calculation is completed.");
    }

    /**
     * Get data for periods & calculations.
     *
     * @return array [$periodData, $calcData]
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
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $writeOffCalc */
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
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $pwWriteOffCalc */
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
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $phase1CalcPrev */
        $writeOffCalcPrev = $ctx->get(SPeriodGetDep::CTX_OUT_BASE_CALC_DATA);
        /**
         * Compose result.
         */
        $result = [$writeOffCalc, $writeOffCalcPrev, $phase1Calc, $unqCollCalc];
        return $result;
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
        $mapMonths = $this->hlpTree->mapValueById($treePlainPrev, EBonDwnl::ATTR_CALC_REF, EBonDwnl::ATTR_UNQ_MONTHS);
        $mapQual = $this->hlpTree->mapValueById($treePhase1, EBonDwnl::ATTR_CALC_REF, EBonDwnl::ATTR_RANK_REF);
        foreach ($treePlain as $item) {
            $custId = $item->getCustomerRef();
            if (isset($mapQual[$custId])) {
                /* this customer is qualified in this period, reset counter */
                $item->setUnqMonths(0);
            } else {
                /* increment unqualified months counter */
                $months = $mapMonths[$custId] ?? 1;
                $item->setUnqMonths($months);
            }
        }
    }

    /**
     * @param \Praxigento\BonusHybrid\Repo\Entity\Data\Downline[] $tree
     */
    private function saveDownline($tree)
    {
        /** @var \Praxigento\BonusHybrid\Repo\Entity\Data\Downline $one */
        foreach ($tree as $one) {
            $id = $one->getId();
            $this->repoBonDwnl->updateById($id, $one);
        }
    }
}