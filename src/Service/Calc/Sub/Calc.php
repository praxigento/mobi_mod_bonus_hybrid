<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Calc\Sub;

use Praxigento\Accounting\Data\Entity\Account;
use Praxigento\Accounting\Data\Entity\Transaction;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Defaults as Def;
use Praxigento\BonusHybrid\Entity\Cfg\Override as CfgOverride;
use Praxigento\BonusHybrid\Entity\Cfg\Param as CfgParam;
use Praxigento\BonusHybrid\Entity\Compression\Oi as OiCompress;
use Praxigento\BonusHybrid\Entity\Compression\Ptc as PtcCompress;
use Praxigento\Downline\Data\Entity\Customer;
use Praxigento\Downline\Data\Entity\Snap;
use Praxigento\Downline\Service\Snap\Request\ExpandMinimal as DownlineSnapExtendMinimalRequest;

class Calc
{
    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\Traits\TMap {
        mapById as protected;
        mapByTeams as protected;
        mapByTreeDepthDesc as protected;
    }

    /**
     * A_... - labels to access data in associative arrays.
     */
    const A_CUST_ID = 'CustId';
    const A_ENTRIES = 'Entries';
    const A_ORDR_ID = 'OrderId';
    const A_OTHER_ID = 'OtherId';
    const A_PV = 'Pv';
    const A_RANK_ID = 'RankId';
    const A_VALUE = 'Value';

