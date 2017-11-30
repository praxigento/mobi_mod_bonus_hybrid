<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Inactive;

use Praxigento\BonusBase\Service\Period\Calc\Get\IDependent as SPeriodGetDep;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EBonDwnl;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline\Inactive as EInact;

/**
 * Collect customer inactivity stats.
 *
 * This is internal service (for this module only), so it has no own interface.
 */
class Collect
    implements \Praxigento\Core\Service\IProcess
{
    /** Maximal end of base period to get data for */
    const CTX_IN_PERIOD_END = 'in.periodEnd';

    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent */
    private $procPeriodGet;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoBonDwnl;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline\Inactive */
    private $repoInact;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoBonDwnl,
        \Praxigento\BonusHybrid\Repo\Entity\Downline\Inactive $repoInact,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet
    )
    {
        $this->logger = $logger;
        $this->repoBonDwnl = $repoBonDwnl;
        $this->repoInact = $repoInact;
        $this->procPeriodGet = $procPeriodGet;
    }

    /**
     * @param EBonDwnl[] $tree
     * @param $prevStat
     * @return array
     */
    private function calc($tree, $prevStat)
    {
        $result = [];
        /* TODO: map previous stats by customer ID */
        foreach ($tree as $item) {
            $pv = $item->getPv();
            if ($pv < Cfg::DEF_ZERO) {
                /* this customer is inactive in this period */
                $custId = $item->getCustomerRef();
                $treeEntryId = $item->getId();
                $months = 1;
                if (isset($prevStat[$custId])) {
                    $prevItem = $prevStat[$custId];
                    $months = $prevItem['months'] + 1;
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
        $periodEnd = $ctx->get(self::CTX_IN_PERIOD_END);
        /**
         * perform processing
         */
        $ctx->set(self::CTX_OUT_SUCCESS, false);
        /* get dependent calculation data */
        list($writeOffPeriod, $writeOffCalc, $collectCalc) = $this->getCalcData($periodEnd);
        $calcId = $writeOffCalc->getId();
        $tree = $this->repoBonDwnl->getByCalcId($calcId);
        $prevStat = [];
        $this->calc($tree, $prevStat);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("Inactive Stats Collection calculation is completed.");
    }

    /**
     * Get data for dependent calculation.
     *
     * @return array [$periodData, $calcData]
     */
    private function getCalcData($maxPeriodEnd)
    {
        /* get period & calc data */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(SPeriodGetDep::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $ctx->set(SPeriodGetDep::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_INACTIVE_COLLECT);
        $ctx->set(SPeriodGetDep::CTX_IN_PERIOD_END, $maxPeriodEnd);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Period $writeOffPeriod */
        $writeOffPeriod = $ctx->get(SPeriodGetDep::CTX_OUT_BASE_PERIOD_DATA);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $collectCalc */
        $writeOffCalc = $ctx->get(SPeriodGetDep::CTX_OUT_BASE_CALC_DATA);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $collectCalc */
        $collectCalc = $ctx->get(SPeriodGetDep::CTX_OUT_DEP_CALC_DATA);
        $result = [$writeOffPeriod, $writeOffCalc, $collectCalc];
        return $result;
    }
}