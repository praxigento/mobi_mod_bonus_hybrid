<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib\Service\Calc\Sub;

use Praxigento\Accounting\Data\Entity\Account;
use Praxigento\Accounting\Data\Entity\Transaction;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\Bonus\Hybrid\Lib\Defaults as Def;
use Praxigento\Bonus\Hybrid\Lib\Entity\Cfg\Override as CfgOverride;
use Praxigento\Bonus\Hybrid\Lib\Entity\Cfg\Param as CfgParam;
use Praxigento\Bonus\Hybrid\Lib\Entity\Compression\Oi as OiCompress;
use Praxigento\Bonus\Hybrid\Lib\Entity\Compression\Ptc as PtcCompress;
use Praxigento\Downline\Data\Entity\Customer;
use Praxigento\Downline\Data\Entity\Snap;
use Praxigento\Downline\Lib\Service\Snap\Request\ExpandMinimal as DownlineSnapExtendMinimalRequest;

class Calc extends \Praxigento\Core\Lib\Service\Base\Sub\Base {
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
    /** @var    \Praxigento\Downline\Lib\Service\ISnap */
    private $_callDownlineSnap;
    /** @var \Praxigento\Downline\Lib\Tool\ITree */
    private $_toolDownlineTree;
    /** @var  \Praxigento\Bonus\Hybrid\Lib\Tool\IScheme */
    private $_toolScheme;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Praxigento\Bonus\Hybrid\Lib\IToolbox $toolbox,
        \Praxigento\Downline\Lib\Service\ISnap $callDownlineSnap
    ) {
        parent::__construct($logger, $toolbox);
        $this->_callDownlineSnap = $callDownlineSnap;
        $this->_toolScheme = $toolbox->getScheme();
        $this->_toolDownlineTree = $toolbox->getDownlineTree();
    }

    /**
     * @param $value PV amount for period to calculate bonus amount
     * @param $levels Personal Bonus levels and percents.
     *
     * @return string
     */
    private function _calcBonusValue($value, $levels) {
        $mult = 0;
        foreach($levels as $level => $percent) {
            if($value < $level) {
                break;
            } elseif($value == $level) {
                $mult = $percent;
                break;
            }
            $mult = $percent;
        }
        $bonus = $value * $mult;
        $result = $this->_toolbox->getFormat()->roundBonus($bonus);
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
    public function _calcOverrideBonusByRank($custId, $cfgOvr, $mapGen, $mapById) {
        $result = [ ];
        if(isset($mapGen[$custId])) {
            $generations = $mapGen[$custId];
            /* this customer has generations in downline */
            foreach($cfgOvr as $gen => $cfgData) {
                $percent = $cfgData[CfgOverride::ATTR_PERCENT];
                if($percent > 0) {
                    if(isset($generations[$gen])) {
                        /* this generation exists for the customer */
                        $team = $mapGen[$custId][$gen];
                        foreach($team as $childId) {
                            $childData = $mapById[$childId];
                            $pv = $childData[OiCompress::ATTR_PV];
                            $bonus = $this->_toolbox->getFormat()->roundBonus($pv * $percent);
                            $this->_logger->debug("Customer #$custId has '$pv' PV for '$gen' generation and '$bonus' as override bonus part from child #$childId .");
                            $result[] = [
                                self::A_VALUE    => $bonus,
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
    private function _composeSnapUpdates($compressedData) {
        $req = new DownlineSnapExtendMinimalRequest();
        /* convert to [$customer=>parent, ... ] form */
        $converted = [ ];
        foreach($compressedData as $custId => $data) {
            /* 0 - PV, 1 - parentId */
            $converted[$custId] = $data[1];
        }
        $req->setTree($converted);
        $resp = $this->_callDownlineSnap->expandMinimal($req);
        unset($converted);
        $result = $resp->getSnapData();
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
    private function _getExpandedTreeSnap($data, $labelCustomerId, $labelParentId) {
        /* populate compressed data with depth & path values */
        $tree = [ ];
        foreach($data as $one) {
            $custId = $one[$labelCustomerId];
            $parentId = $one[$labelParentId];
            $tree[$custId] = $parentId;
        }
        $reqExt = new DownlineSnapExtendMinimalRequest();
        $reqExt->setTree($tree);
        $respExt = $this->_callDownlineSnap->expandMinimal($reqExt);
        $result = $respExt->getSnapData();
        return $result;
    }

    /**
     * @param $value
     * @param $levels array [ $level => $percent, ... ]
     *
     * @return number
     */
    private function _getLevelPercent($value, $levels) {
        $result = 0;
        foreach($levels as $level => $percent) {
            if($value < $level) {
                break;
            }
            $result = $percent;
        }
        return $result;
    }

    private function _getMaxPercentForInfinityBonus($cfgParams) {
        $result = 0;
        foreach($cfgParams as $scheme => $params) {
            foreach($params as $item) {
                if($item[CfgParam::ATTR_INFINITY] > $result) {
                    $result = $item[CfgParam::ATTR_INFINITY];
                }
            }
        }
        return $result;
    }

    private function _getMaxPercentForPersonalBonus($levelsPersonal) {
        $result = 0;
        foreach($levelsPersonal as $item) {
            if($item > $result) {
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
    public function _getMaxQualifiedRankId($compressOiEntry, $scheme, $cfgParam) {
        $result = null;
        $custId = $compressOiEntry[OiCompress::ATTR_CUSTOMER_ID];
        $forcedRankId = $this->_toolScheme->getForcedQualificationRank($custId, $scheme);
        if(is_null($forcedRankId)) {
            /* qualification params: PV & TV */
            $pv = $compressOiEntry[OiCompress::ATTR_PV];
            $tv = $compressOiEntry[OiCompress::ATTR_TV];
            /* qualification params:  legs */
            $legMax = $compressOiEntry[OiCompress::ATTR_OV_LEG_MAX];
            $legSecond = $compressOiEntry[OiCompress::ATTR_OV_LEG_SECOND];
            $legSummary = $compressOiEntry[OiCompress::ATTR_OV_LEG_SUMMARY];
            /* sort legs values to use in 3-legs qualification */
            $sorted = [ $legMax, $legSecond, $legSummary ];
            sort($sorted);
            $sortedMax = $sorted[2];
            $sortedMedium = $sorted[1];
            $sortedMin = $sorted[0];
            /* lookup for the max qualified rank */
            $ranks = $cfgParam[$scheme];
            foreach($ranks as $rank) {
                /* rank legs values */
                $qpv = $rank[CfgParam::ATTR_QUALIFY_PV];
                $qtv = $rank[CfgParam::ATTR_QUALIFY_TV];
                $ovMax = $rank[CfgParam::ATTR_LEG_MAX];
                $ovMedium = $rank[CfgParam::ATTR_LEG_MEDIUM];
                $ovMin = $rank[CfgParam::ATTR_LEG_MIN];
                if(
                    ($pv >= $qpv) &&
                    ($tv >= $qtv)
                ) {

                    if(($ovMax > Cfg::DEF_ZERO) && ($ovMedium > Cfg::DEF_ZERO) && ($ovMin > Cfg::DEF_ZERO)) {
                        /* use all 3 legs to qualification, compare sorted data */
                        if(($sortedMax >= $ovMax) && ($sortedMedium >= $ovMedium) && ($sortedMin >= $ovMin)) {
                            $result = $rank[CfgParam::ATTR_RANK_ID];
                            break;
                        }
                    } elseif(($ovMax > Cfg::DEF_ZERO) && ($ovMedium > Cfg::DEF_ZERO)) {
                        /* use 2 legs to qualification, compare original data */
                        if(($legMax >= $ovMax) && ($legSecond >= $ovMedium)) {
                            $result = $rank[CfgParam::ATTR_RANK_ID];
                            break;
                        }
                    } elseif($ovMax > Cfg::DEF_ZERO) {
                        /* use 1 leg to qualification, compare original data */
                        if($legMax >= $ovMax) {
                            $result = $rank[CfgParam::ATTR_RANK_ID];
                            break;
                        }
                    } elseif(
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
    public function _mapByGeneration($mapByDepthDesc, $mapTreeExp) {
        $result = [ ]; // [ $custId=>[$genId => $totalPv, ...], ... ]
        foreach($mapByDepthDesc as $depth => $ids) {
            foreach($ids as $custId) {
                $path = $mapTreeExp[$custId][Snap::ATTR_PATH];
                $parents = $this->_toolDownlineTree->getParentsFromPathReversed($path);
                $level = 0;
                foreach($parents as $parentId) {
                    $level += 1;
                    if(!isset($result[$parentId])) {
                        $result[$parentId] = [ ];
                    }
                    if(!isset($result[$parentId][$level])) {
                        $result[$parentId][$level] = [ ];
                    }
                    $result[$parentId][$level][] = $custId;
                }
            }
        }
        return $result;
    }

    /**
     * Convert array of data ([ 0 => [ 'id' => 321, ... ], ...]) to mapped array ([ 321 => [ 'id'=>321, ... ], ... ]).
     *
     * @param $data
     * @param $labelId
     *
     * @return array
     */
    private function _mapById($data, $labelId) {
        $result = [ ];
        foreach($data as $one) {
            $result[$one[$labelId]] = $one;
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
    public function _mapByPv($data, $labelCustId, $labelPv) {
        $result = [ ];
        foreach($data as $one) {
            $customerId = $one[$labelCustId];
            $pv = $one[$labelPv];
            if(isset($result[$customerId])) {
                $result[$customerId] += $pv;
            } else {
                $result[$customerId] = $pv;
            }
        }
        return $result;
    }

    /**
     * Create map of the front team members (siblings) [$custId => [$memberId, ...], ...] from compressed or snapshot
     * data.
     *
     * @param $data
     *
     * @return array [$custId => [$memberId, ...], ...]
     */
    private function _mapByTeams($data, $labelCustId, $labelParentId) {
        $result = [ ];
        foreach($data as $one) {
            $custId = $one[$labelCustId];
            $parentId = $one[$labelParentId];
            if($custId == $parentId) {
                /* skip root nodes, root node is not a member of a team. */
                continue;
            }
            if(!isset($result[$parentId])) {
                $result[$parentId] = [ ];
            }
            $result[$parentId][] = $custId;
        }
        return $result;
    }

    /**
     * Get depth index for Downline Tree ordered by depth desc.
     *
     * @param $tree
     * @param $labelCustId
     * @param $labelDepth
     *
     * @return array  [$depth => [$custId, ...]]
     */
    private function _mapByTreeDepthDesc($tree, $labelCustId, $labelDepth) {
        $result = [ ];
        foreach($tree as $one) {
            $customerId = $one[$labelCustId];
            $depth = $one[$labelDepth];
            if(!isset($result[$depth])) {
                $result[$depth] = [ ];
            }
            $result[$depth][] = $customerId;
        }
        /* sort by depth desc */
        krsort($result);
        return $result;
    }

    private function _populateCompressedSnapWithPv($snap, $calculatedData) {
        $result = $snap;
        foreach($calculatedData as $custId => $data) {
            /* 0 - PV, 1 - parentId */
            $result[$custId][PtcCompress::ATTR_PV] = $data[0];
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
    private function _shouldInterruptInfinityBonus($percent, $percentParent) {
        $result = false;
        if(
            ($percentParent > 0) &&
            ($percentParent <= $percent)
        ) {
            $result = true;
        }
        return $result;
    }

    public function bonusCourtesy($compressPtc, $percentCourtesy, $levelsPersonal, $levelsTeam) {
        $result = [ ];
        $mapDataById = $this->_mapById($compressPtc, PtcCompress::ATTR_CUSTOMER_ID);
        $mapTeams = $this->_mapByTeams($compressPtc, PtcCompress::ATTR_CUSTOMER_ID, PtcCompress::ATTR_PARENT_ID);
        foreach($compressPtc as $item) {
            $custId = $item[PtcCompress::ATTR_CUSTOMER_ID];
            $custScheme = $this->_toolScheme->getSchemeByCustomer($item);
            if(
                isset($mapTeams[$custId]) &&
                ($custScheme == Def::SCHEMA_DEFAULT)
            ) {
                $custRef = $item[Customer::ATTR_HUMAN_REF];
                $tv = $mapDataById[$custId][PtcCompress::ATTR_TV];
                $tv = $this->_toolScheme->getForcedTv($custId, $custScheme, $tv);
                $percentTeam = $this->_getLevelPercent($tv, $levelsTeam);
                $this->_logger->debug("Customer #$custId ($custRef) has $tv TV and $percentTeam% as max percent.");
                /* for all front team members of the customer */
                $team = $mapTeams[$custId];
                foreach($team as $memberId) {
                    $pv = $mapDataById[$memberId][PtcCompress::ATTR_PV];
                    if($pv > 0) {
                        $memberData = $mapDataById[$memberId];
                        $memberRef = $memberData[Customer::ATTR_HUMAN_REF];
                        $percentPv = $this->_getLevelPercent($pv, $levelsPersonal);
                        $percentDelta = $percentTeam - $percentPv;
                        if($percentDelta > Cfg::DEF_ZERO) {
                            $this->_logger->debug("Member $memberId ($memberRef) has $pv PV, percent: $percentPv%, delta: $percentDelta% and does not give bonus part to customer #$custId ($custRef).");
                        } else {
                            $bonusPart = $this->_toolbox->getFormat()->roundBonus($pv * $percentCourtesy);
                            $result[$custId][] = [ self::A_VALUE => $bonusPart, self::A_OTHER_ID => $memberId ];
                            $this->_logger->debug("$bonusPart is a Courtesy Bonus part for customer #$custId ($custRef) from front member #$memberId ($memberRef) - pv: $pv, percent: $percentPv%, delta: $percentDelta%.");
                        }
                    }
                }
            }
        }
        unset($mapDataById);
        unset($mapTeams);
        return $result;
    }

    public function bonusInfinity($compressOi, $scheme, $cfgParams) {
        $result = [ ]; // [$custId=>[A_PV=>..., A_ENTRIES=>[A_VALUE=>..., A_OTHER_ID=>...]], ...]
        $mapById = $this->_mapById($compressOi, OiCompress::ATTR_CUSTOMER_ID);
        $mapTreeExp = $this->_getExpandedTreeSnap(
            $compressOi,
            OiCompress::ATTR_CUSTOMER_ID,
            OiCompress::ATTR_PARENT_ID
        );
        $ibPercentMax = $this->_getMaxPercentForInfinityBonus($cfgParams);
        /* process downline tree */
        foreach($mapTreeExp as $custId => $treeData) {
            $customerData = $mapById[$custId];
            $pv = $customerData[OiCompress::ATTR_PV];
            if($pv > Cfg::DEF_ZERO) {
                $path = $treeData[Snap::ATTR_PATH];
                $parents = $this->_toolDownlineTree->getParentsFromPathReversed($path);
                $prevParentIbPercent = 0;
                $ibPercentDelta = $ibPercentMax - $prevParentIbPercent;
                $isFirstGen = true; // first generation customers should not have an infinity bonus
                foreach($parents as $parentId) {
                    $parentData = $mapById[$parentId];
                    $parentRankId = $parentData[OiCompress::ATTR_RANK_ID];
                    $parentScheme = $this->_toolScheme->getSchemeByCustomer($parentData);
                    /* should parent get an Infinity bonus? */
                    if(
                        isset($cfgParams[$scheme][$parentRankId]) &&
                        isset($cfgParams[$scheme][$parentRankId][CfgParam::ATTR_INFINITY]) &&
                        ($cfgParams[$scheme][$parentRankId][CfgParam::ATTR_INFINITY] > 0) &&
                        ($parentScheme == $scheme) &&
                        !$isFirstGen
                    ) {
                        $ibPercent = $cfgParams[$scheme][$parentRankId][CfgParam::ATTR_INFINITY];
                        /* compare ranks and interrupt if next parent has the same rank or lower */
                        $shouldInterrupt = $this->_shouldInterruptInfinityBonus(
                            $prevParentIbPercent,
                            $ibPercent,
                            $cfgParams
                        );
                        if(
                            $shouldInterrupt ||
                            ($ibPercentDelta <= 0)
                        ) {
                            break;
                        } else {
                            /* calculate bonus value */
                            if(!isset($result[$parentId])) {
                                $result[$parentId] = [ self::A_PV => 0, self::A_ENTRIES => [ ] ];
                            }
                            $result[$parentId][self::A_PV] += $pv;
                            $ibPercent = $cfgParams[$scheme][$parentRankId][CfgParam::ATTR_INFINITY];
                            $percent = ($ibPercent <= $ibPercentDelta) ? $ibPercent : $ibPercentDelta;
                            $bonus = $this->_toolbox->getFormat()->roundBonus($pv * $percent);
                            $result[$parentId][self::A_ENTRIES][] = [
                                self::A_VALUE    => $bonus,
                                self::A_OTHER_ID => $custId
                            ];
                            /* re-save Infinity percent and decrease delta */
                            $prevParentIbPercent = $ibPercent;
                            $ibPercentDelta -= $ibPercent;

                        }
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

    public function bonusOverride($compressOi, $scheme, $cfgOverride) {
        $result = [ ];
        $mapById = $this->_mapById($compressOi, OiCompress::ATTR_CUSTOMER_ID);
        $mapTeams = $this->_mapByTeams($compressOi, OiCompress::ATTR_CUSTOMER_ID, OiCompress::ATTR_PARENT_ID);
        /* populate compressed data with depth & path values */
        $mapTreeExp = $this->_getExpandedTreeSnap(
            $compressOi,
            OiCompress::ATTR_CUSTOMER_ID,
            OiCompress::ATTR_PARENT_ID
        );
        $mapByDepthDesc = $this->_mapByTreeDepthDesc($mapTreeExp, Snap::ATTR_CUSTOMER_ID, Snap::ATTR_DEPTH);
        /* scan all levels starting from the bottom and collect PV by generations */
        $mapGenerations = $this->_mapByGeneration($mapByDepthDesc, $mapTreeExp); // [ $custId=>[$genId => $totalPv, ...], ... ]
        /* scan all customers and calculate bonus values */
        foreach($compressOi as $custData) {
            $custId = $custData[OiCompress::ATTR_CUSTOMER_ID];
            $custRef = $custData[Customer::ATTR_HUMAN_REF];
            $rankId = $custData[OiCompress::ATTR_RANK_ID];
            $custScheme = $this->_toolScheme->getSchemeByCustomer($custData);
            if(
                !is_null($rankId) &&
                ($custScheme == $scheme)
            ) {
                /* this is qualified manager */
                $this->_logger->debug("Customer #$custId (#$custRef ) from scheme '$custScheme' is qualified to rank #$rankId.");
                if(isset($cfgOverride[$scheme][$rankId])) {
                    $cfgOvrEntry = $cfgOverride[$scheme][$rankId];
                    // calculate bonus value for $custId according rank configuration
                    $bonusData = $this->_calcOverrideBonusByRank($custId, $cfgOvrEntry, $mapGenerations, $mapById);
                    $entry = [ self::A_CUST_ID => $custId, self::A_RANK_ID => $rankId, self::A_ENTRIES => $bonusData ];
                    $result[] = $entry;
                } else {
                    $this->_logger->error("There is incomplete override bonus configuration for scheme '$scheme' and rank #$rankId. ");
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
    public function bonusPersonalDef($compressPtc, $levels) {
        $result = [ ];
        foreach($compressPtc as $one) {
            $custId = $one[PtcCompress::ATTR_CUSTOMER_ID];
            $pvValue = $one[PtcCompress::ATTR_PV];
            $scheme = $this->_toolScheme->getSchemeByCustomer($one);
            if($scheme == Def::SCHEMA_DEFAULT) {
                $bonusValue = $this->_calcBonusValue($pvValue, $levels);
                if($bonusValue > 0) {
                    $result[] = [ self::A_CUST_ID => $custId, self::A_VALUE => $bonusValue ];
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
    public function bonusPersonalEu($treeSnap, $compressPtc, $orders) {
        $result = [ ];
        $mapFlatById = $this->_mapById($treeSnap, Snap::ATTR_CUSTOMER_ID);
        $mapCompressById = $this->_mapById($compressPtc, PtcCompress::ATTR_CUSTOMER_ID);
        foreach($orders as $custId => $items) {
            foreach($items as $orderId => $amount) {
                $bonus = $amount * Def::REBATE_PERCENT;
                $bonus = $this->_toolbox->getFormat()->roundBonus($bonus);
                if(!isset($mapCompressById[$custId])) {
                    /* this is not qualified customer */
                    $bonus = $this->_toolbox->getFormat()->roundBonus($bonus / 2);
                    $result[] = [
                        self::A_CUST_ID => $custId,
                        self::A_ORDR_ID => $orderId,
                        self::A_VALUE   => $bonus
                    ];
                    $this->_logger->debug("Personal bonus (EU) '$bonus' is paid to unqualified customer #$custId for order #$orderId.");
                    $path = $mapFlatById[$custId][Snap::ATTR_PATH];
                    $parents = $this->_toolDownlineTree->getParentsFromPathReversed($path);
                    foreach($parents as $parentId) {
                        if(isset($mapCompressById[$parentId])) {
                            $result[] = [
                                self::A_CUST_ID => $parentId,
                                self::A_ORDR_ID => $orderId,
                                self::A_VALUE   => $bonus
                            ];
                            $this->_logger->debug("Personal bonus (EU) '$bonus' is paid to qualified parent #$parentId of the unqualified customer #$custId for order #$orderId.");
                            break;
                        }
                    }
                } else {
                    /* this is qualified customer */
                    $result[] = [
                        self::A_CUST_ID => $custId,
                        self::A_ORDR_ID => $orderId,
                        self::A_VALUE   => $bonus
                    ];
                    $this->_logger->debug("Personal bonus (EU) '$bonus' is paid to qualified customer #$custId for order #$orderId.");
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
     * @param $courtesyPercent
     *
     * @return array
     */
    public function bonusTeamDef($compressPtc, $levelsPersonal, $levelsTeam, $courtesyPercent) {
        $result = [ ];
        $mapDataById = $this->_mapById($compressPtc, PtcCompress::ATTR_CUSTOMER_ID);
        $mapTeams = $this->_mapByTeams($compressPtc, PtcCompress::ATTR_CUSTOMER_ID, PtcCompress::ATTR_PARENT_ID);
        $pbPercentMax = $this->_getMaxPercentForPersonalBonus($levelsPersonal);
        foreach($mapDataById as $custId => $custData) {
            $custData = $mapDataById[$custId];
            $custRef = $custData[Customer::ATTR_HUMAN_REF];
            $scheme = $this->_toolScheme->getSchemeByCustomer($custData);
            if($scheme == Def::SCHEMA_DEFAULT) {
                $pv = $custData[PtcCompress::ATTR_PV];
                $pvForced = $this->_toolScheme->getForcedPv($custId, $scheme, $pv);
                if($pvForced > $pv) {
                    $pv = $pvForced;
                    $this->_logger->debug("Customer #$custId (ref. #$custRef ) has forced qualification with PV=$pvForced.");
                }
                $pbPercent = $this->_getLevelPercent($pv, $levelsPersonal);
                if($pv > Cfg::DEF_ZERO) {
                    /* traverse up to tree root to calculate team bonus values */
                    $path = $custData[PtcCompress::ATTR_PATH];
                    $parents = $this->_toolDownlineTree->getParentsFromPathReversed($path);
                    $pbPercentDelta = $pbPercentMax - $pbPercent;
                    $shouldApplyCourtasy = true;
                    foreach($parents as $parentId) {
                        if($pbPercentDelta > Cfg::DEF_ZERO) {
                            /* get team qualification percent for  parent */
                            $parentData = $mapDataById[$parentId];
                            $parentRef = $parentData[Customer::ATTR_HUMAN_REF];
                            $parentScheme = $this->_toolScheme->getSchemeByCustomer($parentData);
                            $tv = $parentData[PtcCompress::ATTR_TV];
                            $tvForced = $this->_toolScheme->getForcedTv($parentId, $scheme, $tv);
                            if($tvForced > $tv) {
                                $this->_logger->debug("Customer #$parentId (ref. #$parentRef ) has forced qualification with TV=$tvForced.");
                                $tv = $tvForced;
                            }
                            $tbPercent = $this->_getLevelPercent($tv, $levelsTeam);
                            $tbPercentDelta = $tbPercent - $pbPercent;
                            if($tbPercentDelta > Cfg::DEF_ZERO) {
                                /* parent's TV % should be more then customer's PV % */
                                if(
                                    ($parentScheme != Def::SCHEMA_DEFAULT) &&
                                    ($tbPercentDelta > $courtesyPercent)
                                ) {
                                    /* EU parent should not get more then courtesy % */
                                    $tbPercentDelta = $courtesyPercent;

                                }
                                if($tbPercentDelta >= $pbPercentDelta) {
                                    /* parent's TV allows him to get all team bonus from this customer */
                                    $bonus = $this->_toolbox->getFormat()->roundBonus($pv * $pbPercentDelta);
                                    $result[] = [ self::A_CUST_ID => $parentId, self::A_VALUE => $bonus, self::A_OTHER_ID => $custId ];
                                    $this->_logger->debug("Customer #$parentId ($parentRef) has TV=$tv, %TB=$tbPercent,"
                                                          . " and get '$bonus' ($pbPercentDelta%) as DEFAULT Team Bonus from "
                                                          . "downline customer #$custId ($custRef) with PV=$pv and "
                                                          . "%PV=$pbPercent, %delta=$pbPercentDelta. "
                                                          . "All bonus is distributed.");
                                    break;
                                } else {
                                    /* parent's TV allows him to get only part of the team bonus from this customer */
                                    $bonus = $this->_toolbox->getFormat()->roundBonus($pv * $tbPercentDelta);
                                    $result[] = [ self::A_CUST_ID => $parentId, self::A_VALUE => $bonus, self::A_OTHER_ID => $custId ];
                                    $pbPercentDelta -= $tbPercentDelta;
                                    $this->_logger->debug("Customer #$parentId ($parentRef) has TV=$tv, %TB=$tbPercent,"
                                                          . " and get '$bonus' ($tbPercentDelta%) as DEFAULT Team Bonus from "
                                                          . "downline customer #$custId ($custRef) with PV=$pv and "
                                                          . "%PV=$pbPercent, %delta=$tbPercentDelta. "
                                                          . "Undistributed %delta is $pbPercentDelta%.");
                                }
                            } else {
                                /* this parent has TV % less then customer's PV % and should not be granted  */
                                $this->_logger->debug("Customer #$parentId (ref. #$parentRef) has TV=$tv, "
                                                      . "%TB=$tbPercent is less then %PB=$pbPercent and should not "
                                                      . "get Team Bonus.");
                                if($shouldApplyCourtasy) {
                                    /* reduce delta to courtesy bonus percent if parent is not "father" */
                                    $pbPercentDelta -= $courtesyPercent;
                                    $this->_logger->debug("Customer #$parentId ($parentRef) is 'father' for the "
                                                          . "customer #$custId ($custRef) %delta is decreased on "
                                                          . "Courtesy Bonus percent (new value: $pbPercentDelta).");
                                    $shouldApplyCourtasy = false;
                                }
                            }
                        } else {
                            /* this customer has max Personal Bonus percent, no Team Bonus is possible */
                            $this->_logger->debug("Customer #$custId (ref. #$custRef ) has maximal Personal Bonus %.");
                            break;
                        }
                    }
                } else {
                    $this->_logger->debug("Customer #$custId (ref. #$custRef ) has no PV ($pv PV) and could not participate in DEFAULT Team Bonus.");
                }
            } else {
                $this->_logger->debug("Customer #$custId (ref. #$custRef ) has incompatible scheme '$scheme' for DEFAULT Team Bonus.");
            }
        }
        unset($mapDataById);
        unset($mapTeams);
        return $result;
    }

    public function bonusTeamEu($compressPtc, $teamBonusPercent) {
        $result = [ ];
        $mapDataById = $this->_mapById($compressPtc, PtcCompress::ATTR_CUSTOMER_ID);
        foreach($mapDataById as $custId => $custData) {
            $custData = $mapDataById[$custId];
            $custRef = $custData[Customer::ATTR_HUMAN_REF];
            $scheme = $this->_toolScheme->getSchemeByCustomer($custData);
            if($scheme == Def::SCHEMA_EU) {
                $pv = $custData[PtcCompress::ATTR_PV];
                if($pv > Cfg::DEF_ZERO) {
                    $parentId = $custData[PtcCompress::ATTR_PARENT_ID];
                    $parentData = $mapDataById[$parentId];
                    $parentRef = $parentData[Customer::ATTR_HUMAN_REF];
                    $bonus = $this->_toolbox->getFormat()->roundBonus($pv * $teamBonusPercent);
                    $result[] = [ self::A_CUST_ID => $parentId, self::A_VALUE => $bonus, self::A_OTHER_ID => $custId ];
                    $this->_logger->debug("Customer #$parentId (ref. #$parentRef ) has '$bonus' as EU Team Bonus from downline customer #$custId (ref. #$custRef ).");
                } else {
                    $this->_logger->debug("Customer #$custId (ref. #$custRef ) has no PV ($pv PV) and could not participate in EU Team Bonus.");
                }
            } else {
                $this->_logger->debug("Customer #$custId (ref. #$custRef ) has incompatible scheme '$scheme' for EU Team Bonus.");
            }
        }
        unset($mapDataById);
        return $result;
    }

    /**
     * @param $compressedPtc
     * @param $cfgParams
     * @param $scheme
     *
     * @return array [$custId=>[$custId, $parentId, $pv, $ovLegMax, $ovLegSecond, $ovLegSummary], ...]
     */
    public function compressOi($compressedPtc, $cfgParams, $scheme) {
        $result = [ ];
        $mapById = $this->_mapById($compressedPtc, PtcCompress::ATTR_CUSTOMER_ID);
        $mapByDepth = $this->_mapByTreeDepthDesc($compressedPtc, PtcCompress::ATTR_CUSTOMER_ID, PtcCompress::ATTR_DEPTH);
        $mapByTeam = $this->_mapByTeams($compressedPtc, PtcCompress::ATTR_CUSTOMER_ID, PtcCompress::ATTR_PARENT_ID);
        foreach($mapByDepth as $level) {
            foreach($level as $custId) {
                /* compose data for one customer */
                $custData = $mapById[$custId];
                $parentId = $custData[PtcCompress::ATTR_PARENT_ID];
                $pv = $custData[PtcCompress::ATTR_PV];
                $tv = $custData[PtcCompress::ATTR_TV];
                $resultEntry = [
                    OiCompress::ATTR_SCHEME         => $scheme,
                    OiCompress::ATTR_CUSTOMER_ID    => $custId,
                    OiCompress::ATTR_PARENT_ID      => $parentId,
                    OiCompress::ATTR_PV             => $pv,
                    OiCompress::ATTR_TV             => $tv,
                    OiCompress::ATTR_OV_LEG_MAX     => 0,
                    OiCompress::ATTR_OV_LEG_SECOND  => 0,
                    OiCompress::ATTR_OV_LEG_SUMMARY => 0
                ];
                /* calculate legs */
                $isQualifiedCust = $this->isQualifiedManager($custId, $pv, $tv, $scheme, $cfgParams);
                if($isQualifiedCust) {
                    /* this is qualified manager, calculate MAX leg, second leg and summary leg */
                    if(isset($mapByTeam[$custId])) {
                        /* this customer has downline subtrees */
                        $team = $mapByTeam[$custId];
                        $legMax = $legSecond = $legSummary = 0;
                        foreach($team as $memberId) {
                            $ovMember = $mapById[$memberId][PtcCompress::ATTR_OV];
                            if($ovMember > $legMax) {
                                /* update MAX leg */
                                $legSummary += $legSecond;
                                $legSecond = $legMax;
                                $legMax = $ovMember;
                            } elseif($ovMember > $legSecond) {
                                /* update second leg */
                                $legSummary += $legSecond;
                                $legSecond = $ovMember;
                            } else {
                                $legSummary += $ovMember;
                            }
                        }
                        /* update legs */
                        $resultEntry[OiCompress::ATTR_OV_LEG_MAX] = $legMax;
                        $resultEntry[OiCompress::ATTR_OV_LEG_SECOND] = $legSecond;
                        $resultEntry[OiCompress::ATTR_OV_LEG_SUMMARY] = $legSummary;
                        $rankId = $this->_getMaxQualifiedRankId($resultEntry, $scheme, $cfgParams);
                        $resultEntry[OiCompress::ATTR_RANK_ID] = $rankId;
                    }
                }
                /* re-link parent */
                $parentData = $mapById[$parentId];
                $parentPv = $parentData[PtcCompress::ATTR_PV];
                $parentTv = $parentData[PtcCompress::ATTR_TV];
                $isQualifiedParent = $this->isQualifiedManager($parentId, $parentPv, $parentTv, $scheme, $cfgParams);
                if(!$isQualifiedParent) {
                    /* parent is not qualified, move this customer up to the closest qualified parent */
                    $path = $custData[PtcCompress::ATTR_PATH];
                    $parents = $this->_toolDownlineTree->getParentsFromPathReversed($path);
                    $foundParentId = null;
                    foreach($parents as $newParentId) {
                        $newParentData = $mapById[$newParentId];
                        $newParentPv = $newParentData[PtcCompress::ATTR_PV];
                        $newParentTv = $newParentData[PtcCompress::ATTR_TV];
                        $isQualifiedNewParent = $this->isQualifiedManager($newParentId, $newParentPv, $newParentTv, $scheme, $cfgParams);
                        if($isQualifiedNewParent) {
                            $foundParentId = $newParentId;
                            break;
                        }
                    }
                    unset($parents);
                    if(is_null($foundParentId)) {
                        /* no qualified parent up to the root, make this customer as root customer  */
                        $resultEntry[OiCompress::ATTR_PARENT_ID] = $custId;
                    } else {
                        $resultEntry[OiCompress::ATTR_PARENT_ID] = $foundParentId;
                    }
                }
                /* add entry to results */
                $result[$custId] = $resultEntry;
            }
        }
        unset($mapByDepth);
        unset($mapByTeam);
        unset($mapById);
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
    public function compressPtc($treeSnap, $customers, $trans) {
        $qLevels = $this->_toolScheme->getQualificationLevels();
        $forcedIds = $this->_toolScheme->getForcedQualificationCustomersIds();
        $this->_logger->info("PTC Compression parameters:" .
                             " qualification levels=" . var_export($qLevels, true)
                             . ", forced customers: " . var_export($forcedIds, true));
        /* array with results: [$customerId => [$pvCompressed, $parentCompressed], ... ]*/
        $compressedTree = [ ];
        $mapCustomer = $this->_mapById($customers, Customer::ATTR_CUSTOMER_ID);
        $mapPv = $this->_mapByPv($trans, Account::ATTR_CUST_ID, Transaction::ATTR_VALUE);
        $mapDepth = $this->_mapByTreeDepthDesc($treeSnap, Snap::ATTR_CUSTOMER_ID, Snap::ATTR_DEPTH);
        $mapTeams = $this->_mapByTeams($treeSnap, Snap::ATTR_CUSTOMER_ID, Snap::ATTR_PARENT_ID);
        foreach($mapDepth as $depth => $levelCustomers) {
            foreach($levelCustomers as $custId) {
                $pv = isset($mapPv[$custId]) ? $mapPv[$custId] : 0;
                $parentId = $treeSnap[$custId][Snap::ATTR_PARENT_ID];
                $custData = $mapCustomer[$custId];
                $scheme = $this->_toolScheme->getSchemeByCustomer($custData);
                $level = $qLevels[$scheme]; // qualification level for current customer
                if(
                    ($pv >= $level) ||
                    (in_array($custId, $forcedIds))
                ) {
                    if(isset($compressedTree[$custId])) {
                        $pvExist = $compressedTree[$custId][0];
                        $pvNew = $pv + $pvExist;
                        $compressedTree[$custId] = [ $pvNew, $parentId ];
                    } else {
                        $compressedTree[$custId] = [ $pv, $parentId ];
                    }
                } else {
                    /* move PV up to the closest qualified parent (current customer's level is used for qualification) */
                    $path = $treeSnap[$custId][Snap::ATTR_PATH];
                    $parents = $this->_toolDownlineTree->getParentsFromPathReversed($path);
                    $foundParentId = null;
                    foreach($parents as $newParentId) {
                        $pvParent = isset($mapPv[$newParentId]) ? $mapPv[$newParentId] : 0;
                        if(
                            ($pvParent >= $level) ||
                            (in_array($newParentId, $forcedIds))
                        ) {
                            $foundParentId = $newParentId;
                            break;
                        }
                    }
                    unset($parents);
                    /* add PV to this parent */
                    if(
                        !is_null($foundParentId) &&
                        ($pv > 0)
                    ) {
                        if(isset($compressedTree[$foundParentId])) {
                            $pvExist = $compressedTree[$foundParentId][0];
                            $pvNew = $pv + $pvExist;
                            $compressedTree[$foundParentId][0] = $pvNew;
                        } else {
                            $compressedTree[$foundParentId] [0] = $pv;
                        }
                        $this->_logger->debug("$pv PV are transferred from customer #$custId to his qualified parent #$foundParentId .");
                    }
                    /* change parent for all siblings of the unqualified customer */
                    if(isset($mapTeams[$custId])) {
                        $team = $mapTeams[$custId];
                        foreach($team as $memberId) {
                            if(isset($compressedTree[$memberId])) {
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
        $data = $this->_composeSnapUpdates($compressedTree);
        /* add compressed PV data */
        $result = $this->_populateCompressedSnapWithPv($data, $compressedTree);
        return $result;
    }

    private function isQualifiedManager($custId, $pv, $tv, $scheme, $cfgParams) {
        $result = false;
        if(
            ($pv > Cfg::DEF_ZERO) &&
            ($tv > Cfg::DEF_ZERO)
        ) {
            $params = $cfgParams[$scheme];
            foreach($params as $param) {
                $qpv = $param[CfgParam::ATTR_QUALIFY_PV];
                $qtv = $param[CfgParam::ATTR_QUALIFY_TV];
                if(
                    ($pv >= $qpv) &&
                    ($tv >= $qtv)
                ) {
                    /* this customer is qualified for the rank */
                    $result = true;
                    break;
                }
            }
        }
        if(!$result) {
            /* check forced qualification */
            $rankId = $this->_toolScheme->getForcedQualificationRank($custId, $scheme);
            $result = ($rankId > 0);
        }
        return $result;
    }

    /**
     * @param $trans
     *
     * @return array [$accId=>$pvWriteOff, ...]
     */
    public function pvWriteOff($trans) {
        $result = [ ];
        foreach($trans as $one) {
            $debitAccId = $one[Transaction::ATTR_DEBIT_ACC_ID];
            $creditAccId = $one[Transaction::ATTR_CREDIT_ACC_ID];
            $value = $one[Transaction::ATTR_VALUE];
            if(isset($result[$debitAccId])) {
                $result[$debitAccId] -= $value;
            } else {
                $result[$debitAccId] = -$value;
            }
            if(isset($result[$creditAccId])) {
                $result[$creditAccId] += $value;
            } else {
                $result[$creditAccId] = $value;
            }
        }
        return $result;
    }

    public function valueOv($compressPtc) {
        $result = [ ];
        $mapById = $this->_mapById($compressPtc, PtcCompress::ATTR_CUSTOMER_ID);
        $mapDepth = $this->_mapByTreeDepthDesc($compressPtc, PtcCompress::ATTR_CUSTOMER_ID, PtcCompress::ATTR_DEPTH);
        $mapTeams = $this->_mapByTeams($compressPtc, PtcCompress::ATTR_CUSTOMER_ID, PtcCompress::ATTR_PARENT_ID);
        foreach($mapDepth as $depth => $levelCustomers) {
            $this->_logger->debug("Process level #$depth of the downline tree.");
            foreach($levelCustomers as $custId) {
                $ov = $mapById[$custId][PtcCompress::ATTR_PV];
                if(isset($mapTeams[$custId])) {
                    /* add OV from front team members */
                    $team = $mapTeams[$custId];
                    foreach($team as $memberId) {
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

    public function valueTv($compressPtc) {
        $result = [ ];
        $mapById = $this->_mapById($compressPtc, PtcCompress::ATTR_CUSTOMER_ID);
        $mapTeams = $this->_mapByTeams($compressPtc, PtcCompress::ATTR_CUSTOMER_ID, PtcCompress::ATTR_PARENT_ID);
        foreach($compressPtc as $one) {
            $custId = $one[PtcCompress::ATTR_CUSTOMER_ID];
            $tv = $mapById[$custId][PtcCompress::ATTR_PV];
            $this->_logger->debug("Customer #$custId has own $tv PV.");
            if(isset($mapTeams[$custId])) {
                $frontTeam = $mapTeams[$custId];
                foreach($frontTeam as $teamMemberId) {
                    $memberPv = $mapById[$teamMemberId][PtcCompress::ATTR_PV];
                    $tv += $memberPv;
                    $this->_logger->debug("$memberPv PV is added to #$custId from member #$teamMemberId.");
                }
            }
            $result[$custId] = $tv;
            $this->_logger->debug("Customer #$custId has total $tv TV.");
        }
        unset($mapTeams);
        return $result;
    }
}