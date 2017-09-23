<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Compress\Phase2;

use Praxigento\BonusHybrid\Defaults as Def;
use Praxigento\BonusHybrid\Repo\Entity\Data\Cfg\Param as ECfgParam;
use Praxigento\BonusHybrid\Repo\Entity\Data\Compression\Phase2\Legs as ELegs;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EBonDwnl;

/**
 * Compression calculation itself.
 */
class Calc
{
    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\A\Traits\TMap {
        mapById as protected;
        mapByTeams as protected;
        mapByTreeDepthDesc as protected;
    }

    /** @var \Praxigento\BonusHybrid\Service\Calc\Compress\Helper */
    private $hlp;
    /** @var \Praxigento\Downline\Tool\ITree */
    private $hlpDwnlTree;
    /** @var \Praxigento\BonusHybrid\Helper\Calc\GetMaxQualifiedRankId */
    private $hlpGetMaxRankId;
    /** @var \Praxigento\BonusHybrid\Helper\Calc\IsQualified */
    private $hlpIsQualified;
    /** @var \Praxigento\BonusHybrid\Tool\IScheme */
    private $hlpScheme;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Cfg\Param */
    private $repoCfgParam;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoDwnlBon;
    /** @var \Praxigento\BonusBase\Repo\Entity\Rank */
    private $repoRank;

    public function __construct(
        \Praxigento\Downline\Tool\ITree $hlpTree,
        \Praxigento\BonusHybrid\Tool\IScheme $hlpScheme,
        \Praxigento\BonusHybrid\Service\Calc\Compress\Helper $hlp,
        \Praxigento\BonusHybrid\Helper\Calc\GetMaxQualifiedRankId $hlpGetMaxRankId,
        \Praxigento\BonusHybrid\Helper\Calc\IsQualified $hlpIsQualified,
        \Praxigento\BonusBase\Repo\Entity\Rank $repoRank,
        \Praxigento\BonusHybrid\Repo\Entity\Cfg\Param $repoCfgParam,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoDwnlBon
    )
    {
        $this->hlpDwnlTree = $hlpTree;
        $this->hlpScheme = $hlpScheme;
        $this->hlp = $hlp;
        $this->hlpGetMaxRankId = $hlpGetMaxRankId;
        $this->hlpIsQualified = $hlpIsQualified;
        $this->repoRank = $repoRank;
        $this->repoCfgParam = $repoCfgParam;
        $this->repoDwnlBon = $repoDwnlBon;
    }

