<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Compress;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Cfg\Param as ECfgParam;
use Praxigento\BonusHybrid\Repo\Data\Compression\Phase2\Legs as ELegs;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;
use Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Compress\Phase2\Data\Legs as DLegs;
use Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Compress\Phase2\Fun\Act\Qualify as ActQualify;
use Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Compress\Phase2\Fun\Rou\CalcLegs as RouCalcLegs;
use Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Compress\Phase2\Fun\Rou\ComposeLegs as RouComposeLegs;

/**
 * Process to calculate Phase2 compression.
 */
class Phase2
    implements \Praxigento\Core\Api\App\Service\Process
{
    /** int */
    const IN_CALC_ID_PHASE2 = 'calcIdPhase2';
    /** Phase1 compressed downline  */
    const IN_DWNL_PHASE1 = 'dwnlPhase1';
    /** Plain downline */
    const IN_DWNL_PLAIN = 'dwnlPlain';
    /** array none-compressed PV by customer ID */
    const IN_MAP_PV = 'mapPV';
    /** string Scheme code (see \Praxigento\BonusHybrid\Config::SCHEMA_XXX) */
    const IN_SCHEME = 'scheme';
    /** \Praxigento\BonusHybrid\Repo\Data\Downline[] */
    const OUT_DWNL_PHASE2 = 'dwnlPhase2';
    /** \Praxigento\BonusHybrid\Repo\Data\Compression\Phase2\Legs[] */
    const OUT_LEGS = 'legs';
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Compress\Phase2\Fun\Act\Qualify */
    private $actQualify;
    /** @var \Praxigento\Downline\Api\Helper\Tree */
    private $hlpTree;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Compress\Phase2\IsQualified */
    private $hlpIsQualified;
    /** @var \Praxigento\BonusHybrid\Api\Helper\Scheme */
    private $hlpScheme;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $daoBonDwnl;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Cfg\Param */
    private $daoCfgParam;
    /** @var \Praxigento\BonusBase\Repo\Dao\Rank */
    private $daoRank;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Compress\Phase2\Fun\Rou\CalcLegs */
    private $rouCalcLegs;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Compress\Phase2\Fun\Rou\ComposeLegs */
    private $rouComposeLegs;

    public function __construct(
        \Praxigento\Downline\Api\Helper\Tree $hlpTree,
        \Praxigento\BonusHybrid\Api\Helper\Scheme $hlpScheme,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Compress\Phase2\IsQualified $hlpIsQualified,
        \Praxigento\BonusBase\Repo\Dao\Rank $daoRank,
        \Praxigento\BonusHybrid\Repo\Dao\Cfg\Param $daoCfgParam,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoBonDwnl,
        ActQualify $actQualify,
        RouCalcLegs $rouCalcLegs,
        RouComposeLegs $rouComposeLegs
    )
    {
        $this->hlpTree = $hlpTree;
        $this->hlpScheme = $hlpScheme;
        $this->hlpIsQualified = $hlpIsQualified;
        $this->daoRank = $daoRank;
        $this->daoCfgParam = $daoCfgParam;
        $this->daoBonDwnl = $daoBonDwnl;
        $this->actQualify = $actQualify;
        $this->rouCalcLegs = $rouCalcLegs;
        $this->rouComposeLegs = $rouComposeLegs;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /* get working data from input */
        $phase2CalcId = $ctx->get(self::IN_CALC_ID_PHASE2);
        $dwnlCompress = $ctx->get(self::IN_DWNL_PHASE1);
        $dwnlPlain = $ctx->get(self::IN_DWNL_PLAIN);
        $mapPv = $ctx->get(self::IN_MAP_PV);
        $scheme = $ctx->get(self::IN_SCHEME);

        /* define local working data */
        $cfgParams = $this->getCfgParams();

        /* prepare output vars */
        $outDownline = [];
        $outLegs = [];

        /**
         * perform processing
         */
        $mapByIdCompress = $this->hlpTree->mapById($dwnlCompress, EBonDwnl::A_CUST_REF);
        $mapByTeamCompress = $this->hlpTree->mapByTeams($dwnlCompress, EBonDwnl::A_CUST_REF, EBonDwnl::A_PARENT_REF);
        $mapByDepthCompress = $this->hlpTree->mapByTreeDepthDesc($dwnlCompress, EBonDwnl::A_CUST_REF, EBonDwnl::A_DEPTH);
        $mapByIdPlain = $this->hlpTree->mapById($dwnlPlain, EBonDwnl::A_CUST_REF);
        $mapByTeamPlain = $this->hlpTree->mapByTeams($dwnlPlain, EBonDwnl::A_CUST_REF, EBonDwnl::A_PARENT_REF);
        $rankIdMgr = $this->daoRank->getIdByCode(Cfg::RANK_MANAGER);
        /* MOBI-629: add init rank for un-ranked entries */
        $rankIdDistr = $this->daoRank->getIdByCode(Cfg::RANK_DISTRIBUTOR);;
        /* run though the compressed tree from bottom to top and collect OV */
        foreach ($mapByDepthCompress as $level) {
            foreach ($level as $custId) {
                /* prepare results entries */
                $entryDwnl = new EBonDwnl();
                /* get compressed data and compose downline item */
                /** @var EBonDwnl $custData */
                $custData = $mapByIdCompress[$custId];
                $parentId = $custData->getParentRef();
                $pvOwn = $mapPv[$custId] ?? 0;
                $pvCompress = $custData->getPv();
                $tvCompress = $custData->getTv();
                $ovCompress = $custData->getOv();
                /* populate downline result entry with data */
                $entryDwnl->setCalculationRef($phase2CalcId);
                $entryDwnl->setCustomerRef($custId);
                $entryDwnl->setParentRef($parentId);
                $entryDwnl->setRankRef($rankIdDistr);
                $entryDwnl->setPv($pvCompress);
                $entryDwnl->setTv($tvCompress);
                $entryDwnl->setOv($ovCompress);

                /**
                 * Calculate phase2 legs for qualified customers only (manager or higher).
                 */
                $ctxHlpQ = new \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Compress\Phase2\IsQualified\Context();
                $ctxHlpQ->setCustId($custId);
                $ctxHlpQ->setPv($pvOwn);
                $ctxHlpQ->setTv($tvCompress);
                $ctxHlpQ->setScheme($scheme);
                $ctxHlpQ->setCfgParams($cfgParams);
                $isCustQualifiedAsMgr = $this->hlpIsQualified->exec($ctxHlpQ);

                if ($isCustQualifiedAsMgr) {
                    /* this is qualified manager, calculate MAX leg, second leg and summary leg */
                    if (isset($mapByTeamCompress[$custId])) {
                        /* this customer has downline subtrees in compressed and plain trees */
                        /* populate legs result entry with data */
                        $entryLegs = new ELegs();
                        $entryLegs->setCalcRef($phase2CalcId);
                        $entryLegs->setCustRef($custId);

                        /* define legs based on plain OV */
                        if (isset($mapByTeamPlain[$custId])) {
                            $teamPlain = $mapByTeamPlain[$custId];
                            $legsPlain = $this->rouCalcLegs->exec($teamPlain, $mapByIdPlain);
                        } else {
                            $legsPlain = new DLegs();
                        }
                        /* define legs based on compressed OV */
                        $teamCompress = $mapByTeamCompress[$custId];
                        $legsCompress = $this->rouCalcLegs->exec($teamCompress, $mapByIdCompress);

                        /* get first 2 legs from plain and 'others' from compressed */
                        $legs = $this->rouComposeLegs->exec($legsPlain, $legsCompress);

                        /* update legs */
                        $entryLegs->setLegMax($legs->getMaxOv());
                        $entryLegs->setLegSecond($legs->getSecondOv());
                        $entryLegs->setLegOthers($legs->getOthersOv());
                        $entryLegs->setCustMaxRef($legs->getMaxCustId());
                        $entryLegs->setCustSecondRef($legs->getSecondCustId());
                        /* then calculate & update rank ID */
                        $reqQual = new \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Compress\Phase2\Fun\Act\Qualify\Data\Request();
                        $reqQual->setCfgParams($cfgParams);
                        $reqQual->setScheme($scheme);
                        $reqQual->setDownlineEntry($entryDwnl);
                        $reqQual->setLegsEntry($entryLegs);
                        $respQual = $this->actQualify->exec($reqQual);
                        $rankId = $respQual->getRankId();
                        $entryLegs = $respQual->getLegsEntry();

                        /* save rankId & add legs entry to results */
                        $entryDwnl->setRankRef($rankId);
                        $outLegs[$custId] = $entryLegs;

                    } else {
                        /* qualified customer w/o downline is a Manager */
                        $forcedRank = $this->hlpScheme->getForcedQualificationRank($custId, $scheme);
                        $rankIdChecked = ($forcedRank) ? $forcedRank : $rankIdMgr;
                        $entryDwnl->setRankRef($rankIdChecked);
                    }
                }
                /**
                 * Check qualification for current parent
                 */
                /** @var EBonDwnl $parentData */
                $parentData = $mapByIdCompress[$parentId];
                $parentPvOwn = isset($mapPv[$parentId]) ? $mapPv[$parentId] : 0;
                $parentTv = $parentData->getTv();
                /* validate parent qualification */
                $ctxHlpQ = new \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Compress\Phase2\IsQualified\Context();
                $ctxHlpQ->setCustId($parentId);
                $ctxHlpQ->setPv($parentPvOwn);
                $ctxHlpQ->setTv($parentTv);
                $ctxHlpQ->setScheme($scheme);
                $ctxHlpQ->setCfgParams($cfgParams);
                $isParentQualifiedAsMgr = $this->hlpIsQualified->exec($ctxHlpQ);

                /* re-link parent for all customers (qualified & distributors) */
                if (!$isParentQualifiedAsMgr) {
                    /* parent is not qualified, move customer up to the closest parent qualified as manager or higher */
                    $path = $custData->getPath();
                    $parents = $this->hlpTree->getParentsFromPathReversed($path);
                    $foundParentId = null;
                    $prevParentId = null;
                    $fatherIsUnqual = false; // we should not compress nodes for EU scheme where grand is qualified
                    foreach ($parents as $newParentId) {
                        /** @var EBonDwnl $newParentData */
                        $newParentData = $mapByIdCompress[$newParentId];
                        $newParentPvOwn = isset($mapPv[$newParentId]) ? $mapPv[$newParentId] : 0;
                        $newParentTv = $newParentData->getTv();
                        /* validate parent qualification */
                        $ctxHlpQ = new \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Compress\Phase2\IsQualified\Context();
                        $ctxHlpQ->setCustId($newParentId);
                        $ctxHlpQ->setPv($newParentPvOwn);
                        $ctxHlpQ->setTv($newParentTv);
                        $ctxHlpQ->setScheme($scheme);
                        $ctxHlpQ->setCfgParams($cfgParams);
                        $isNewParentQualifiedAsMgr = $this->hlpIsQualified->exec($ctxHlpQ);
                        if ($isNewParentQualifiedAsMgr) {
                            $foundParentId = $newParentId;
                            break;
                        }
                        $fatherIsUnqual = true; // customer's father or higher is distributor
                        $prevParentId = $newParentId; // save the last distributor before manager
                    }
                    unset($parents);
                    if (is_null($foundParentId)) {
                        /* no qualified parent up to the root, make this customer as root customer  */
                        $entryDwnl->setParentRef($custId);
                    } elseif ($fatherIsUnqual && ($scheme == Cfg::SCHEMA_EU)) {
                        /* EU: there is qualified grand for unqualified father, should not compress */
                        $entryDwnl->setParentRef($prevParentId);
                    } else {
                        $entryDwnl->setParentRef($foundParentId);
                    }
                }

                /* add entry to results */
                $outDownline[$custId] = $entryDwnl;
            }
        }
        /* get paths & depths for downline tree (is & parentId only present in results ) */
        $snap = $this->hlpTree->expandMinimal($outDownline, EBonDwnl::A_PARENT_REF);
        /* go through the downline snapshot and move depth & path info into results */
        foreach ($outDownline as $id => $one) {
            $depth = $snap[$id][\Praxigento\Downline\Repo\Data\Snap::A_DEPTH];
            $path = $snap[$id][\Praxigento\Downline\Repo\Data\Snap::A_PATH];
            /** @var EBonDwnl $entry */
            $entry = $outDownline[$id];
            $entry->setDepth($depth);
            $entry->setPath($path);
        }
        /* clean up memory */
        unset($mapByTeamPlain);
        unset($mapByIdPlain);
        unset($mapByDepthCompress);
        unset($mapByTeamCompress);
        unset($mapByIdCompress);

        /* put result data into output */
        $result = new \Praxigento\Core\Data();
        $result->set(self::OUT_DWNL_PHASE2, $outDownline);
        $result->set(self::OUT_LEGS, $outLegs);
        return $result;
    }

    /**
     * Get configuration for Override & Infinity bonuses ordered by scheme and leg max/medium/min desc.
     *
     * @return array [$scheme=>[$rankId=>[...], ...], ...]
     *
     * TODO: move this func closer to \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Compress\Phase2\IsQualified
     */
    private function getCfgParams()
    {
        $result = [];
        $order = [
            ECfgParam::A_SCHEME . ' ASC',
            ECfgParam::A_LEG_MAX . ' DESC',
            ECfgParam::A_LEG_MEDIUM . ' DESC',
            ECfgParam::A_LEG_MIN . ' DESC'
        ];
        $data = $this->daoCfgParam->get(null, $order);
        /** @var ECfgParam $one */
        foreach ($data as $one) {
            $scheme = $one->getScheme();
            $rankId = $one->getRankId();
            $result[$scheme][$rankId] = $one;
        }
        return $result;
    }

}