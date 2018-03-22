<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus\Infinity;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Cfg\Param as ECfgParam;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;
use Praxigento\Downline\Repo\Data\Customer as ECustomer;

class Calc
{
    /** @var \Praxigento\Downline\Helper\Tree */
    private $hlpDwnlTree;
    /** @var \Praxigento\Core\Api\Helper\Format */
    private $hlpFormat;
    /** @var  \Praxigento\BonusHybrid\Helper\IScheme */
    private $hlpScheme;
    /** @var \Praxigento\Downline\Api\Helper\Downline */
    private $hlpTree;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $repoBonDwnl;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Cfg\Param */
    private $repoCfgParams;
    /** @var \Praxigento\Downline\Repo\Dao\Customer */
    private $repoDwnl;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Core\Api\Helper\Format $hlpFormat,
        \Praxigento\Downline\Api\Helper\Downline $hlpTree,
        \Praxigento\Downline\Helper\Tree $hlpDwnlTree,
        \Praxigento\BonusHybrid\Helper\IScheme $hlpScheme,
        \Praxigento\Downline\Repo\Dao\Customer $repoDwnl,
        \Praxigento\BonusHybrid\Repo\Dao\Cfg\Param $repoCfgParams,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $repoBonDwnl
    )
    {
        $this->logger = $logger;
        $this->hlpFormat = $hlpFormat;
        $this->hlpTree = $hlpTree;
        $this->hlpDwnlTree = $hlpDwnlTree;
        $this->hlpScheme = $hlpScheme;
        $this->repoDwnl = $repoDwnl;
        $this->repoCfgParams = $repoCfgParams;
        $this->repoBonDwnl = $repoBonDwnl;
    }

    public function exec($compressCalcId, $ovrdCalcId, $scheme)
    {

        $result = [];

        /* collect additional data */
        $dwnlCompress = $this->repoBonDwnl->getByCalcId($compressCalcId);
        $dwnlPlain = $this->repoDwnl->get();
        $cfgParams = $this->getCfgParams();
        $ibPercentMax = $this->getMaxPercentForInfinityBonus($cfgParams, $scheme);
        /* create maps to access data */
        $mapById = $this->hlpDwnlTree->mapById($dwnlCompress, EBonDwnl::ATTR_CUST_REF);
        $mapPlainById = $this->hlpDwnlTree->mapById($dwnlPlain, ECustomer::ATTR_CUSTOMER_ID);
        /**
         * Process downline tree
         * @var EBonDwnl $custCompress
         */
        foreach ($mapById as $custId => $custCompress) {
            /** @var ECustomer $custPlain */
            $custPlain = $mapPlainById[$custId];
            $custMlmId = $custPlain->getMlmId();
            $pv = $custCompress->getPv();
            if ($pv > Cfg::DEF_ZERO) {
                $path = $custCompress->getPath();
                $parents = $this->hlpTree->getParentsFromPathReversed($path);
                $prevParentIbPercent = 0;
                $ibPercentDelta = $ibPercentMax - $prevParentIbPercent;
                $isFirstGen = true; // first generation customers should not have an infinity bonus
                foreach ($parents as $parentId) {
                    /** @var EBonDwnl $parentCompress */
                    $parentCompress = $mapById[$parentId];
                    /** @var ECustomer $parentPlain */
                    $parentPlain = $mapPlainById[$parentId];
                    $parentMlmId = $parentPlain->getMlmId();
                    $parentRankId = $parentCompress->getRankRef();
                    $parentScheme = $this->hlpScheme->getSchemeByCustomer($parentPlain);
                    /* should parent get an Infinity bonus? */
                    $hasInfPercent = false;
                    $ibPercent = 0;
                    if (isset($cfgParams[$scheme][$parentRankId])) {
                        /** @var ECfgParam $param */
                        $param = $cfgParams[$scheme][$parentRankId];
                        $ibPercent = $param->getInfinity();
                        $hasInfPercent = ($ibPercent > 0);
                    }
                    $hasParentRightScheme = ($parentScheme == $scheme);
                    if ($hasInfPercent && $hasParentRightScheme && !$isFirstGen) {
                        /* compare ranks and interrupt if next parent has the same rank or lower */
                        $shouldInterrupt = $this->shouldInterruptInfinityBonus($prevParentIbPercent, $ibPercent);
                        /* this parent should not get infinity bonus (has the same rank or lower)*/
                        if ($shouldInterrupt) continue;
                        /* all infinity bonus is distributed, break the loop */
                        if ($ibPercentDelta <= Cfg::DEF_ZERO) break;

                        /* calculate bonus value and add to results */
                        /* get infinity bonus percent */
                        /** @var ECfgParam $param */
                        $param = $cfgParams[$scheme][$parentRankId];
                        $ibPercent = $param->getInfinity();
                        $percent = ($ibPercent <= $ibPercentDelta) ? $ibPercent : $ibPercentDelta;
                        $bonus = $this->hlpFormat->roundBonus($pv * $percent);
                        /* add new bonus entry to results */
                        $entry = new \Praxigento\BonusHybrid\Service\Calc\A\Data\Bonus();
                        $entry->setCustomerRef($parentId);
                        $entry->setValue($bonus);
                        $entry->setDonatorRef($custId);
                        $result[] = $entry;
                        $this->logger->debug("BON/INF/$scheme: Upline #$parentId ($parentMlmId) gets '$bonus' ($pv * $percent) from customer #$custId ($custMlmId).'");
                        /* re-save Infinity percent and decrease delta */
                        $prevParentIbPercent = $ibPercent;
                        $ibPercentDelta -= $ibPercent;

                    }
                    $isFirstGen = false;
                }
            }
        }
        /* clean and return */
        unset($mapTreeExp);
        unset($mapById);


        return $result;
    }

    /**
     * @return array [$scheme][$rankId] => $cfg;
     */
    private function getCfgParams()
    {
        $result = [];
        $where = null;
        $order = [
            ECfgParam::ATTR_SCHEME . ' ASC',
            ECfgParam::ATTR_LEG_MAX . ' DESC',
            ECfgParam::ATTR_LEG_MEDIUM . ' DESC',
            ECfgParam::ATTR_LEG_MIN . ' DESC'
        ];
        $data = $this->repoCfgParams->get($where, $order);
        /** @var ECfgParam $one */
        foreach ($data as $one) {
            $scheme = $one->getScheme();
            $rankId = $one->getRankId();
            $result[$scheme][$rankId] = $one;
        }
        return $result;
    }

    /**
     * Walk through the all configuration parameters & get MAX percent for Infinity bonus
     *
     * @param array $cfgParams see \Praxigento\BonusHybrid\Service\Calc\Bonus\Infinity\Calc::getCfgParams
     * @return int
     */
    private function getMaxPercentForInfinityBonus($cfgParams, $scheme)
    {
        $result = 0;
        $params = $cfgParams[$scheme];
        /** @var ECfgParam $item */
        foreach ($params as $item) {
            $percent = $item->getInfinity();
            if ($percent > $result) {
                $result = $percent;
            }
        }
        return $result;
    }

    /**
     * Interrupt infinity bonus if parent's percent exists and is greater or equal to current infinity percent.
     *
     * @param $percent
     * @param $percentParent
     *
     * @return bool
     */
    private function shouldInterruptInfinityBonus($percent, $percentParent)
    {
        $result = false;
        if (
            ($percentParent > 0) &&
            ($percentParent <= $percent)
        ) {
            $result = true;
        }
        return $result;
    }
}