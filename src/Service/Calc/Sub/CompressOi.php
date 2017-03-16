<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Calc\Sub;

use Praxigento\BonusHybrid\Defaults as Def;
use Praxigento\BonusHybrid\Entity\Compression\Oi as Oi;
use Praxigento\BonusHybrid\Entity\Compression\Ptc as Ptc;
use Praxigento\BonusHybrid\Entity\Registry\Pto as Pto;

class CompressOi
{
    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\Traits\TMap {
        mapById as protected;
        mapByTeams as protected;
        mapByTreeDepthDesc as protected;
    }

    const OPT_CONFIG_PARAMS = 'configParams';
    const OPT_MAP_PV = 'mapPv';
    const OPT_SCHEME = 'scheme';
    const OPT_TREE_COMPRESSED_PTC = 'treeCompressedPtc';
    const OPT_TREE_PLAIN_PTO = 'treePlainPto';
    /** @var \Praxigento\BonusHybrid\Helper\Calc\GetMaxQualifiedRankId */
    protected $hlpGetMaxRankId;
    /** @var \Praxigento\BonusHybrid\Helper\Calc\IsQualified */
    protected $hlpIsQualified;
    /** @var \Praxigento\BonusBase\Helper\IRank */
    protected $hlpRank;
    /** @var \Praxigento\Downline\Tool\ITree */
    protected $toolDwnlTree;
    /** @var \Praxigento\Downline\Service\ISnap */
    protected $callDwnlSnap;
    public function __construct(
        \Praxigento\BonusBase\Helper\IRank $hlpRank,
        \Praxigento\BonusHybrid\Helper\Calc\IsQualified $hlpIsQualified,
        \Praxigento\BonusHybrid\Helper\Calc\GetMaxQualifiedRankId $hlpGetMaxRankId,
        \Praxigento\Downline\Tool\ITree $toolTree,
        \Praxigento\Downline\Service\ISnap $callDwnSnap
    ) {
        $this->hlpRank = $hlpRank;
        $this->hlpIsQualified = $hlpIsQualified;
        $this->hlpGetMaxRankId = $hlpGetMaxRankId;
        $this->toolDwnlTree = $toolTree;
        $this->callDwnlSnap = $callDwnSnap;
    }

