<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus\Team;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;
use Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Data\Bonus as DBonus;
use Praxigento\Downline\Repo\Data\Customer as ECustomer;

/**
 * Calculate Team bonus according to DEFAULT scheme.
 */
class CalcDef
{
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $daoBonDwnl;
    /** @var \Praxigento\Downline\Repo\Dao\Customer */
    private $daoDwnl;
    /** @var \Praxigento\BonusBase\Repo\Dao\Level */
    private $daoLevel;
    /** @var \Praxigento\Core\Api\Helper\Format */
    private $hlpFormat;
    /** @var  \Praxigento\BonusHybrid\Api\Helper\Scheme */
    private $hlpScheme;
    /** @var \Praxigento\Downline\Api\Helper\Tree */
    private $hlpTree;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Downline\Repo\Dao\Customer $daoDwnl,
        \Praxigento\BonusBase\Repo\Dao\Level $daoLevel,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoBonDwnl,
        \Praxigento\Core\Api\Helper\Format $hlpFormat,
        \Praxigento\Downline\Api\Helper\Tree $hlpTree,
        \Praxigento\BonusHybrid\Api\Helper\Scheme $hlpScheme
    )
    {
        $this->logger = $logger;
        $this->daoDwnl = $daoDwnl;
        $this->daoLevel = $daoLevel;
        $this->daoBonDwnl = $daoBonDwnl;
        $this->hlpFormat = $hlpFormat;
        $this->hlpTree = $hlpTree;
        $this->hlpScheme = $hlpScheme;
    }

    /**
     * Walk trough the compressed downline & calculate team bonus for DEFAULT scheme.
     *
     * @param int $calcId ID of the compression calculation to get downline.
     * @return Data[]
     * @throws \Exception
     */
    public function exec($calcId)
    {
        $result = [];
        /* collect additional data */
        $levelsPersonal = $this->daoLevel->getByCalcTypeCode(Cfg::CODE_TYPE_CALC_BONUS_PERSONAL);
        $levelsTeam = $this->daoLevel->getByCalcTypeCode(Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF);
        $dwnlCompress = $this->daoBonDwnl->getByCalcId($calcId);
        $dwnlCurrent = $this->daoDwnl->get();
        $pctPbMax = $this->getMaxPercentForPersonalBonus($levelsPersonal);
        $courtesyPct = \Praxigento\BonusHybrid\Config::COURTESY_BONUS_PERCENT;
        /* create maps to access data */
        $mapDwnlById = $this->hlpTree->mapById($dwnlCompress, EBonDwnl::A_CUST_REF);
        $mapTeams = $this->hlpTree->mapByTeams($dwnlCompress, EBonDwnl::A_CUST_REF, EBonDwnl::A_PARENT_REF);
        $mapCustById = $this->hlpTree->mapById($dwnlCurrent, ECustomer::A_CUSTOMER_ID);
        /**
         * Go through all customers from compressed tree and calculate bonus.
         *
         * @var int $custId
         * @var EBonDwnl $custDwnl
         */
        foreach ($mapDwnlById as $custId => $custDwnl) {
            /** @var ECustomer $custData */
            $custData = $mapCustById[$custId];
            $custMlmId = $custData->getMlmId();
            $scheme = $this->hlpScheme->getSchemeByCustomer($custData);
            /* only DEFAULT-schema customers may apply to Team Bonus */
            $pv = $custDwnl->getPv();
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
                $path = $custDwnl->getPath();
                $parents = $this->hlpTree->getParentsFromPathReversed($path);
                /* init undistributed delta: 20% - 5% */
                $pctPbLeft = $pctPbMax - $pctPb;
                /* ... and distributed amount: 5% */
                $pctPbDone = $pctPb;
                $this->logger->debug("TB: Customer #$custId(ref. #$custMlmId) has $pv PV and %PB=$pctPb, "
                    . "%left=$pctPbLeft, %done=$pctPbDone");
                /* set "isFather" flag for courtesy bonus */
                $isFather = true;
                foreach ($parents as $parentId) {
                    /* current customer has not MAX PB% or there is undistributed delta yet */
                    if ($pctPbLeft > Cfg::DEF_ZERO) {
                        /* get team qualification percent for parent */
                        /** @var EBonDwnl $parentDwnl */
                        $parentDwnl = $mapDwnlById[$parentId];
                        /** @var ECustomer $parentData */
                        $parentData = $mapCustById[$parentId];
                        $parentMlmId = $parentData->getMlmId();
                        $parentScheme = $this->hlpScheme->getSchemeByCustomer($parentData);
                        $tv = $parentDwnl->getTv();
                        $tvForced = $this->hlpScheme->getForcedTv($parentId, $scheme, $tv);
                        if ($tvForced > $tv) {
                            $this->logger->debug("TB: Customer #$parentId (ref. #$parentMlmId ) has forced qualification with TV=$tvForced.");
                            $tv = $tvForced;
                        }
                        /* get TB% for current parent and calc available % for current parent */
                        $pctTb = $this->getLevelPercent($tv, $levelsTeam);
                        $pctTbAvlbDelta = $pctTb - $pctPbDone;
                        if ($pctTbAvlbDelta > Cfg::DEF_ZERO) {
                            /* parent's TV % should be more then customer's PV % */

                            /* EU parent should not get more then courtesy % */
                            if ($parentScheme != Cfg::SCHEMA_DEFAULT) {
                                if ($isFather) {
                                    /* Courtesy bonus will calculate in other process, just decrease % left */
                                    $pctPbLeft = $this->hlpFormat->toNumber($pctPbLeft - $courtesyPct);
                                    $pctPbDone = $this->hlpFormat->toNumber($pctPbDone + $courtesyPct);
                                    $this->logger->debug("TB: Customer #$parentId (ref. #$parentMlmId) has "
                                        . "scheme=$parentScheme and is 'father' for #$custId ($custMlmId). "
                                        . "Decrease %TB on %courtesy=$courtesyPct to %left=$pctPbLeft, %done=$pctPbDone.");
                                }
                            }

                            if (
                                ($pctTbAvlbDelta > $pctPbLeft) ||
                                abs($pctTbAvlbDelta - $pctPbLeft) < Cfg::DEF_ZERO // this is ">="
                            ) {
                                /* there is undistributed PB% */
                                /* parent's TV allows him to get all team bonus from this customer */
                                if ($parentScheme == Cfg::SCHEMA_DEFAULT) {
                                    $bonus = $this->hlpFormat->roundBonus($pv * $pctPbLeft);
                                    $entry = new DBonus();
                                    $entry->setCustomerRef($parentId);
                                    $entry->setDonatorRef($custId);
                                    $entry->setValue($bonus);
                                    $result[] = $entry;
                                    $this->logger->debug("TB: Customer #$parentId ($parentMlmId) has TV=$tv, %TB=$pctTb,"
                                        . " and get '$bonus' ($pctPbLeft%) as DEFAULT Team Bonus from "
                                        . "downline customer #$custId ($custMlmId) with PV=$pv and "
                                        . "%PB=$pctPb");
                                } else {
                                    $this->logger->debug("TB: Customer #$parentId ($parentMlmId) has TV=$tv, %TB=$pctTb,"
                                        . " but cannot get DEFAULT Team Bonus from "
                                        . "downline customer #$custId ($custMlmId) with PV=$pv and "
                                        . "%PB=$pctPb because he is not from DEFAULT scheme.");
                                }
                                $pctPbLeft = $this->hlpFormat->toNumber($pctPbLeft - $pctTbAvlbDelta);
                                $pctPbDone = $this->hlpFormat->toNumber($pctPbDone + $pctTbAvlbDelta);
                                $this->logger->debug("TB: All bonus is distributed (%left=$pctPbLeft, %done=$pctPbDone).");
                                break;
                            } else {
                                /* parent's TV allows him to get only part of the team bonus from this customer */
                                if ($parentScheme == Cfg::SCHEMA_DEFAULT) {
                                    $bonus = $this->hlpFormat->roundBonus($pv * $pctTbAvlbDelta);
                                    $entry = new DBonus();
                                    $entry->setCustomerRef($parentId);
                                    $entry->setDonatorRef($custId);
                                    $entry->setValue($bonus);
                                    $result[] = $entry;
                                    $pctPbLeft = $this->hlpFormat->toNumber($pctPbLeft - $pctTbAvlbDelta);
                                    $pctPbDone = $this->hlpFormat->toNumber($pctPbDone + $pctTbAvlbDelta);
                                    $this->logger->debug("TB: Customer #$parentId ($parentMlmId) has TV=$tv, %TB=$pctTb,"
                                        . " and get '$bonus' ($pctTbAvlbDelta%) as DEFAULT Team Bonus from "
                                        . "downline customer #$custId ($custMlmId) with PV=$pv and "
                                        . "%PB=$pctPb, %left=$pctPbLeft%, %done=$pctPbDone.");
                                } else {
                                    $pctPbLeft = $this->hlpFormat->toNumber($pctPbLeft - $pctTbAvlbDelta);
                                    $pctPbDone = $this->hlpFormat->toNumber($pctPbDone + $pctTbAvlbDelta);
                                    $this->logger->debug("TB: Customer #$parentId ($parentMlmId) has TV=$tv, %TB=$pctTb,"
                                        . " but cannot get DEFAULT Team Bonus from "
                                        . "downline customer #$custId ($custMlmId) with PV=$pv and "
                                        . "%PB=$pctPb because he is not from DEFAULT scheme."
                                        . " Available: %left=$pctPbLeft%, %done=$pctPbDone.");
                                }

                            }
                        } else {
                            /* this parent has %TB less then distributed %PB and should not be granted  */
                            $this->logger->debug("TB: Customer #$parentId (ref. #$parentMlmId) has TV=$tv, "
                                . "%TB=$pctTb is not more then %done=$pctPbDone and should not "
                                . "get Team Bonus from #$custId ($custMlmId).");
                            if ($isFather) {
                                /* reduce delta to courtesy bonus percent if parent is not "father" */
                                $pctPbLeft = $this->hlpFormat->toNumber($pctPbLeft - $courtesyPct);
                                $pctPbDone = $this->hlpFormat->toNumber($pctPbDone + $courtesyPct);
                                $this->logger->debug("Customer #$parentId ($parentMlmId) is 'father' for the "
                                    . "customer #$custId ($custMlmId) %left is decreased on "
                                    . "Courtesy Bonus percent (new value: $pctPbLeft, %done=$pctPbDone).");
                            }
                        }
                    } else {
                        /* this customer has max Personal Bonus percent, no Team Bonus is possible */
                        $this->logger->debug("TB: Customer #$custId (ref. #$custMlmId ) has maximal Personal Bonus %.");
                        break;
                    }
                    /* next parent is not father */
                    $isFather = false;
                }
            } else {
                $this->logger->debug("TB: Customer #$custId (ref. #$custMlmId ) has no PV ($pv PV) and could not participate in DEFAULT Team Bonus.");
            }
        }
        unset($mapCustById);
        unset($mapTeams);
        unset($mapDwnlById);
        return $result;
    }

    /**
     * Get percent for the first level that is greater then given $value.
     *
     * @param int $value PV/TV/... value to get level's percent
     * @param array $levels asc ordered array with levels & percents ([$level => $percent])
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

    /**
     * Look up for the level with the max bonus percent.
     *
     * @param array $levels set of the levels with percents ([$level => $percent])
     * @return int
     */
    private function getMaxPercentForPersonalBonus($levels)
    {
        $result = 0;
        foreach ($levels as $percent) {
            if ($percent > $result) {
                $result = $percent;
            }
        }
        return $result;
    }
}