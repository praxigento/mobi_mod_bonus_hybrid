<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\PvWriteOff;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;

/**
 * Process PV Write Offs and calculate PV/TV/OV values for plain downline tree.
 */
class SaveDownline
{
    /** @var \Praxigento\Downline\Service\ISnap */
    private $callDwnlSnap;
    /** @var \Praxigento\Downline\Helper\Tree */
    private $hlpDwnlTree;
    /** @var  \Praxigento\BonusHybrid\Helper\IScheme */
    private $hlpScheme;
    /** @var \Praxigento\BonusHybrid\Helper\SignupDebit\GetCustomersIds */
    private $hlpSignupDebitCust;
    /** @var \Praxigento\Accounting\Repo\Dao\Account */
    private $repoAcc;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $repoDwnl;
    /** @var \Praxigento\BonusBase\Repo\Dao\Rank */
    private $repoRank;

    public function __construct(
        \Praxigento\BonusHybrid\Helper\IScheme $hlpScheme,
        \Praxigento\BonusHybrid\Helper\SignupDebit\GetCustomersIds $hlpSignupDebitCust,
        \Praxigento\Downline\Helper\Tree $hlpDwnlTree,
        \Praxigento\Accounting\Repo\Dao\Account $repoAcc,
        \Praxigento\BonusBase\Repo\Dao\Rank $repoRank,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $repoDwnl,
        \Praxigento\Downline\Service\ISnap $callDwnlSnap
    )
    {
        $this->hlpScheme = $hlpScheme;
        $this->hlpSignupDebitCust = $hlpSignupDebitCust;
        $this->hlpDwnlTree = $hlpDwnlTree;
        $this->repoAcc = $repoAcc;
        $this->repoRank = $repoRank;
        $this->repoDwnl = $repoDwnl;
        $this->callDwnlSnap = $callDwnlSnap;
    }

    public function exec($calcId, $periodEnd, $updates)
    {
        /* load accounts map */
        $mapAccs = $this->loadCustomersAccounts();
        /* get 'Sign Up Volume Debit' customers and customers with forced qualification */
        $signupDebitCustomers = $this->hlpSignupDebitCust->exec();
        $forcedQualCustomers = $this->prepareForcedQualification();
        /* load customers downline tree and map by depth in descending order */
        $tree = $this->loadDownline($periodEnd);
        $mapByDepth = $this->hlpDwnlTree->mapByTreeDepthDesc(
            $tree,
            \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder::A_CUST_ID,
            \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder::A_DEPTH
        );
        /* default rank for the customers */
        $defRankId = $this->getDefaultRankId();
        /* create registries for collected data */
        $regDwnl = [];
        $regTeam = [];      // custId => [teamCustId, ...]
        $reqQual = [];      // custId => true|false
        /* PV to be transited through unqualified children (sum for all unqual. children of custId) */
        $reqJumps = [];     // custId=> pv
        /* scan downline tree from the lowest level up to the top */
        foreach ($mapByDepth as $level => $customers) {
            /* process all customers from one level of the downline tree */
            foreach ($customers as $custId) {
                $custData = $tree[$custId];
                $custParentId = $custData[\Praxigento\Downline\Repo\Query\Snap\OnDate\Builder::A_PARENT_ID];
                $custDepth = $custData[\Praxigento\Downline\Repo\Query\Snap\OnDate\Builder::A_DEPTH];
                $custPath = $custData[\Praxigento\Downline\Repo\Query\Snap\OnDate\Builder::A_PATH];
                $custScheme = $this->hlpScheme->getSchemeByCustomer($custData);
                /* register current customer in the parent's team */
                if (!isset($regTeam[$custParentId])) $regTeam[$custParentId] = [];
                $regTeam[$custParentId][] = $custId;
                /* get PV for current customer */
                /* TODO: should we add 'Sign Up Debit' PV here ??? */
                $custPv = $this->getPv($custId, $mapAccs, $updates, $signupDebitCustomers);
                /* get customer qualification */
                $isQualifedByPvDef = ($custScheme == Cfg::SCHEMA_DEFAULT)
                    && ($custPv > (Cfg::PV_QUALIFICATION_LEVEL_DEF - Cfg::DEF_ZERO));
                $isQualifedByPvEu = ($custScheme == Cfg::SCHEMA_EU)
                    && ($custPv > (Cfg::PV_QUALIFICATION_LEVEL_EU - Cfg::DEF_ZERO));
                $isForced = isset($forcedQualCustomers[$custScheme][$custId]);
                $isCustQualified = $isQualifedByPvDef || $isQualifedByPvEu || $isForced;
                $reqQual[$custId] = $isCustQualified;

                /* jump unqualif. children's PV if customer is not qualified itself */
                if (!$isCustQualified && isset($reqJumps[$custId])) {
                    $reqJumps[$custParentId] = $reqJumps[$custId];
                    unset($reqJumps[$custId]);
                }

                /* register customer in downline registry with initial values for TV/OV (own PV)*/
                $dwnlData = new EBonDwnl();
                $dwnlData->setCalculationRef($calcId);
                $dwnlData->setCustomerRef($custId);
                $dwnlData->setOv(0);
                $dwnlData->setParentRef($custParentId);
                $dwnlData->setDepth($custDepth);
                $dwnlData->setPath($custPath);
                $dwnlData->setPv($custPv);
                $dwnlData->setRankRef($defRankId);
                $dwnlData->setTv(0);
                $dwnlData->setUnqMonths(0);
                $regDwnl[$custId] = $dwnlData;

                /* walk trough the team and add children OV to the customer OV */
                $team = $regTeam[$custId] ?? [];
                $custTv = $custPv;  // TV = (PV.own + SUM(PV.team)
                $custOv = ($isCustQualified) ? $custPv : 0;  // OV = (PV.own + SUM(OV.team)
                foreach ($team as $childId) {
                    /* all children should be registered before */
                    /** @var EBonDwnl $childData */
                    $childData = $regDwnl[$childId];
                    $childPv = $childData->getPv();
                    $childOv = $childData->getOv();
                    $isChildQualified = $reqQual[$childId];

                    if (!$isCustQualified && !$isChildQualified) {
                        /* PV of the unqualified children should jump through unqualified customers */
                        if ($childPv) {
                            if (isset($reqJumps[$custParentId])) {
                                $reqJumps[$custParentId] += $childPv;
                            } else {
                                $reqJumps[$custParentId] = $childPv;
                            }
                        }
                        /* ... but OV should be assigned to customer (unqualif. child's PV are not in his OV) */
                        $custOv += $childOv;
                    } else {
                        /* add child's PV & OV to customer's TV & OV */
                        $custTv += $childPv;
                        $custOv += $childOv;
                        if (!$isChildQualified) $custOv += $childPv; // PV for unqualified children are not in his OV.
                    }
                }
                /* update customer TV & OV */
                /** @var EBonDwnl $custUpdate */
                $custUpdate = $regDwnl[$custId];
                $updateTv = $custUpdate->getTv();
                $updateOv = $custUpdate->getOv();
                $custUpdate->setTv($updateTv + $custTv);
                $custUpdate->setOv($updateOv + $custOv);
                /* add jumped PV of the unqualified children */
                if (isset($reqJumps[$custId])) {
                    $jumpOv = $reqJumps[$custId];
                    $updateOv = $custUpdate->getOv();
                    $custUpdate->setOv($updateOv + $jumpOv);
                    unset($reqJumps[$custId]);
                }
            }
        }
        /* save data into downline registry */
        foreach ($regDwnl as $item) {
            $this->repoDwnl->create($item);
        }
    }

