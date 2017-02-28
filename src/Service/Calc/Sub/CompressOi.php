<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Calc\Sub;

use Praxigento\BonusHybrid\Defaults as Def;
use Praxigento\BonusHybrid\Entity\Compression\Oi as Oi;
use Praxigento\BonusHybrid\Entity\Compression\Ptc as Ptc;

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

    public function __construct(
        \Praxigento\BonusBase\Helper\IRank $hlpRank,
        \Praxigento\BonusHybrid\Helper\Calc\IsQualified $hlpIsQualified,
        \Praxigento\BonusHybrid\Helper\Calc\GetMaxQualifiedRankId $hlpGetMaxRankId,
        \Praxigento\Downline\Tool\ITree $toolTree
    ) {
        $this->hlpRank = $hlpRank;
        $this->hlpIsQualified = $hlpIsQualified;
        $this->hlpGetMaxRankId = $hlpGetMaxRankId;
        $this->toolDwnlTree = $toolTree;
    }

    /**
     * @param $opts
     *
     * @return array [$custId=>[$custId, $parentId, $pv, $ovLegMax, $ovLegSecond, $ovLegSummary], ...]
     */
    public function do($opts)
    {
        /* parse options */
        $mapPv = $opts[self::OPT_MAP_PV];
        $compressedPtc = $opts[self::OPT_TREE_COMPRESSED_PTC];
        $cfgParams = $opts[self::OPT_CONFIG_PARAMS];
        $scheme = $opts[self::OPT_SCHEME];
        $plainPto = $opts[self::OPT_TREE_PLAIN_PTO];

        /* perform action */
        $result = [];
        $mapById = $this->mapById($compressedPtc, Ptc::ATTR_CUSTOMER_ID);
        $mapByDepth = $this->mapByTreeDepthDesc($compressedPtc, Ptc::ATTR_CUSTOMER_ID, Ptc::ATTR_DEPTH);
        $mapByTeam = $this->mapByTeams($compressedPtc, Ptc::ATTR_CUSTOMER_ID, Ptc::ATTR_PARENT_ID);
        $rankIdMgr = $this->hlpRank->getIdByCode(Def::RANK_MANAGER);
        foreach ($mapByDepth as $level) {
            foreach ($level as $custId) {
                /* compose data for one customer */
                $custData = $mapById[$custId];
                $parentId = $custData[Ptc::ATTR_PARENT_ID];
                $pvOwn = isset($mapPv[$custId]) ? $mapPv[$custId] : 0;
                $pv = $custData[Ptc::ATTR_PV];
                $tv = $custData[Ptc::ATTR_TV];
                $resultEntry = [
                    Oi::ATTR_SCHEME => $scheme,
                    Oi::ATTR_CUSTOMER_ID => $custId,
                    Oi::ATTR_PARENT_ID => $parentId,
                    Oi::ATTR_PV => $pv,
                    Oi::ATTR_TV => $tv,
                    Oi::ATTR_OV_LEG_MAX => 0,
                    Oi::ATTR_OV_LEG_SECOND => 0,
                    Oi::ATTR_OV_LEG_SUMMARY => 0
                ];
                /* calculate legs */
                $isQualifiedCust = $this->hlpIsQualified->exec([
                    \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_CUST_ID => $custId,
                    \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_PV => $pvOwn,
                    \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_TV => $tv,
                    \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_SCHEME => $scheme,
                    \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_CFG_PARAMS => $cfgParams
                ]);
                if ($isQualifiedCust) {
                    /* this is qualified manager, calculate MAX leg, second leg and summary leg */
                    if (isset($mapByTeam[$custId])) {
                        /* this customer has downline subtrees */
                        $team = $mapByTeam[$custId];
                        $legMax = $legSecond = $legSummary = 0;
                        foreach ($team as $memberId) {
                            $ovMember = $mapById[$memberId][Ptc::ATTR_OV];
                            if ($ovMember > $legMax) {
                                /* update MAX leg */
                                $legSummary += $legSecond;
                                $legSecond = $legMax;
                                $legMax = $ovMember;
                            } elseif ($ovMember > $legSecond) {
                                /* update second leg */
                                $legSummary += $legSecond;
                                $legSecond = $ovMember;
                            } else {
                                $legSummary += $ovMember;
                            }
                        }
                        /* update legs */
                        $resultEntry[Oi::ATTR_OV_LEG_MAX] = $legMax;
                        $resultEntry[Oi::ATTR_OV_LEG_SECOND] = $legSecond;
                        $resultEntry[Oi::ATTR_OV_LEG_SUMMARY] = $legSummary;
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
                /* re-link parent */
                $parentData = $mapById[$parentId];
                $parentPvOwn = isset($mapPv[$parentId]) ? $mapPv[$parentId] : 0;
                $parentTv = $parentData[Ptc::ATTR_TV];
                $isQualifiedParent = $this->hlpIsQualified->exec([
                    \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_CUST_ID => $parentId,
                    \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_PV => $parentPvOwn,
                    \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_TV => $parentTv,
                    \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_SCHEME => $scheme,
                    \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_CFG_PARAMS => $cfgParams
                ]);
                if (!$isQualifiedParent) {
                    /* parent is not qualified, move this customer up to the closest qualified parent */
                    $path = $custData[Ptc::ATTR_PATH];
                    $parents = $this->toolDwnlTree->getParentsFromPathReversed($path);
                    $foundParentId = null;
                    foreach ($parents as $newParentId) {
                        $newParentData = $mapById[$newParentId];
                        $newParentPvOwn = isset($mapPv[$newParentId]) ? $mapPv[$newParentId] : 0;
                        $newParentTv = $newParentData[Ptc::ATTR_TV];

                        $isQualifiedNewParent = $this->hlpIsQualified->exec([
                            \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_CUST_ID => $newParentId,
                            \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_PV => $newParentPvOwn,
                            \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_TV => $newParentTv,
                            \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_SCHEME => $scheme,
                            \Praxigento\BonusHybrid\Helper\Calc\IsQualified::OPT_CFG_PARAMS => $cfgParams
                        ]);
                        if ($isQualifiedNewParent) {
                            $foundParentId = $newParentId;
                            break;
                        }
                    }
                    unset($parents);
                    if (is_null($foundParentId)) {
                        /* no qualified parent up to the root, make this customer as root customer  */
                        $resultEntry[Oi::ATTR_PARENT_ID] = $custId;
                    } else {
                        $resultEntry[Oi::ATTR_PARENT_ID] = $foundParentId;
                    }
                }
                /* add entry to results */
                $result[$custId] = $resultEntry;
            }
        }
        unset($mapByDepth);
        unset($mapByTeam);
        unset($mapById);
        /* MOBI-629: add init rank for un-ranked entries */
        $defRankId = $this->hlpRank->getIdByCode(Def::RANK_DISTRIBUTOR);;
        foreach ($result as $key => $item) {
            if (!isset($item[Oi::ATTR_RANK_ID])) {
                $item[Oi::ATTR_RANK_ID] = $defRankId;
                $result[$key] = $item;
            }
        }
        return $result;
    }
}