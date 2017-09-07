<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus\Team\Calc;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Defaults as Def;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EDwnlBon;
use Praxigento\BonusHybrid\Service\Calc\Data\Bonus as DBonus;
use Praxigento\Downline\Repo\Entity\Data\Customer as ECustomer;

/**
 * Calculate Team bonus according to DEFAULT scheme.
 */
class DefScheme
{
    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\Traits\TMap {
        mapById as protected;
        mapByTeams as protected;
    }

    /** @var \Praxigento\Downline\Tool\ITree */
    private $hlpDwnl;
    /** @var \Praxigento\Core\Tool\IFormat */
    private $hlpFormat;
    /** @var  \Praxigento\BonusHybrid\Tool\IScheme */
    private $hlpScheme;
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Praxigento\Downline\Repo\Entity\Customer */
    private $repoDwnl;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoDwnlBon;
    /** @var \Praxigento\BonusBase\Repo\Entity\Level */
    private $repoLevel;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\Core\Tool\IFormat $hlpFormat,
        \Praxigento\Downline\Tool\ITree $hlpDwnl,
        \Praxigento\BonusHybrid\Tool\IScheme $hlpScheme,
        \Praxigento\Downline\Repo\Entity\Customer $repoDwnl,
        \Praxigento\BonusBase\Repo\Entity\Level $repoLevel,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoDwnlBon
    )
    {
        $this->logger = $logger;
        $this->hlpFormat = $hlpFormat;
        $this->hlpDwnl = $hlpDwnl;
        $this->hlpScheme = $hlpScheme;
        $this->repoDwnl = $repoDwnl;
        $this->repoLevel = $repoLevel;
        $this->repoDwnlBon = $repoDwnlBon;
    }

    /**
     * Walk trough the compressed downline & calculate team bonus for EU scheme.
     *
     * @param int $calcId ID of the compression calculation to get downline.
     * @return Data[]
     */
    public function exec($calcId)
    {
        $result = [];
        /* collect additional data */
        $levelsPersonal = $this->repoLevel->getByCalcTypeCode(Cfg::CODE_TYPE_CALC_BONUS_PERSONAL_DEF);
        $levelsTeam = $this->repoLevel->getByCalcTypeCode(Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF);
        $dwnlCompress = $this->repoDwnlBon->getByCalcId($calcId);
        $dwnlCurrent = $this->repoDwnl->get();
        $pctPbMax = $this->getMaxPercentForPersonalBonus($levelsPersonal);
        $courtesyPct = \Praxigento\BonusHybrid\Defaults::COURTESY_BONUS_PERCENT;
        /* create maps to access data */
        $mapDwnlById = $this->mapById($dwnlCompress, EDwnlBon::ATTR_CUST_REF);
        $mapTeams = $this->mapByTeams($dwnlCompress, EDwnlBon::ATTR_CUST_REF, EDwnlBon::ATTR_PARENT_REF);
        $mapCustById = $this->mapById($dwnlCurrent, ECustomer::ATTR_CUSTOMER_ID);
        /**
         * Go through all customers from compressed tree and calculate bonus.
         *
         * @var int $custId
         * @var EDwnlBon $custDwnl
         */
        foreach ($mapDwnlById as $custId => $custDwnl) {
            /** @var ECustomer $custData */
            $custData = $mapCustById[$custId];
            $custMlmId = $custData->getHumanRef();
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
                $parents = $this->hlpDwnl->getParentsFromPathReversed($path);
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
                        /** @var EDwnlBon $parentDwnl */
                        $parentDwnl = $mapDwnlById[$parentId];
                        /** @var ECustomer $parentData */
                        $parentData = $mapCustById[$parentId];
                        $parentMlmId = $parentData->getHumanRef();
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
                            if ($parentScheme != Def::SCHEMA_DEFAULT) {
                                if ($isFather) {
                                    /* Courtesy bonus will calculate in other process, just decrease % left */
                                    $pctPbLeft = number_format($pctPbLeft - $courtesyPct, 2);
                                    $pctPbDone = number_format($pctPbDone + $courtesyPct, 2);
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
                                if ($parentScheme == Def::SCHEMA_DEFAULT) {
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
                                $pctPbLeft = number_format($pctPbLeft - $pctTbAvlbDelta, 2);
                                $pctPbDone = number_format($pctPbDone + $pctTbAvlbDelta, 2);
                                $this->logger->debug("TB: All bonus is distributed (%left=$pctPbLeft, %done=$pctPbDone).");
                                break;
                            } else {
                                /* parent's TV allows him to get only part of the team bonus from this customer */
                                if ($parentScheme == Def::SCHEMA_DEFAULT) {
                                    $bonus = $this->hlpFormat->roundBonus($pv * $pctTbAvlbDelta);
                                    $entry = new DBonus();
                                    $entry->setCustomerRef($parentId);
                                    $entry->setDonatorRef($custId);
                                    $entry->setValue($bonus);
                                    $result[] = $entry;
                                    $pctPbLeft = number_format($pctPbLeft - $pctTbAvlbDelta, 2);
                                    $pctPbDone = number_format($pctPbDone + $pctTbAvlbDelta, 2);
                                    $this->logger->debug("TB: Customer #$parentId ($parentMlmId) has TV=$tv, %TB=$pctTb,"
                                        . " and get '$bonus' ($pctTbAvlbDelta%) as DEFAULT Team Bonus from "
                                        . "downline customer #$custId ($custMlmId) with PV=$pv and "
                                        . "%PB=$pctPb, %left=$pctPbLeft%, %done=$pctPbDone.");
                                } else {
                                    $pctPbLeft = number_format($pctPbLeft - $pctTbAvlbDelta, 2);
                                    $pctPbDone = number_format($pctPbDone + $pctTbAvlbDelta, 2);
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
                                $pctPbLeft = number_format($pctPbLeft - $courtesyPct, 2);
                                $pctPbDone = number_format($pctPbDone + $courtesyPct, 2);
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