    /**
     * @param $opts
     *
     * @return array [$custId=>[\Praxigento\BonusHybrid\Entity\Compression\Oi::...], ...]
     */
    public function do($opts)
    {
        /* parse options */
        $mapPv = $opts[self::OPT_MAP_PV];
        $treeCompress = $opts[self::OPT_TREE_COMPRESSED_PTC];
        $treePlain = $opts[self::OPT_TREE_PLAIN_PTO];
        $cfgParams = $opts[self::OPT_CONFIG_PARAMS];
        $scheme = $opts[self::OPT_SCHEME];

        /* perform action */
        $result = [];

        /* prepare source data for calculation */
        $mapByIdCompress = $this->mapById($treeCompress, Ptc::ATTR_CUSTOMER_ID);
        $mapByTeamCompress = $this->mapByTeams($treeCompress, Ptc::ATTR_CUSTOMER_ID, Ptc::ATTR_PARENT_ID);
        $mapByDepthCompress = $this->mapByTreeDepthDesc($treeCompress, Ptc::ATTR_CUSTOMER_ID, Ptc::ATTR_DEPTH);
        $mapByIdPlain = $this->mapById($treePlain, Pto::ATTR_CUSTOMER_REF);
        $mapByTeamPlain = $this->mapByTeams($treePlain, Pto::ATTR_CUSTOMER_REF, Pto::ATTR_PARENT_REF);
        $rankIdMgr = $this->hlpRank->getIdByCode(Def::RANK_MANAGER);
        /* MOBI-629: add init rank for un-ranked entries */
        $rankIdDistr = $this->hlpRank->getIdByCode(Def::RANK_DISTRIBUTOR);;
        /* run though the compressed tree from bottom to top and collect OV */
        foreach ($mapByDepthCompress as $level) {
            foreach ($level as $custId) {

                /* get compressed data and compose phase2 item */
                $custData = $mapByIdCompress[$custId];
                $parentId = $custData[Ptc::ATTR_PARENT_ID];
                $pvOwn = isset($mapPv[$custId]) ? $mapPv[$custId] : 0;
                $pvCompress = $custData[Ptc::ATTR_PV];
                $tvCompress = $custData[Ptc::ATTR_TV];
                $resultEntry = [
                    Oi::ATTR_SCHEME => $scheme,
                    Oi::ATTR_CUSTOMER_ID => $custId,
                    Oi::ATTR_PARENT_ID => $parentId,
                    Oi::ATTR_RANK_ID => $rankIdDistr,
                    Oi::ATTR_PV => $pvCompress,
                    Oi::ATTR_TV => $tvCompress,
                    Oi::ATTR_OV_LEG_MAX => 0,
                    Oi::ATTR_OV_LEG_SECOND => 0,
                    Oi::ATTR_OV_LEG_OTHERS => 0
                ];

                /* calculate phase2 legs for qualified managers */
                $isCustQualifiedAsMgr = $this->hlpIsQualified->exec([
                    \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_CUST_ID => $custId,
                    \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_PV => $pvOwn,
                    \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_TV => $tvCompress,
                    \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_SCHEME => $scheme,
                    \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_CFG_PARAMS => $cfgParams
                ]);

                if ($isCustQualifiedAsMgr) {
                    /* this is qualified manager, calculate MAX leg, second leg and summary leg */
                    if (isset($mapByTeamCompress[$custId])) {
                        /* this customer has downline subtrees in compressed and plain trees */

                        /* define legs based on plain OV */
                        $teamPlain = $mapByTeamPlain[$custId];
                        $legs = $this->legsCalc($teamPlain, $mapByIdPlain, Pto::ATTR_OV);
                        list($legMaxP, $legSecondP, $legOthersP) = $legs;

                        /* define legs based on compressed OV */
                        $teamCompress = $mapByTeamCompress[$custId];
                        $legs = $this->legsCalc($teamCompress, $mapByIdCompress, Ptc::ATTR_OV);
                        list($legMaxC, $legSecondC, $legOthersC) = $legs;

                        /* get first 2 legs from plain and 'others' from compressed */
                        $legs = $this->legsCompose(
                            $legMaxP, $legSecondP, $legOthersP, $legMaxC, $legSecondC, $legOthersC
                        );
                        list($legMax, $legSecond, $legOthers) = $legs;

                        /* update legs */
                        $resultEntry[Oi::ATTR_OV_LEG_MAX] = $legMax;
                        $resultEntry[Oi::ATTR_OV_LEG_SECOND] = $legSecond;
                        $resultEntry[Oi::ATTR_OV_LEG_OTHERS] = $legOthers;
                        $rankId = $this->hlpGetMaxRankId->exec([
                            \Praxigento\BonusHybrid\Helper\Calc\GetMaxQualifiedRankId::OPT_COMPRESS_OI_ENTRY => $resultEntry,
                            \Praxigento\BonusHybrid\Helper\Calc\GetMaxQualifiedRankId::OPT_SCHEME => $scheme,
                            \Praxigento\BonusHybrid\Helper\Calc\GetMaxQualifiedRankId::OPT_CFG_PARAMS => $cfgParams
                        ]);
                        $resultEntry[Oi::ATTR_RANK_ID] = $rankId;

                    } else {
                        /* qualified customer w/o downline is a Manager */
                        $resultEntry[Oi::ATTR_RANK_ID] = $rankIdMgr;
                    }
                }

                /* check qualification for current parent */
                $parentData = $mapByIdCompress[$parentId];
                $parentPvOwn = isset($mapPv[$parentId]) ? $mapPv[$parentId] : 0;
                $parentTv = $parentData[Ptc::ATTR_TV];
                $isParentQualifiedAsMgr = $this->hlpIsQualified->exec([
                    \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_CUST_ID => $parentId,
                    \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_PV => $parentPvOwn,
                    \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_TV => $parentTv,
                    \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_SCHEME => $scheme,
                    \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_CFG_PARAMS => $cfgParams
                ]);

                /* re-link parent for all customers (qualified & distributors) */
                if (!$isParentQualifiedAsMgr) {
                    /* parent is not qualified, move customer up to the closest parent qualified as manager or higher */
                    $path = $custData[Ptc::ATTR_PATH];
                    $parents = $this->toolDwnlTree->getParentsFromPathReversed($path);
                    $foundParentId = null;
                    $prevParentId = null;
                    $fatherIsUnqual = false; // we should not compress nodes for EU scheme where grand is qualified
                    $isFirstGen = true; // (first gen only)
                    foreach ($parents as $newParentId) {
                        $newParentData = $mapByIdCompress[$newParentId];
                        $newParentPvOwn = isset($mapPv[$newParentId]) ? $mapPv[$newParentId] : 0;
                        $newParentTv = $newParentData[Ptc::ATTR_TV];

                        $isNewParentQualifiedAsMgr = $this->hlpIsQualified->exec([
                            \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_CUST_ID => $newParentId,
                            \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_PV => $newParentPvOwn,
                            \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_TV => $newParentTv,
                            \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_SCHEME => $scheme,
                            \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_CFG_PARAMS => $cfgParams
                        ]);
                        if ($isNewParentQualifiedAsMgr) {
                            $foundParentId = $newParentId;
                            break;
//                        } elseif ($isFirstGen) {
//                            $fatherIsUnqual = true; // customer's father is distributor
//                            $isFirstGen = false; // all next generations are not first
//                        } elseif ($fatherIsUnqual) {
//                            $fatherIsUnqual = false; // this is second generation or higher
                        }
                        $fatherIsUnqual = true; // customer's father or higher is distributor
                        $prevParentId = $newParentId; // save the last distributor before manager
                    }
                    unset($parents);
                    if (is_null($foundParentId)) {
                        /* no qualified parent up to the root, make this customer as root customer  */
                        $resultEntry[Oi::ATTR_PARENT_ID] = $custId;
                    } elseif ($fatherIsUnqual && ($scheme == Def::SCHEMA_EU)) {
                        /* EU: there is qualified grand for unqualified father, should not compress */
                        $resultEntry[Oi::ATTR_PARENT_ID] = $prevParentId;
                    } else {
                        $resultEntry[Oi::ATTR_PARENT_ID] = $foundParentId;
                    }
                }

                /* add entry to results */
                $result[$custId] = $resultEntry;
            }
        }

        $req = new \Praxigento\Downline\Service\Snap\Request\ExpandMinimal();
        $req->setTree($result);
        $req->setKeyCustomerId(Oi::ATTR_CUSTOMER_ID);
        $req->setKeyParentId(Oi::ATTR_PARENT_ID);
        $resp = $this->callDwnlSnap->expandMinimal($req);
        $snap = $resp->getSnapData();
        foreach ($result as $id => $one) {
            $depth = $snap[$id][\Praxigento\Downline\Data\Entity\Snap::ATTR_DEPTH];
            $path = $snap[$id][\Praxigento\Downline\Data\Entity\Snap::ATTR_PATH];
            $result[$id][Oi::ATTR_DEPTH] = $depth;
            $result[$id][Oi::ATTR_PATH] = $path;
        }
        /* clean up memory */
        unset($mapByTeamPlain);
        unset($mapByIdPlain);
        unset($mapByDepthCompress);
        unset($mapByTeamCompress);
        unset($mapByIdCompress);

        /* and return result */
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
    protected function legsCalc($team, $mapById, $labelOv)
    {
        $legMax = $legSecond = $legOthers = 0;
        foreach ($team as $memberId) {
            $ovMember = $mapById[$memberId][$labelOv];
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
    protected function legsCompose($maxP, $secondP, $othersP, $maxC, $secondC, $othersC)
    {
        $second = $others = 0;
        if ($maxP && !$secondP && !$othersP) {
            /* there is one only leg: use compressed data */
            $max = $maxC;
        } elseif ($maxP && $secondP && !$othersP) {
            /* there are 2 legs */
            $max = $maxC;
            $second = $secondC;
        } else {
            /* there are 2 legs & others */
            $max = ($maxP > $maxC) ? $maxP : $maxC;
            $second = ($secondP > $secondC) ? $secondP : $secondC;
            $others = $maxC + $secondC + $othersC - ($max + $second);
        }
        $result = [$max, $second, $others];
        return $result;
    }
}