    /**
     * @param $writeOffCalcId
     * @param $phase1CalcId
     * @param $phase2CalcId
     * @param $scheme
     * @return \Praxigento\BonusHybrid\Service\Calc\Compress\Phase2\Calc\Result
     */
    public function exec($writeOffCalcId, $phase1CalcId, $phase2CalcId, $scheme)
    {
        /* collect additional data */
        $mapPv = $this->hlp->getPv($writeOffCalcId);
        $dwnlPlain = $this->repoDwnlBon->getByCalcId($writeOffCalcId);
        $dwnlCompress = $this->repoDwnlBon->getByCalcId($phase1CalcId);
        $cfgParams = $this->getCfgParams();

        /* perform action */
        $resultDownline = [];
        $resultLegs = [];


        /* prepare source data for calculation */
        $mapByIdCompress = $this->mapById($dwnlCompress, EBonDwnl::ATTR_CUST_REF);
        $mapByTeamCompress = $this->mapByTeams($dwnlCompress, EBonDwnl::ATTR_CUST_REF, EBonDwnl::ATTR_PARENT_REF);
        $mapByDepthCompress = $this->mapByTreeDepthDesc($dwnlCompress, EBonDwnl::ATTR_CUST_REF, EBonDwnl::ATTR_DEPTH);
        $mapByIdPlain = $this->mapById($dwnlPlain, EBonDwnl::ATTR_CUST_REF);
        $mapByTeamPlain = $this->mapByTeams($dwnlPlain, EBonDwnl::ATTR_CUST_REF, EBonDwnl::ATTR_PARENT_REF);
        $rankIdMgr = $this->repoRank->getIdByCode(Def::RANK_MANAGER);
        /* MOBI-629: add init rank for un-ranked entries */
        $rankIdDistr = $this->repoRank->getIdByCode(Def::RANK_DISTRIBUTOR);;
        /* run though the compressed tree from bottom to top and collect OV */
        foreach ($mapByDepthCompress as $level) {
            foreach ($level as $custId) {
                /* prepare results entries */
                $entryDwnl = new EBonDwnl();
                $entryLegs = new ELegs();
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
                /* populate legs result entry with data */
                $entryLegs->setCalcRef($phase2CalcId);
                $entryLegs->setCustRef($custId);
                /**
                 * Calculate phase2 legs for qualified customers only (manager or higher).
                 */
                $ctxHlpQ = new \Praxigento\BonusHybrid\Helper\Calc\IsQualified\Context();
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

                        /* define legs based on plain OV */
                        if (isset($mapByTeamPlain[$custId])) {
                            $teamPlain = $mapByTeamPlain[$custId];
                            $legs = $this->legsCalc($teamPlain, $mapByIdPlain, EBonDwnl::ATTR_OV);

                        } else {
                            $legs = [0, 0, 0];
                        }
                        list($legMaxP, $legSecondP, $legOthersP) = $legs;
                        /* define legs based on compressed OV */
                        $teamCompress = $mapByTeamCompress[$custId];
                        $legs = $this->legsCalc($teamCompress, $mapByIdCompress, EBonDwnl::ATTR_OV);
                        list($legMaxC, $legSecondC, $legOthersC) = $legs;

                        /* get first 2 legs from plain and 'others' from compressed */
                        $legs = $this->legsCompose(
                            $legMaxP, $legSecondP, $legOthersP, $legMaxC, $legSecondC, $legOthersC
                        );
                        list($legMax, $legSecond, $legOthers) = $legs;

                        /* update legs */
                        $entryLegs->setLegMax($legMax);
                        $entryLegs->setLegSecond($legSecond);
                        $entryLegs->setLegOthers($legOthers);
                        /* then calculate & update rank ID */
                        $ctxHlpR = new \Praxigento\BonusHybrid\Helper\Calc\GetMaxQualifiedRankId\Context();
                        $ctxHlpR->setCfgParams($cfgParams);
                        $ctxHlpR->setScheme($scheme);
                        $ctxHlpR->setDownlineEntry($entryDwnl);
                        $ctxHlpR->setLegsEntry($entryLegs);
                        $rankId = $this->hlpGetMaxRankId->exec($ctxHlpR);
                        $entryDwnl->setRankRef($rankId);

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
                $ctxHlpQ = new \Praxigento\BonusHybrid\Helper\Calc\IsQualified\Context();
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
                    $parents = $this->hlpDwnlTree->getParentsFromPathReversed($path);
                    $foundParentId = null;
                    $prevParentId = null;
                    $fatherIsUnqual = false; // we should not compress nodes for EU scheme where grand is qualified
                    foreach ($parents as $newParentId) {
                        /** @var EBonDwnl $newParentData */
                        $newParentData = $mapByIdCompress[$newParentId];
                        $newParentPvOwn = isset($mapPv[$newParentId]) ? $mapPv[$newParentId] : 0;
                        $newParentTv = $newParentData->getTv();
                        /* validate parent qualification */
                        $ctxHlpQ = new \Praxigento\BonusHybrid\Helper\Calc\IsQualified\Context();
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
                    } elseif ($fatherIsUnqual && ($scheme == Def::SCHEMA_EU)) {
                        /* EU: there is qualified grand for unqualified father, should not compress */
                        $entryDwnl->setParentRef($prevParentId);
                    } else {
                        $entryDwnl->setParentRef($foundParentId);
                    }
                }

                /* add entry to results */
                $resultDownline[$custId] = $entryDwnl;
                $resultLegs[$custId] = $entryLegs;
            }
        }
        /* get paths & depths for downline tree (is & parentId only present in results ) */
        $snap = $this->hlpDwnlTree->expandMinimal($resultDownline, EBonDwnl::ATTR_PARENT_REF);
        /* go through the downline snapshot and move depth & path info into results */
        foreach ($resultDownline as $id => $one) {
            $depth = $snap[$id][\Praxigento\Downline\Repo\Entity\Data\Snap::ATTR_DEPTH];
            $path = $snap[$id][\Praxigento\Downline\Repo\Entity\Data\Snap::ATTR_PATH];
            /** @var EBonDwnl $entry */
            $entry = $resultDownline[$id];
            $entry->setDepth($depth);
            $entry->setPath($path);
        }
        /* clean up memory */
        unset($mapByTeamPlain);
        unset($mapByIdPlain);
        unset($mapByDepthCompress);
        unset($mapByTeamCompress);
        unset($mapByIdCompress);