    const COMPRESSED_PARENT = 'prc';
    const COMPRESSED_PV = 'pvc';
    const DATA_PV = 'pv';
    const DATA_SNAP = 'snap';
    /** @var  int MOBI-629 */
    protected $cachedOiDefRankId;
    /** @var    \Praxigento\Downline\Service\ISnap */
    protected $callDownlineSnap;
    /** @var \Praxigento\BonusBase\Helper\IRank */
    protected $hlpRank;
    /** @var \Praxigento\BonusHybrid\Helper\SignupDebit\GetCustomersIds */
    protected $hlpSignupDebitCust;
    /** @var \Psr\Log\LoggerInterface */
    protected $logger;
    /** @var \Praxigento\Downline\Repo\Entity\ICustomer */
    protected $repoDwnlCust;
    /** @var  \Praxigento\BonusBase\Repo\Entity\IRank */
    protected $repoRank;
    /** @var \Praxigento\Downline\Tool\ITree */
    protected $toolDownlineTree;
    /** @var \Praxigento\Core\Tool\IFormat */
    protected $toolFormat;
    /** @var  \Praxigento\BonusHybrid\Tool\IScheme */
    protected $toolScheme;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\Core\Tool\IFormat $toolFormat,
        \Praxigento\Downline\Tool\ITree $toolTree,
        \Praxigento\BonusHybrid\Tool\IScheme $toolScheme,
        \Praxigento\BonusBase\Helper\IRank $hlpRank,
        \Praxigento\BonusHybrid\Helper\SignupDebit\GetCustomersIds $hlpSignupDebitCust,
        \Praxigento\Downline\Repo\Entity\ICustomer $repoDwnlCust,
        \Praxigento\Downline\Service\ISnap $callDownlineSnap
    ) {
        $this->logger = $logger;
        $this->toolFormat = $toolFormat;
        $this->toolScheme = $toolScheme;
        $this->toolDownlineTree = $toolTree;
        $this->hlpRank = $hlpRank;
        $this->hlpSignupDebitCust = $hlpSignupDebitCust;
        $this->repoDwnlCust = $repoDwnlCust;
        $this->callDownlineSnap = $callDownlineSnap;
    }

    public function bonusCourtesy($compressPtc, $percentCourtesy, $levelsPersonal, $levelsTeam)
    {
        $result = [];
        $mapDataById = $this->mapById($compressPtc, PtcCompress::ATTR_CUSTOMER_ID);
        $mapTeams = $this->mapByTeams($compressPtc, PtcCompress::ATTR_CUSTOMER_ID, PtcCompress::ATTR_PARENT_ID);
        foreach ($compressPtc as $item) {
            $custId = $item[PtcCompress::ATTR_CUSTOMER_ID];
            $custScheme = $this->toolScheme->getSchemeByCustomer($item);
            if (
                isset($mapTeams[$custId]) &&
                ($custScheme == Def::SCHEMA_DEFAULT)
            ) {
                $custRef = $item[Customer::ATTR_HUMAN_REF];
                $tv = $mapDataById[$custId][PtcCompress::ATTR_TV];
                $tv = $this->toolScheme->getForcedTv($custId, $custScheme, $tv);
                $percentTeam = $this->getLevelPercent($tv, $levelsTeam);
                $this->logger->debug("Customer #$custId ($custRef) has $tv TV and $percentTeam% as max percent.");
                /* for all front team members of the customer */
                $team = $mapTeams[$custId];
                foreach ($team as $memberId) {
                    $pv = $mapDataById[$memberId][PtcCompress::ATTR_PV];
                    if ($pv > 0) {
                        $memberData = $mapDataById[$memberId];
                        $memberRef = $memberData[Customer::ATTR_HUMAN_REF];
                        $percentPv = $this->getLevelPercent($pv, $levelsPersonal);
                        $percentDelta = $percentTeam - $percentPv;
                        if ($percentDelta > Cfg::DEF_ZERO) {
                            $this->logger->debug("Member $memberId ($memberRef) has $pv PV, percent: $percentPv%, delta: $percentDelta% and does not give bonus part to customer #$custId ($custRef).");
                        } else {
                            $bonusPart = $this->toolFormat->roundBonus($pv * $percentCourtesy);
                            $result[$custId][] = [self::A_VALUE => $bonusPart, self::A_OTHER_ID => $memberId];
                            $this->logger->debug("$bonusPart is a Courtesy Bonus part for customer #$custId ($custRef) from front member #$memberId ($memberRef) - pv: $pv, percent: $percentPv%, delta: $percentDelta%.");
                        }
                    }
                }
            }
        }
        unset($mapDataById);
        unset($mapTeams);
        return $result;
    }

    public function bonusInfinity($compressOi, $scheme, $cfgParams)
    {
        $result = []; // [$custId=>[A_PV=>..., A_ENTRIES=>[A_VALUE=>..., A_OTHER_ID=>...]], ...]
        $mapById = $this->mapById($compressOi, OiCompress::ATTR_CUSTOMER_ID);
        $mapTreeExp = $this->getExpandedTreeSnap(
            $compressOi,
            OiCompress::ATTR_CUSTOMER_ID,
            OiCompress::ATTR_PARENT_ID
        );
        $ibPercentMax = $this->getMaxPercentForInfinityBonus($cfgParams);
        /* get MLM ID map for logging */
        $dwnl = $this->repoDwnlCust->get();
        $mapDwnlById = $this->mapById($dwnl, Customer::ATTR_CUSTOMER_ID);
        /* process downline tree */
        foreach ($mapTreeExp as $custId => $treeData) {
            $customerData = $mapById[$custId];
            $custMlmId = $mapDwnlById[$custId][Customer::ATTR_HUMAN_REF];
            $pv = $customerData[OiCompress::ATTR_PV];
            if ($pv > Cfg::DEF_ZERO) {
                $path = $treeData[Snap::ATTR_PATH];
                $parents = $this->toolDownlineTree->getParentsFromPathReversed($path);
                $prevParentIbPercent = 0;
                $ibPercentDelta = $ibPercentMax - $prevParentIbPercent;
                $isFirstGen = true; // first generation customers should not have an infinity bonus
                foreach ($parents as $parentId) {
                    $parentData = $mapById[$parentId];
                    $parentMlmId = $mapDwnlById[$parentId][Customer::ATTR_HUMAN_REF];
                    $parentRankId = $parentData[OiCompress::ATTR_RANK_ID];
                    $parentScheme = $this->toolScheme->getSchemeByCustomer($parentData);
                    /* should parent get an Infinity bonus? */
                    $hasInfPercent =
                        isset($cfgParams[$scheme][$parentRankId]) &&
                        isset($cfgParams[$scheme][$parentRankId][CfgParam::ATTR_INFINITY]) &&
                        ($cfgParams[$scheme][$parentRankId][CfgParam::ATTR_INFINITY] > 0);
                    $hasParentRightScheme = ($parentScheme == $scheme);
                    if ($hasInfPercent && $hasParentRightScheme && !$isFirstGen) {
                        $ibPercent = $cfgParams[$scheme][$parentRankId][CfgParam::ATTR_INFINITY];
                        /* compare ranks and interrupt if next parent has the same rank or lower */
                        $shouldInterrupt = $this->shouldInterruptInfinityBonus(
                            $prevParentIbPercent,
                            $ibPercent,
                            $cfgParams
                        );
                        /* this parent should not get infinity bonus (has the same rank or lower)*/
                        if ($shouldInterrupt) continue;
                        /* all infinity bonus is distributed, break the loop */
                        if ($ibPercentDelta <= Cfg::DEF_ZERO) break;

                        /* calculate bonus value and add to current parent */
                        if (!isset($result[$parentId])) {
                            $result[$parentId] = [self::A_PV => 0, self::A_ENTRIES => []];
                        }
                        $result[$parentId][self::A_PV] += $pv;
                        $ibPercent = $cfgParams[$scheme][$parentRankId][CfgParam::ATTR_INFINITY];
                        $percent = ($ibPercent <= $ibPercentDelta) ? $ibPercent : $ibPercentDelta;
                        $bonus = $this->toolFormat->roundBonus($pv * $percent);
                        $result[$parentId][self::A_ENTRIES][] = [
                            self::A_VALUE => $bonus,
                            self::A_OTHER_ID => $custId
                        ];
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

    public function bonusOverride($compressOi, $scheme, $cfgOverride)
    {
        $result = [];
        $mapById = $this->mapById($compressOi, OiCompress::ATTR_CUSTOMER_ID);
        $mapTeams = $this->mapByTeams($compressOi, OiCompress::ATTR_CUSTOMER_ID, OiCompress::ATTR_PARENT_ID);
        /* populate compressed data with depth & path values */
        $mapTreeExp = $this->getExpandedTreeSnap(
            $compressOi,
            OiCompress::ATTR_CUSTOMER_ID,
            OiCompress::ATTR_PARENT_ID
        );
        $mapByDepthDesc = $this->mapByTreeDepthDesc($mapTreeExp, Snap::ATTR_CUSTOMER_ID, Snap::ATTR_DEPTH);
        /* scan all levels starting from the bottom and collect PV by generations */
        $mapGenerations = $this->mapByGeneration($mapByDepthDesc,
            $mapTreeExp); // [ $custId=>[$genId => $totalPv, ...], ... ]
        $defRankId = $this->hlpRank->getIdByCode(Def::RANK_DISTRIBUTOR);
        /* scan all customers and calculate bonus values */
        foreach ($compressOi as $custData) {
            $custId = $custData[OiCompress::ATTR_CUSTOMER_ID];
            $custRef = $custData[Customer::ATTR_HUMAN_REF];
            $rankId = $custData[OiCompress::ATTR_RANK_ID];
            $custScheme = $this->toolScheme->getSchemeByCustomer($custData);
            if (
                ($rankId != $defRankId) &&
                ($custScheme == $scheme)
            ) {
                /* this is qualified manager */
                $this->logger->debug("Customer #$custId (#$custRef ) from scheme '$custScheme' is qualified to rank #$rankId.");
                if (isset($cfgOverride[$scheme][$rankId])) {
                    $cfgOvrEntry = $cfgOverride[$scheme][$rankId];
                    // calculate bonus value for $custId according rank configuration
                    $bonusData = $this->calcOverrideBonusByRank($custId, $cfgOvrEntry, $mapGenerations, $mapById);
                    $entry = [self::A_CUST_ID => $custId, self::A_RANK_ID => $rankId, self::A_ENTRIES => $bonusData];
                    $result[] = $entry;
                } else {
                    $this->logger->error("There is incomplete override bonus configuration for scheme '$scheme' and rank #$rankId. ");
                }
            }
        }
        unset($mapGenerations);
        unset($mapByDepthDesc);
        unset($mapTreeExp);
        unset($mapTeams);
        unset($mapById);
        return $result;
    }

    /**
     * Walk through the compressed downline tree and calculate Personal bonus on DEFAULT scheme.
     *
     * @param $compressPtc
     * @param $levels
     *
     * @return array [ ['custId'=>$v, 'amount'=>$v], ... ]
     */
    public function bonusPersonalDef($compressPtc, $levels)
    {
        $result = [];
        foreach ($compressPtc as $one) {
            $custId = $one[PtcCompress::ATTR_CUSTOMER_ID];
            $pvValue = $one[PtcCompress::ATTR_PV];
            $scheme = $this->toolScheme->getSchemeByCustomer($one);
            if ($scheme == Def::SCHEMA_DEFAULT) {
                $bonusValue = $this->calcBonusValue($pvValue, $levels);
                if ($bonusValue > 0) {
                    $result[] = [self::A_CUST_ID => $custId, self::A_VALUE => $bonusValue];
                }
            }
        }
        return $result;
    }

    /**
     * Calculate Personal bonus according to EU scheme (rebates).
     *
     * @param $treeSnap
     * @param $compressPtc
     * @param $orders
     *
     * @return array [ ['custId'=>$v, 'ordrId'=>$v, 'amount'=>$v], ... ]
     */
    public function bonusPersonalEu($treeSnap, $compressPtc, $orders)
    {
        $result = [];
        $mapFlatById = $this->mapById($treeSnap, Snap::ATTR_CUSTOMER_ID);
        $mapCompressById = $this->mapById($compressPtc, PtcCompress::ATTR_CUSTOMER_ID);
        foreach ($orders as $custId => $items) {
            foreach ($items as $orderId => $amount) {
                $bonus = $amount * Def::REBATE_PERCENT;
                $bonus = $this->toolFormat->roundBonus($bonus);
                if (!isset($mapCompressById[$custId])) {
                    /* this is not qualified customer */
                    $bonus = $this->toolFormat->roundBonus($bonus / 2);
                    $result[] = [
                        self::A_CUST_ID => $custId,
                        self::A_ORDR_ID => $orderId,
                        self::A_VALUE => $bonus
                    ];
                    $this->logger->debug("Personal bonus (EU) '$bonus' is paid to unqualified customer #$custId for order #$orderId.");
                    $path = $mapFlatById[$custId][Snap::ATTR_PATH];
                    $parents = $this->toolDownlineTree->getParentsFromPathReversed($path);
                    foreach ($parents as $parentId) {
                        if (isset($mapCompressById[$parentId])) {
                            $result[] = [
                                self::A_CUST_ID => $parentId,
                                self::A_ORDR_ID => $orderId,
                                self::A_VALUE => $bonus
                            ];
                            $this->logger->debug("Personal bonus (EU) '$bonus' is paid to qualified parent #$parentId of the unqualified customer #$custId for order #$orderId.");
                            break;
                        }
                    }
                } else {
                    /* this is qualified customer */
                    $result[] = [
                        self::A_CUST_ID => $custId,
                        self::A_ORDR_ID => $orderId,
                        self::A_VALUE => $bonus
                    ];
                    $this->logger->debug("Personal bonus (EU) '$bonus' is paid to qualified customer #$custId for order #$orderId.");
                }
            }
        }
        return $result;
    }

    /**
     * Calculate Team Bonus values.
     *
     * @param $compressPtc
     * @param $levelsPersonal
     * @param $levelsTeam
     * @param $courtesyPct
     *
     * @return array
     */
    public function bonusTeamDef($compressPtc, $levelsPersonal, $levelsTeam, $courtesyPct)
    {
        $result = [];
        $mapDataById = $this->mapById($compressPtc, PtcCompress::ATTR_CUSTOMER_ID);
        $mapTeams = $this->mapByTeams($compressPtc, PtcCompress::ATTR_CUSTOMER_ID, PtcCompress::ATTR_PARENT_ID);
        $pctPbMax = $this->getMaxPercentForPersonalBonus($levelsPersonal);
        foreach ($mapDataById as $custId => $custData) {
            $custData = $mapDataById[$custId];
            $custRef = $custData[Customer::ATTR_HUMAN_REF];
            $scheme = $this->toolScheme->getSchemeByCustomer($custData);
            /* only DEFAULT-schema customers may apply to Team Bonus */
            $pv = $custData[PtcCompress::ATTR_PV];
            /* customer has PV to calculate bonus */
            if ($pv > Cfg::DEF_ZERO) {
                /* personal % for this customer */
                $pctPb = $this->getLevelPercent($pv, $levelsPersonal);
                /* check courtesy bonus (if %PB=MAX, 5% to the first parent) */
                if (abs($pctPbMax - $pctPb) < Cfg::DEF_ZERO) {
                    /* there is no team bonus */
                    continue;
                }
                /* traverse up to tree root to calculate team bonus values */
                $path = $custData[PtcCompress::ATTR_PATH];
                $parents = $this->toolDownlineTree->getParentsFromPathReversed($path);
                /* init undistributed delta: 20% - 5% */
                $pctPbLeft = $pctPbMax - $pctPb;
                /* ... and distributed amount: 5% */
                $pctPbDone = $pctPb;
                $this->logger->debug("TB: Customer #$custId(ref. #$custRef) has $pv PV and %PB=$pctPb, "
                    . "%left=$pctPbLeft, %done=$pctPbDone");
                /* set "isFather" flag for courtesy bonus */
                $isFather = true;
                foreach ($parents as $parentId) {
                    /* current customer has not MAX PB% or there is undistributed delta yet */
                    if ($pctPbLeft > Cfg::DEF_ZERO) {
                        /* get team qualification percent for  parent */
                        $parentData = $mapDataById[$parentId];
                        $parentRef = $parentData[Customer::ATTR_HUMAN_REF];
                        $parentScheme = $this->toolScheme->getSchemeByCustomer($parentData);
                        $tv = $parentData[PtcCompress::ATTR_TV];
                        $tvForced = $this->toolScheme->getForcedTv($parentId, $scheme, $tv);
                        if ($tvForced > $tv) {
                            $this->logger->debug("TB: Customer #$parentId (ref. #$parentRef ) has forced qualification with TV=$tvForced.");
                            $tv = $tvForced;
                        }
                        /* get TB% for current parent and calc available % for current parent */
                        $pctTb = $this->getLevelPercent($tv, $levelsTeam);
                        $pctTbAvlbDelta = $pctTb - $pctPbDone;
                        if ($pctTbAvlbDelta > Cfg::DEF_ZERO) {
                            /* parent's TV % should be more then customer's PV % */

                            /* EU parent should not get more then courtesy % */
                            if ($parentScheme != Def::SCHEMA_DEFAULT) {
                                if ($isFather) {
                                    /* Courtesy bonus will calculate in other process, just decrease % left */
                                    $pctPbLeft = number_format($pctPbLeft - $courtesyPct, 2);
                                    $pctPbDone = number_format($pctPbDone + $courtesyPct, 2);
                                    $this->logger->debug("TB: Customer #$parentId (ref. #$parentRef) has "
                                        . "scheme=$parentScheme and is 'father' for #$custId ($custRef). "
                                        . "Decrease %TB on %courtesy=$courtesyPct to %left=$pctPbLeft, %done=$pctPbDone.");
                                }
                            }

                            if (
                                ($pctTbAvlbDelta > $pctPbLeft) ||
                                abs($pctTbAvlbDelta - $pctPbLeft) < Cfg::DEF_ZERO // this is ">="
                            ) {
                                /* there is undistributed PB% */
                                /* parent's TV allows him to get all team bonus from this customer */
                                if ($parentScheme == Def::SCHEMA_DEFAULT) {
                                    $bonus = $this->toolFormat->roundBonus($pv * $pctPbLeft);
                                    $result[] = [
                                        self::A_CUST_ID => $parentId,
                                        self::A_VALUE => $bonus,
                                        self::A_OTHER_ID => $custId
                                    ];
                                    $this->logger->debug("TB: Customer #$parentId ($parentRef) has TV=$tv, %TB=$pctTb,"
                                        . " and get '$bonus' ($pctPbLeft%) as DEFAULT Team Bonus from "
                                        . "downline customer #$custId ($custRef) with PV=$pv and "
                                        . "%PB=$pctPb");
                                } else {
                                    $this->logger->debug("TB: Customer #$parentId ($parentRef) has TV=$tv, %TB=$pctTb,"
                                        . " but cannot get DEFAULT Team Bonus from "
                                        . "downline customer #$custId ($custRef) with PV=$pv and "
                                        . "%PB=$pctPb because he is not from DEFAULT scheme.");
                                }
                                $pctPbLeft = number_format($pctPbLeft - $pctTbAvlbDelta, 2);
                                $pctPbDone = number_format($pctPbDone + $pctTbAvlbDelta, 2);
                                $this->logger->debug("TB: All bonus is distributed (%left=$pctPbLeft, %done=$pctPbDone).");
                                break;
                            } else {
                                /* parent's TV allows him to get only part of the team bonus from this customer */
                                if ($parentScheme == Def::SCHEMA_DEFAULT) {
                                    $bonus = $this->toolFormat->roundBonus($pv * $pctTbAvlbDelta);
                                    $result[] = [
                                        self::A_CUST_ID => $parentId,
                                        self::A_VALUE => $bonus,
                                        self::A_OTHER_ID => $custId
                                    ];
                                    $pctPbLeft = number_format($pctPbLeft - $pctTbAvlbDelta, 2);
                                    $pctPbDone = number_format($pctPbDone + $pctTbAvlbDelta, 2);
                                    $this->logger->debug("TB: Customer #$parentId ($parentRef) has TV=$tv, %TB=$pctTb,"
                                        . " and get '$bonus' ($pctTbAvlbDelta%) as DEFAULT Team Bonus from "
                                        . "downline customer #$custId ($custRef) with PV=$pv and "
                                        . "%PB=$pctPb, %left=$pctPbLeft%, %done=$pctPbDone.");
                                } else {
                                    $pctPbLeft = number_format($pctPbLeft - $pctTbAvlbDelta, 2);
                                    $pctPbDone = number_format($pctPbDone + $pctTbAvlbDelta, 2);
                                    $this->logger->debug("TB: Customer #$parentId ($parentRef) has TV=$tv, %TB=$pctTb,"
                                        . " but cannot get DEFAULT Team Bonus from "
                                        . "downline customer #$custId ($custRef) with PV=$pv and "
                                        . "%PB=$pctPb because he is not from DEFAULT scheme."
                                        . " Available: %left=$pctPbLeft%, %done=$pctPbDone.");
                                }

                            }
                        } else {
                            /* this parent has %TB less then distributed %PB and should not be granted  */
                            $this->logger->debug("TB: Customer #$parentId (ref. #$parentRef) has TV=$tv, "
                                . "%TB=$pctTb is not more then %done=$pctPbDone and should not "
                                . "get Team Bonus from #$custId ($custRef).");
                            if ($isFather) {
                                /* reduce delta to courtesy bonus percent if parent is not "father" */
                                $pctPbLeft = number_format($pctPbLeft - $courtesyPct, 2);
                                $pctPbDone = number_format($pctPbDone + $courtesyPct, 2);
                                $this->logger->debug("Customer #$parentId ($parentRef) is 'father' for the "
                                    . "customer #$custId ($custRef) %left is decreased on "
                                    . "Courtesy Bonus percent (new value: $pctPbLeft, %done=$pctPbDone).");
                            }
                        }
                    } else {
                        /* this customer has max Personal Bonus percent, no Team Bonus is possible */
                        $this->logger->debug("TB: Customer #$custId (ref. #$custRef ) has maximal Personal Bonus %.");
                        break;
                    }
                    /* next parent is not father */
                    $isFather = false;
                }
            } else {
                $this->logger->debug("TB: Customer #$custId (ref. #$custRef ) has no PV ($pv PV) and could not participate in DEFAULT Team Bonus.");
            }
        }
        unset($mapDataById);
        unset($mapTeams);
        return $result;
    }

    public function bonusTeamEu($compressPtc, $teamBonusPercent)
    {
        $result = [];
        $mapDataById = $this->mapById($compressPtc, PtcCompress::ATTR_CUSTOMER_ID);
        foreach ($mapDataById as $custId => $custData) {
            $custData = $mapDataById[$custId];
            $custRef = $custData[Customer::ATTR_HUMAN_REF];
            $pv = $custData[PtcCompress::ATTR_PV];

            $parentId = $custData[PtcCompress::ATTR_PARENT_ID];
            $parentData = $mapDataById[$parentId];
            $parentRef = $parentData[Customer::ATTR_HUMAN_REF];
            $scheme = $this->toolScheme->getSchemeByCustomer($parentData);
            if ($scheme == Def::SCHEMA_EU) {
                $pvParent = $parentData[PtcCompress::ATTR_PV];
                if ($pvParent > (Def::PV_QUALIFICATION_LEVEL_EU - Cfg::DEF_ZERO)) {
                    $bonus = $this->toolFormat->roundBonus($pv * $teamBonusPercent);
                    if ($bonus > Cfg::DEF_ZERO) {
                        $result[] = [
                            self::A_CUST_ID => $parentId,
                            self::A_VALUE => $bonus,
                            self::A_OTHER_ID => $custId
                        ];
                    }
                    $this->logger->debug("parent #$parentId (ref. #$parentRef) has '$bonus' as EU Team Bonus from downline customer #$custId (ref. #$custRef ).");
                } else {
                    $this->logger->debug("parent #$parentId (ref. #$parentRef) does not qualified t oget EU Team Bonus from downline customer #$custId (ref. #$custRef ).");
                }
            } else {
                $this->logger->debug("Parent #$parentId (ref. #$parentRef) has incompatible scheme '$scheme' for EU Team Bonus.");
            }
        }
        unset($mapDataById);
        return $result;
    }

    /**
     * @param $value PV amount for period to calculate bonus amount
     * @param $levels Personal Bonus levels and percents.
     *
     * @return string
     */
    private function calcBonusValue($value, $levels)
    {
        $mult = 0;
        foreach ($levels as $level => $percent) {
            if ($value < $level) {
                break;
            } elseif ($value == $level) {
                $mult = $percent;
                break;
            }
            $mult = $percent;
        }
        $bonus = $value * $mult;
        $result = $this->toolFormat->roundBonus($bonus);
        return $result;
    }

    /**
     * ATTENTION: this method is public just for PHPUnit testing.
     *
     * @param $custId int Customer ID
     * @param $cfgOvr array override bonus configuration parameters for the customer
     * @param $mapGen array generations mapping
     * @param $mapById array customer data by ID mapping
     *
     * @return number
     */
    public function calcOverrideBonusByRank($custId, $cfgOvr, $mapGen, $mapById)
    {
        $result = [];
        if (isset($mapGen[$custId])) {
            $generations = $mapGen[$custId];
            /* this customer has generations in downline */
            foreach ($cfgOvr as $gen => $cfgData) {
                $percent = $cfgData[CfgOverride::ATTR_PERCENT];
                if ($percent > 0) {
                    if (isset($generations[$gen])) {
                        /* this generation exists for the customer */
                        $team = $mapGen[$custId][$gen];
                        foreach ($team as $childId) {
                            $childData = $mapById[$childId];
                            $pv = $childData[OiCompress::ATTR_PV];
                            $bonus = $this->toolFormat->roundBonus($pv * $percent);
                            $this->logger->debug("Customer #$custId has '$pv' PV for '$gen' generation and '$bonus' as override bonus part from child #$childId .");
                            $result[] = [
                                self::A_VALUE => $bonus,
                                self::A_OTHER_ID => $childId
                            ];
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Get downline snap data from compressed data ([$custId=>[0=>$pv, 1=>$parentId], ...]).
     *
     * @param $compressedData
     *
     * @return array [[$custId, $parentId, $depth, $path], ...]
     */
    private function composeSnapUpdates($compressedData)
    {
        $req = new DownlineSnapExtendMinimalRequest();
        /* convert to [$customer=>parent, ... ] form */
        $converted = [];
        foreach ($compressedData as $custId => $data) {
            /* 0 - PV, 1 - parentId */
            $converted[$custId] = $data[1];
        }
        $req->setTree($converted);
        $resp = $this->callDownlineSnap->expandMinimal($req);
        unset($converted);
        $result = $resp->getSnapData();
        return $result;
    }


    /**
     * Process Downline Tree snapshot, customer data and PV related transactions and compose data for compressed tree.
     *
     * @param $treeSnap array downline tree snapshot: [ $custId => [$customer_id, $parent_id, $depth, $path], ...].
     * @param $customers array [ [$custId, $humanRef, $countryCode], ...]
     * @param $trans array of the PV related transactions.
     *
     * @return array data to save into prxgt_bon_hyb_compress
     */
    public function compressPtc($treeSnap, $customers, $trans)
    {
        $qLevels = $this->toolScheme->getQualificationLevels();
        $forcedCustomers = $this->toolScheme->getForcedQualificationCustomersIds();
        $signupDebitCustomers = $this->hlpSignupDebitCust->exec();
        $this->logger->info("PTC Compression parameters:" .
            " qualification levels=" . var_export($qLevels, true)
            . ", forced customers: " . var_export($forcedCustomers, true));
        /* array with results: [$customerId => [$pvCompressed, $parentCompressed], ... ]*/
        $compressedTree = [];
        $mapCustomer = $this->mapById($customers, Customer::ATTR_CUSTOMER_ID);
        $mapPv = $this->mapByPv($trans, Account::ATTR_CUST_ID, Transaction::ATTR_VALUE);
        $mapDepth = $this->mapByTreeDepthDesc($treeSnap, Snap::ATTR_CUSTOMER_ID, Snap::ATTR_DEPTH);
        $mapTeams = $this->mapByTeams($treeSnap, Snap::ATTR_CUSTOMER_ID, Snap::ATTR_PARENT_ID);
        foreach ($mapDepth as $depth => $levelCustomers) {
            foreach ($levelCustomers as $custId) {
                $pv = isset($mapPv[$custId]) ? $mapPv[$custId] : 0;
                $parentId = $treeSnap[$custId][Snap::ATTR_PARENT_ID];
                $custData = $mapCustomer[$custId];
                $scheme = $this->toolScheme->getSchemeByCustomer($custData);
                $level = $qLevels[$scheme]; // qualification level for current customer
                $isForced = in_array($custId, $forcedCustomers);
                $isSignupDebit = in_array($custId, $signupDebitCustomers);
                if (($pv >= $level) || $isForced || $isSignupDebit) {
                    if (isset($compressedTree[$custId])) {
                        $pvExist = $compressedTree[$custId][0];
                        $pvNew = $pv + $pvExist;
                        $compressedTree[$custId] = [$pvNew, $parentId];
                    } else {
                        $compressedTree[$custId] = [$pv, $parentId];
                    }
                } else {
                    /* move PV up to the closest qualified parent (current customer's level is used for qualification) */
                    $path = $treeSnap[$custId][Snap::ATTR_PATH];
                    $parents = $this->toolDownlineTree->getParentsFromPathReversed($path);
                    $foundParentId = null;
                    foreach ($parents as $newParentId) {
                        $pvParent = isset($mapPv[$newParentId]) ? $mapPv[$newParentId] : 0;
                        if (
                            ($pvParent >= $level) ||
                            (in_array($newParentId, $forcedCustomers))
                        ) {
                            $foundParentId = $newParentId;
                            break;
                        }
                    }
                    unset($parents);
                    /* add PV to this parent */
                    if (
                        !is_null($foundParentId) &&
                        ($pv > 0)
                    ) {
                        if (isset($compressedTree[$foundParentId])) {
                            $pvExist = $compressedTree[$foundParentId][0];
                            $pvNew = $pv + $pvExist;
                            $compressedTree[$foundParentId][0] = $pvNew;
                        } else {
                            $compressedTree[$foundParentId] [0] = $pv;
                        }
                        $this->logger->debug("$pv PV are transferred from customer #$custId to his qualified parent #$foundParentId .");
                    }
                    /* change parent for all siblings of the unqualified customer */
                    if (isset($mapTeams[$custId])) {
                        $team = $mapTeams[$custId];
                        foreach ($team as $memberId) {
                            if (isset($compressedTree[$memberId])) {
                                /* if null set customer own id to indicate root node */
                                $compressedTree[$memberId][1] = is_null($foundParentId) ? $memberId : $foundParentId;
                            }
                        }
                    }
                }
            }
        }
        unset($mapCustomer);
        unset($mapPv);
        unset($mapDepth);
        unset($mapTeams);
        /* compose compressed snapshot data */
        $data = $this->composeSnapUpdates($compressedTree);
        /* add compressed PV data */
        $result = $this->populateCompressedSnapWithPv($data, $compressedTree);
        return $result;
    }

    /**
     * Populate data with depth & path values.
     *
     * @param $data array of customer data with customer ID & parent ID
     * @param $labelCustomerId string label for customerId
     * @param $labelParentId string label for parentId
     *
     * @return array Downline Snap: [ $custId => [customer_id, depth, parent_id, path], ...]
     */
    private function getExpandedTreeSnap($data, $labelCustomerId, $labelParentId)
    {
        /* populate compressed data with depth & path values */
        $tree = [];
        foreach ($data as $one) {
            $custId = $one[$labelCustomerId];
            $parentId = $one[$labelParentId];
            $tree[$custId] = $parentId;
        }
        $reqExt = new DownlineSnapExtendMinimalRequest();
        $reqExt->setTree($tree);
        $respExt = $this->callDownlineSnap->expandMinimal($reqExt);
        $result = $respExt->getSnapData();
        return $result;
    }

    /**
     * @param $value
     * @param $levels array [ $level => $percent, ... ]
     *
     * @return number
     */
    private function getLevelPercent($value, $levels)
    {
        $result = 0;
        foreach ($levels as $level => $percent) {
            if ($value < $level) {
                break;
            }
            $result = $percent;
        }
        return $result;
    }

    private function getMaxPercentForInfinityBonus($cfgParams)
    {
        $result = 0;
        foreach ($cfgParams as $scheme => $params) {
            foreach ($params as $item) {
                if ($item[CfgParam::ATTR_INFINITY] > $result) {
                    $result = $item[CfgParam::ATTR_INFINITY];
                }
            }
        }
        return $result;
    }

    private function getMaxPercentForPersonalBonus($levelsPersonal)
    {
        $result = 0;
        foreach ($levelsPersonal as $item) {
            if ($item > $result) {
                $result = $item;
            }
        }
        return $result;
    }

    /**
     * ATTENTION: this method is public just for PHPUnit testing.
     *
     * @param $compressOiEntry
     * @param $scheme
     * @param $cfgParam array ATTENTION: $cfgParam must be ordered by scheme then by rank DESC!!!
     *
     * @return int
     */
    public function getMaxQualifiedRankId($compressOiEntry, $scheme, $cfgParam)
    {
        $result = null;
        $custId = $compressOiEntry[OiCompress::ATTR_CUSTOMER_ID];
        $forcedRankId = $this->toolScheme->getForcedQualificationRank($custId, $scheme);
        if (is_null($forcedRankId)) {
            /* qualification params: PV & TV */
            $pv = $compressOiEntry[OiCompress::ATTR_PV];
            $tv = $compressOiEntry[OiCompress::ATTR_TV];
            /* qualification params:  legs */
            $legMax = $compressOiEntry[OiCompress::ATTR_OV_LEG_MAX];
            $legSecond = $compressOiEntry[OiCompress::ATTR_OV_LEG_SECOND];
            $legSummary = $compressOiEntry[OiCompress::ATTR_OV_LEG_OTHERS];
            /* sort legs values to use in 3-legs qualification */
            $sorted = [$legMax, $legSecond, $legSummary];
            sort($sorted);
            $sortedMax = $sorted[2];
            $sortedMedium = $sorted[1];
            $sortedMin = $sorted[0];
            /* lookup for the max qualified rank */
            $ranks = $cfgParam[$scheme];
            foreach ($ranks as $rank) {
                /* rank legs values */
                $qpv = $rank[CfgParam::ATTR_QUALIFY_PV];
                $qtv = $rank[CfgParam::ATTR_QUALIFY_TV];
                $ovMax = $rank[CfgParam::ATTR_LEG_MAX];
                $ovMedium = $rank[CfgParam::ATTR_LEG_MEDIUM];
                $ovMin = $rank[CfgParam::ATTR_LEG_MIN];
                if (
                    ($pv >= $qpv) &&
                    ($tv >= $qtv)
                ) {

                    if (($ovMax > Cfg::DEF_ZERO) && ($ovMedium > Cfg::DEF_ZERO) && ($ovMin > Cfg::DEF_ZERO)) {
                        /* use all 3 legs to qualification, compare sorted data */
                        if (($sortedMax >= $ovMax) && ($sortedMedium >= $ovMedium) && ($sortedMin >= $ovMin)) {
                            $result = $rank[CfgParam::ATTR_RANK_ID];
                            break;
                        }
                    } elseif (($ovMax > Cfg::DEF_ZERO) && ($ovMedium > Cfg::DEF_ZERO)) {
                        /* use 2 legs to qualification, compare original data */
                        if (($legMax >= $ovMax) && ($legSecond >= $ovMedium)) {
                            $result = $rank[CfgParam::ATTR_RANK_ID];
                            break;
                        }
                    } elseif ($ovMax > Cfg::DEF_ZERO) {
                        /* use 1 leg to qualification, compare original data */
                        if ($legMax >= $ovMax) {
                            $result = $rank[CfgParam::ATTR_RANK_ID];
                            break;
                        }
                    } elseif (
                        ($ovMax <= Cfg::DEF_ZERO) &&
                        ($ovMedium <= Cfg::DEF_ZERO) &&
                        ($ovMin <= Cfg::DEF_ZERO)
                    ) {
                        /* is qualified by TV & PV only */
                        $result = $rank[CfgParam::ATTR_RANK_ID];
                        break;
                    }
                }
            }
        } else {
            $result = $forcedRankId;
        }
        return $result;
    }

    /**
     * @param $custId
     * @param $pv
     * @param $tv
     * @param $scheme
     * @param $cfgParams
     * @return bool
     *
     * @deprecated see \Praxigento\BonusHybrid\Helper\Calc\IsQualified
     */
    private function isQualifiedManager($custId, $pv, $tv, $scheme, $cfgParams)
    {
        $result = false;
        if (
            ($pv > Cfg::DEF_ZERO) &&
            ($tv > Cfg::DEF_ZERO)
        ) {
            $params = $cfgParams[$scheme];
            foreach ($params as $param) {
                $qpv = $param[CfgParam::ATTR_QUALIFY_PV];
                $qtv = $param[CfgParam::ATTR_QUALIFY_TV];
                if (
                    ($pv >= $qpv) &&
                    ($tv >= $qtv)
                ) {
                    /* this customer is qualified for the rank */
                    $result = true;
                    break;
                }
            }
        }
        if (!$result) {
            /* check forced qualification */
            $rankId = $this->toolScheme->getForcedQualificationRank($custId, $scheme);
            $result = ($rankId > 0);
        }
        return $result;
    }

    /**
     * Generate map of the customer generations.
     *
     * ATTENTION: this method is public just for PHPUnit testing.
     *
     * @param $mapByDepthDesc
     * @param $mapById
     * @param $mapTreeExp
     *
     * @return array [$custId=>[$genNum=>[$childId, ...], ...], ...]
     */
    public function mapByGeneration($mapByDepthDesc, $mapTreeExp)
    {
        $result = []; // [ $custId=>[$genId => $totalPv, ...], ... ]
        foreach ($mapByDepthDesc as $depth => $ids) {
            foreach ($ids as $custId) {
                $path = $mapTreeExp[$custId][Snap::ATTR_PATH];
                $parents = $this->toolDownlineTree->getParentsFromPathReversed($path);
                $level = 0;
                foreach ($parents as $parentId) {
                    $level += 1;
                    if (!isset($result[$parentId])) {
                        $result[$parentId] = [];
                    }
                    if (!isset($result[$parentId][$level])) {
                        $result[$parentId][$level] = [];
                    }
                    $result[$parentId][$level][] = $custId;
                }
            }
        }
        return $result;
    }

    /**
     * Process all items in$data and map total PV sums by customer IDs.
     *
     * ATTENTION: this method is public just for PHPUnit testing.
     *
     * @param $data array transaction or compressed data
     * @param $labelCustId string label for customer id entry
     * @param $labelPv string label for PV entry
     *
     * @return array [$customerId => $pv, ...]
     */
    public function mapByPv($data, $labelCustId, $labelPv)
    {
        $result = [];
        foreach ($data as $one) {
            $customerId = $one[$labelCustId];
            $pv = $one[$labelPv];
            if (isset($result[$customerId])) {
                $result[$customerId] += $pv;
            } else {
                $result[$customerId] = $pv;
            }
        }
        return $result;
    }


    private function populateCompressedSnapWithPv($snap, $calculatedData)
    {
        $result = $snap;
        foreach ($calculatedData as $custId => $data) {
            /* 0 - PV, 1 - parentId */
            $result[$custId][PtcCompress::ATTR_PV] = $data[0];
        }
        return $result;
    }

    /**
     * @param $trans
     *
     * @return array [$accId=>$pvWriteOff, ...]
     */
    public function pvWriteOff($trans)
    {
        $result = [];
        foreach ($trans as $one) {
            $debitAccId = $one[Transaction::ATTR_DEBIT_ACC_ID];
            $creditAccId = $one[Transaction::ATTR_CREDIT_ACC_ID];
            $value = $one[Transaction::ATTR_VALUE];
            if (isset($result[$debitAccId])) {
                $result[$debitAccId] -= $value;
            } else {
                $result[$debitAccId] = -$value;
            }
            if (isset($result[$creditAccId])) {
                $result[$creditAccId] += $value;
            } else {
                $result[$creditAccId] = $value;
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

    public function valueOv($compressPtc)
    {
        $result = [];
        $mapById = $this->mapById($compressPtc, PtcCompress::ATTR_CUSTOMER_ID);
        $mapDepth = $this->mapByTreeDepthDesc($compressPtc, PtcCompress::ATTR_CUSTOMER_ID, PtcCompress::ATTR_DEPTH);
        $mapTeams = $this->mapByTeams($compressPtc, PtcCompress::ATTR_CUSTOMER_ID, PtcCompress::ATTR_PARENT_ID);
        /* get Sign Up Volume Debit customers */
        $signupDebitCustomers = $this->hlpSignupDebitCust->exec();
        foreach ($mapDepth as $depth => $levelCustomers) {
            $this->logger->debug("Process level #$depth of the downline tree.");
            foreach ($levelCustomers as $custId) {
                $ov = $mapById[$custId][PtcCompress::ATTR_PV];
                $isSignupDebit = in_array($custId, $signupDebitCustomers);
                if ($isSignupDebit) {
                    $ov += Def::SIGNUP_DEBIT_PV;
                }
                if (isset($mapTeams[$custId])) {
                    /* add OV from front team members */
                    $team = $mapTeams[$custId];
                    foreach ($team as $memberId) {
                        $ov += $result[$memberId];
                    }
                }
                $result[$custId] = $ov;
            }
        }
        unset($mapPv);
        unset($mapTeams);
        unset($mapDepth);
        return $result;
    }

    public function valueTv($compressPtc)
    {
        $result = [];
        $mapById = $this->mapById($compressPtc, PtcCompress::ATTR_CUSTOMER_ID);
        $mapTeams = $this->mapByTeams($compressPtc, PtcCompress::ATTR_CUSTOMER_ID, PtcCompress::ATTR_PARENT_ID);
        foreach ($compressPtc as $one) {
            $custId = $one[PtcCompress::ATTR_CUSTOMER_ID];
            $tv = $mapById[$custId][PtcCompress::ATTR_PV];
            $this->logger->debug("Customer #$custId has own $tv PV.");
            if (isset($mapTeams[$custId])) {
                $frontTeam = $mapTeams[$custId];
                foreach ($frontTeam as $teamMemberId) {
                    $memberPv = $mapById[$teamMemberId][PtcCompress::ATTR_PV];
                    $tv += $memberPv;
                    $this->logger->debug("$memberPv PV is added to #$custId from member #$teamMemberId.");
                }
            }
            $result[$custId] = $tv;
            $this->logger->debug("Customer #$custId has total $tv TV.");
        }
        unset($mapTeams);
        return $result;
    }
}