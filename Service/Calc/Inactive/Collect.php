<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Inactive;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;
use Praxigento\BonusHybrid\Repo\Data\Downline\Inactive as EInact;
use Praxigento\BonusHybrid\Service\Calc\Inactive\Collect\Repo\Query\GetInactiveStats as QBGetStats;

/**
 * Collect customer inactivity stats.
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
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline\Inactive */
    private $daoInact;
    /** @var \Praxigento\BonusHybrid\Api\Helper\Scheme */
    private $hlpScheme;
    /** @var \Praxigento\Downline\Api\Helper\Tree */
    private $hlpTree;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Inactive\Collect\Repo\Query\GetInactiveStats */
    private $qbGetStats;
    /** @var \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent */
    private $servGetDepend;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\BonusBase\Repo\Dao\Calculation $daoCalc,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoBonDwnl,
        \Praxigento\BonusHybrid\Repo\Dao\Downline\Inactive $daoInact,
        \Praxigento\Downline\Api\Helper\Tree $hlpTree,
        \Praxigento\BonusHybrid\Api\Helper\Scheme $hlpScheme,
        \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent $servGetDepend,
        QBGetStats $qbGetStats
    )
    {
        $this->logger = $logger;
        $this->daoCalc = $daoCalc;
        $this->daoBonDwnl = $daoBonDwnl;
        $this->daoInact = $daoInact;
        $this->hlpTree = $hlpTree;
        $this->hlpScheme = $hlpScheme;
        $this->servGetDepend = $servGetDepend;
        $this->qbGetStats = $qbGetStats;
    }

    /**
     * @param EBonDwnl[] $tree
     * @param $prevStat
     * @return array
     * @throws \Exception
     */
    private function calc($tree, $prevStat)
    {
        $result = [];
        /* get customers with forced qualification */
        $forced = $this->hlpScheme->getForcedQualificationCustomersIds();
        /* map inactive statistics by customer ID */
        $mapMonths = $this->hlpTree->mapValueById($prevStat, QBGetStats::A_CUST_REF, QBGetStats::A_MONTHS_INACT);
        foreach ($tree as $item) {
            $pv = $item->getPv();
            if ($pv < Cfg::DEF_ZERO) {
                /* this customer is inactive in this period */
                $custId = $item->getCustomerRef();
                /* skip customers with forced qualification */
                if (in_array($custId, $forced)) continue;
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
        $tree = $this->daoBonDwnl->getByCalcId($writeOffCalcId);
        $prevStat = [];
        if ($writeOffCalcPrev) {
            $writeOffCalcIdPrev = $writeOffCalcPrev->getId();
            $prevStat = $this->getPreviousStats($writeOffCalcIdPrev);
        }
        $stats = $this->calc($tree, $prevStat);
        $this->saveStats($stats);
        /* mark this calculation complete */
        $calcId = $collectCalc->getId();
        $this->daoCalc->markComplete($calcId);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("Inactive Stats Collection calculation is completed.");
    }

    /**
     * Get related calculations data for this calculation.
     *
     * @return array [$writeOffCalc, $writeOffCalcPrev, $collectCalc]
     * @throws \Exception
     */
    private function getCalcData()
    {
        /* get period & calc data */
        $req = new \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Request();
        $req->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $req->setDepCalcTypeCode(Cfg::CODE_TYPE_CALC_INACTIVE_COLLECT);
        $resp = $this->servGetDepend->exec($req);
        /** @var \Praxigento\BonusBase\Repo\Data\Period $writeOffPeriod */
        $writeOffPeriod = $resp->getBasePeriodData();
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $writeOffCalc */
        $writeOffCalc = $resp->getBaseCalcData();
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $collectCalc */
        $collectCalc = $resp->getDepCalcData();
        /**
         * Get previous write off period to access inactive stats history.
         */
        $periodPrev = $writeOffPeriod->getDstampBegin();
        $req = new \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Request();
        $req->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $req->setDepCalcTypeCode(Cfg::CODE_TYPE_CALC_INACTIVE_COLLECT);
        $req->setPeriodEnd($periodPrev);
        $req->setDepIgnoreComplete(true);
        $resp = $this->servGetDepend->exec($req);
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $writeOffCalcPrev */
        $writeOffCalcPrev = $resp->getBaseCalcData();
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
     * @param \Praxigento\BonusHybrid\Repo\Data\Downline\Inactive[] $stats
     */
    private function saveStats($stats)
    {
        /** @var \Praxigento\BonusHybrid\Repo\Data\Downline\Inactive $stat */
        foreach ($stats as $stat) {
            $this->daoInact->create($stat);
        }
    }
}