    /**
     * Get ID for default rank.
     *
     * @return int
     */
    private function getDefaultRankId()
    {
        $result = $this->repoRank->getIdByCode(Cfg::RANK_DISTRIBUTOR);
        return $result;
    }

    /**
     * Get PV for customer (real PV from 'PV Write Off' calculation adjusted for 'Sign Up Debit' customers).
     */
    private function getPv($custId, $mapAccs, $updates, $signupDebitCustomers)
    {
        $result = 0;
        if (isset($mapAccs[$custId])) {
            $account = $mapAccs[$custId];
            $accId = $account->getId();
            $result = (isset($updates[$accId])) ? $updates[$accId] : 0;
        }
        /* correct PV for 'Sign Up Debit' customers */
        $isSignupDebit = in_array($custId, $signupDebitCustomers);
        if ($isSignupDebit) {
            $result += \Praxigento\BonusHybrid\Config::SIGNUP_DEBIT_PV;
        }
        return $result;
    }

    /**
     * Load accounts by asset type code and map its using $customerId as a key.
     *
     * @return \Praxigento\Accounting\Repo\Data\Account[]
     */
    private function loadCustomersAccounts()
    {
        $mapAccs = $this->repoAcc->getAllByAssetTypeCode(Cfg::CODE_TYPE_ASSET_PV);
        $result = [];
        foreach ($mapAccs as $one) {
            $custId = $one->getCustomerId();
            $result[$custId] = $one;
        }
        return $result;
    }

    private function loadDownline($periodEnd)
    {
        $reqTree = new \Praxigento\Downline\Service\Snap\Request\GetStateOnDate();
        $reqTree->setDatestamp($periodEnd);
        $reqTree->setAddCountryCode(true);
        $respTree = $this->callDwnlSnap->getStateOnDate($reqTree, true);
        $result = $respTree->get();
        return $result;
    }

    /**
     * @return array [schema => [custId, ...], ...]
     */
    private function prepareForcedQualification()
    {
        $result = [
            Cfg::SCHEMA_DEFAULT => [],
            Cfg::SCHEMA_EU => []
        ];
        $customers = $this->hlpScheme->getForcedQualificationCustomers();
        foreach ($customers as $custId => $item) {
            if (isset($item[Cfg::SCHEMA_DEFAULT])) $result[Cfg::SCHEMA_DEFAULT][] = $custId;
            if (isset($item[Cfg::SCHEMA_EU])) $result[Cfg::SCHEMA_EU][] = $custId;
        }
        return $result;
    }
}