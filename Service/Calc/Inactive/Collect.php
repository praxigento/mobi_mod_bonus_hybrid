<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Inactive;

use Praxigento\BonusBase\Service\Period\Calc\Get\IDependent as SPeriodGetDep;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EBonDwnl;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline\Inactive as EInact;
use Praxigento\BonusHybrid\Service\Calc\Inactive\Collect\Repo\Query\GetInactiveStats as QBGetStats;

/**
 * Collect customer inactivity stats.
 *
 * This is internal service (for this module only), so it has no own interface.
 */
class Collect
    implements \Praxigento\Core\App\Service\IProcess
{
    /** Maximal end of base period to get data for (TODO: not used in regular activity, just for development/phpUnits) */
    const CTX_IN_PERIOD_END = 'in.periodEnd';
    /** @var \Praxigento\Downline\Helper\Tree */
    private $hlpTree;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent */
    private $procPeriodGet;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Inactive\Collect\Repo\Query\GetInactiveStats */
    private $qbGetStats;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoBonDwnl;
    /** @var \Praxigento\BonusBase\Repo\Entity\Calculation */
    private $repoCalc;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline\Inactive */
    private $repoInact;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Downline\Helper\Tree $hlpTree,
        \Praxigento\BonusBase\Repo\Entity\Calculation $repoCalc,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoBonDwnl,
        \Praxigento\BonusHybrid\Repo\Entity\Downline\Inactive $repoInact,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet,
        QBGetStats $qbGetStats
    )
    {
        $this->logger = $logger;
        $this->hlpTree = $hlpTree;
        $this->repoCalc = $repoCalc;
        $this->repoBonDwnl = $repoBonDwnl;
        $this->repoInact = $repoInact;
        $this->procPeriodGet = $procPeriodGet;
        $this->qbGetStats = $qbGetStats;
    }

    /**
     * @param EBonDwnl[] $tree
     * @param $prevStat
     * @return array
     */
    private function calc($tree, $prevStat)
    {
        $result = [];
        /* map inactive statistics by customer ID */
        $mapMonths = $this->hlpTree->mapValueById($prevStat, QBGetStats::A_CUST_REF, QBGetStats::A_MONTHS_INACT);
        foreach ($tree as $item) {
            $pv = $item->getPv();
            if ($pv < Cfg::DEF_ZERO) {
                /* this customer is inactive in this period */
                $custId = $item->getCustomerRef();
                $treeEntryId = $item->getId();
                if (isset($mapMonths[$custId])) {
                    $prevMonths = $mapMonths[$custId];
                    $months = $prevMonths + 1;
                } else {
                    $months = 1;
                }
                $inactItem = new EInact();
                $inactItem->setTreeEntryRef($treeEntryId);
                $inactItem->setInactMonths($months);
                $result[] = $inactItem;
            }
        }
        return $result;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        $this->logger->info("Inactive Stats Collection calculation is started.");
        /**
         * perform processing
         */
        $ctx->set(self::CTX_OUT_SUCCESS, false);
        /* get dependent calculation data */
        list($writeOffCalc, $writeOffCalcPrev, $collectCalc) = $this->getCalcData();
        $writeOffCalcId = $writeOffCalc->getId();
        $tree = $this->repoBonDwnl->getByCalcId($writeOffCalcId);
        $prevStat = [];
        if ($writeOffCalcPrev) {
            $writeOffCalcIdPrev = $writeOffCalcPrev->getId();
            $prevStat = $this->getPreviousStats($writeOffCalcIdPrev);
        }
        $stats = $this->calc($tree, $prevStat);
        $this->saveStats($stats);
        /* mark this calculation complete */
        $calcId = $collectCalc->getId();
        $this->repoCalc->markComplete($calcId);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("Inactive Stats Collection calculation is completed.");
    }

    /**
     * Get related calculations data for this calculation.
     *
     * @return array [$writeOffCalc, $writeOffCalcPrev, $collectCalc]
     */
    private function getCalcData()
    {
        /* get period & calc data */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(SPeriodGetDep::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $ctx->set(SPeriodGetDep::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_INACTIVE_COLLECT);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Period $writeOffPeriod */
        $writeOffPeriod = $ctx->get(SPeriodGetDep::CTX_OUT_BASE_PERIOD_DATA);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $writeOffCalc */
        $writeOffCalc = $ctx->get(SPeriodGetDep::CTX_OUT_BASE_CALC_DATA);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $collectCalc */
        $collectCalc = $ctx->get(SPeriodGetDep::CTX_OUT_DEP_CALC_DATA);
        /**
         * Get previous write off period to access inactive stats history.
         */
        $periodPrev = $writeOffPeriod->getDstampBegin();
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(SPeriodGetDep::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $ctx->set(SPeriodGetDep::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_INACTIVE_COLLECT);
        $ctx->set(SPeriodGetDep::CTX_IN_PERIOD_END, $periodPrev);
        $ctx->set(SPeriodGetDep::CTX_IN_DEP_IGNORE_COMPLETE, true);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $writeOffCalcPrev */
        $writeOffCalcPrev = $ctx->get(SPeriodGetDep::CTX_OUT_BASE_CALC_DATA);
        /**
         * Compose result.
         */
        $result = [$writeOffCalc, $writeOffCalcPrev, $collectCalc];
        return $result;
    }

    /**
     * @param int $calcId previous Write Off Calculation ID
     * @return array
     */
    private function getPreviousStats($calcId)
    {
        $query = $this->qbGetStats->build();
        $conn = $query->getConnection();
        $bind = [
            QBGetStats::BND_CALC_REF => $calcId
        ];
        $result = $conn->fetchAll($query, $bind);
        return $result;
    }

    /**
     * @param \Praxigento\BonusHybrid\Repo\Entity\Data\Downline\Inactive[] $stats
     */
    private function saveStats($stats)
    {
        /** @var \Praxigento\BonusHybrid\Repo\Entity\Data\Downline\Inactive $stat */
        foreach ($stats as $stat) {
            $this->repoInact->create($stat);
        }
    }
}