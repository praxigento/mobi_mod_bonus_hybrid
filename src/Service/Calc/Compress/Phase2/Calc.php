<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Compress\Phase2;

use Praxigento\BonusHybrid\Defaults as Def;
use Praxigento\BonusHybrid\Repo\Entity\Data\Cfg\Param as ECfgParam;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EDwnlBon;

/**
 * Compression calculation itself.
 */
class Calc
{
    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\Traits\TMap {
        mapById as protected;
        mapByTeams as protected;
        mapByTreeDepthDesc as protected;
    }

    /** @var \Praxigento\BonusHybrid\Service\Calc\Compress\Helper */
    private $hlp;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Cfg\Param */
    private $repoCfgParam;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoDwnlBon;
    /** @var \Praxigento\BonusBase\Repo\Entity\Rank */
    private $repoRank;

    public function __construct(
        \Praxigento\BonusHybrid\Service\Calc\Compress\Helper $hlp,
        \Praxigento\BonusBase\Repo\Entity\Rank $repoRank,
        \Praxigento\BonusHybrid\Repo\Entity\Cfg\Param $repoCfgParam,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoDwnlBon
    )
    {
        $this->hlp = $hlp;
        $this->repoRank = $repoRank;
        $this->repoCfgParam = $repoCfgParam;
        $this->repoDwnlBon = $repoDwnlBon;
    }


    public function exec($writeOffCalcId, $phase1CalcId, $phase2CalcId, $scheme)
    {
        /* collect additional data */
        $mapPv = $this->hlp->getPv($writeOffCalcId);
        $dwnlPlain = $this->repoDwnlBon->getByCalcId($writeOffCalcId);
        $dwnlCompress = $this->repoDwnlBon->getByCalcId($phase1CalcId);
        $cfgParams = $this->getCfgParams();

        /* perform action */
        $result = [];

        /* prepare source data for calculation */
        $mapByIdCompress = $this->mapById($dwnlCompress, EDwnlBon::ATTR_CUST_REF);
        $mapByTeamCompress = $this->mapByTeams($dwnlCompress, EDwnlBon::ATTR_CUST_REF, EDwnlBon::ATTR_PARENT_REF);
        $mapByDepthCompress = $this->mapByTreeDepthDesc($dwnlCompress, EDwnlBon::ATTR_CUST_REF, EDwnlBon::ATTR_DEPTH);
        $mapByIdPlain = $this->mapById($dwnlPlain, EDwnlBon::ATTR_CUST_REF);
        $mapByTeamPlain = $this->mapByTeams($dwnlPlain, EDwnlBon::ATTR_CUST_REF, EDwnlBon::ATTR_PARENT_REF);
        $rankIdMgr = $this->repoRank->getIdByCode(Def::RANK_MANAGER);
        /* MOBI-629: add init rank for un-ranked entries */
        $rankIdDistr = $this->repoRank->getIdByCode(Def::RANK_DISTRIBUTOR);;
        /* run though the compressed tree from bottom to top and collect OV */
        foreach ($mapByDepthCompress as $level) {
            foreach ($level as $custId) {
                /* get compressed data and compose phase2 item */
                /** @var EDwnlBon $custData */
                $custData = $mapByIdCompress[$custId];
                $parentId = $custData->getParentRef();
                $pvOwn = $mapPv[$custId] ?? 0;
                $pvCompress = $custData->getPv();
                $tvCompress = $custData->getTv();
                $resultEntry = new EDwnlBon();
                $resultEntry->setCalculationRef($phase2CalcId);
                $resultEntry->setCustomerRef($custId);
                $resultEntry->setParentRef($parentId);
                $resultEntry->setRankRef($rankIdDistr);
                $resultEntry->setPv($pvCompress);
                $resultEntry->setTv($tvCompress);
                $resultEntry = [
                    Oi::ATTR_SCHEME => $scheme,
                    Oi::ATTR_CUSTOMER_REF => $custId,
                    Oi::ATTR_PARENT_REF => $parentId,
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
                        if (isset($mapByTeamPlain[$custId])) {
                            $teamPlain = $mapByTeamPlain[$custId];
                            $legs = $this->legsCalc($teamPlain, $mapByIdPlain, Pto::ATTR_OV);

                        } else {
                            $legs = [0, 0, 0];
                        }
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
                        $resultEntry[Oi::ATTR_PARENT_REF] = $custId;
                    } elseif ($fatherIsUnqual && ($scheme == Def::SCHEMA_EU)) {
                        /* EU: there is qualified grand for unqualified father, should not compress */
                        $resultEntry[Oi::ATTR_PARENT_REF] = $prevParentId;
                    } else {
                        $resultEntry[Oi::ATTR_PARENT_REF] = $foundParentId;
                    }
                }

                /* add entry to results */
                $result[$custId] = $resultEntry;
            }
        }

        $req = new \Praxigento\Downline\Service\Snap\Request\ExpandMinimal();
        $req->setTree($result);
        $req->setKeyCustomerId(Oi::ATTR_CUSTOMER_REF);
        $req->setKeyParentId(Oi::ATTR_PARENT_REF);
        $resp = $this->callDwnlSnap->expandMinimal($req);
        $snap = $resp->getSnapData();
        foreach ($result as $id => $one) {
            $depth = $snap[$id][\Praxigento\Downline\Repo\Entity\Data\Snap::ATTR_DEPTH];
            $path = $snap[$id][\Praxigento\Downline\Repo\Entity\Data\Snap::ATTR_PATH];
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
     * Get configuration for Override & Infinity bonuses ordered by scheme and leg max/medium/min desc.
     *
     * @return array [$scheme=>[$rankId=>[...], ...], ...]
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
}