        /* and return result */
        $result = new \Praxigento\BonusHybrid\Service\Calc\Compress\Phase2\Calc\Result();
        $result->setDownline($resultDownline);
        $result->setLegs($resultLegs);
        return $result;
    }

    /**
     * Get configuration for Override & Infinity bonuses ordered by scheme and leg max/medium/min desc.
     *
     * @return array [$scheme=>[$rankId=>[...], ...], ...]
     *
     * TODO: move this func closer to \Praxigento\BonusHybrid\Helper\Calc\IsQualified
     */
    private function getCfgParams()
    {
        $result = [];
        $order = [
            ECfgParam::ATTR_SCHEME . ' ASC',
            ECfgParam::ATTR_LEG_MAX . ' DESC',
            ECfgParam::ATTR_LEG_MEDIUM . ' DESC',
            ECfgParam::ATTR_LEG_MIN . ' DESC'
        ];
        $data = $this->repoCfgParam->get(null, $order);
        /** @var ECfgParam $one */
        foreach ($data as $one) {
            $scheme = $one->getScheme();
            $rankId = $one->getRankId();
            $result[$scheme][$rankId] = $one;
        }
        return $result;
    }


    /**
     * Run though first-line team members and collect OVs (plain or compressed).
     *
     * @param array $team Customers IDs for first-line team.
     * @param array $mapById OV data for members mapped by customer ID.
     * @param string $labelOv label of the OV entry in OV data.
     * @return array [$legMax, $legSecond, $legOthers]
     */
    private function legsCalc($team, $mapById, $labelOv)
    {
        $legMax = $legSecond = $legOthers = 0;
        foreach ($team as $memberId) {
            $member = $mapById[$memberId];
            $ovMember = $member->get($labelOv); // TODO: I don't know why I don't use $member->getOv()
            if ($ovMember > $legMax) {
                /* update MAX leg */
                $legOthers += $legSecond;
                $legSecond = $legMax;
                $legMax = $ovMember;
            } elseif ($ovMember > $legSecond) {
                /* update second leg */
                $legOthers += $legSecond;
                $legSecond = $ovMember;
            } else {
                $legOthers += $ovMember;
            }
        }
        $result = [$legMax, $legSecond, $legOthers];
        return $result;
    }

    /**
     * Compare plain legs with compressed legs and combine data in results.
     *
     * This bull-shit is from here:
     * https://jira.prxgt.com/browse/MOBI-629?focusedCommentId=87614&page=com.atlassian.jira.plugin.system.issuetabpanels:comment-tabpanel#comment-87614
     *
     *
     * @param $maxP
     * @param $secondP
     * @param $othersP
     * @param $maxC
     * @param $secondC
     * @param $othersC
     * @return array [$max, $second, $others]
     */
    private function legsCompose($maxP, $secondP, $othersP, $maxC, $secondC, $othersC)
    {
        $second = $others = 0;
        if ($maxP && !$secondP && !$othersP) {
            /* there is one only leg, use plain data */
            $max = $maxP;
        } elseif ($maxP && $secondP && !$othersP) {
            /* there are 2 legs, also use plain data */
            $max = $maxP;
            $second = $secondP;
        } else {
            /* there are 2 legs (use plain) & others (use delta) */
            $max = $maxP;
            $second = $secondP;
            $others = $maxC + $secondC + $othersC - ($max + $second);
        }
        $result = [$max, $second, $others];
        return $result;
    }
}