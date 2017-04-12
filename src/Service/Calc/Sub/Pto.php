<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Sub;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Defaults as Def;
use Praxigento\BonusHybrid\Entity\Registry\Pto as EPto;

class Pto
{

    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\Traits\TMap {
        mapByTreeDepthDesc as protected;
    }

    const OPT_CALC_ID = 'calc_id';
    const OPT_PERIOD_END = 'period_end';
    const OPT_UPDATES = 'updates';

    /** @var \Praxigento\Downline\Service\ISnap */
    protected $callDwnlSnap;
    /** @var \Praxigento\BonusHybrid\Helper\SignupDebit\GetCustomersIds */
    protected $hlpSignupDebitCust;
    /** @var \Praxigento\Accounting\Repo\Entity\IAccount */
    protected $repoAcc;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Registry\IPto */
    protected $repoRegPto;
    /** @var \Praxigento\Downline\Tool\ITree */
    protected $toolDownlineTree;
    /** @var  \Praxigento\BonusHybrid\Tool\IScheme */
    protected $toolScheme;

    public function __construct(
        \Praxigento\BonusHybrid\Tool\IScheme $toolScheme,
        \Praxigento\Downline\Tool\ITree $toolTree,
        \Praxigento\BonusHybrid\Helper\SignupDebit\GetCustomersIds $hlpSignupDebitCust,
        \Praxigento\Accounting\Repo\Entity\IAccount $repoAcc,
        \Praxigento\BonusHybrid\Repo\Entity\Registry\IPto $repoRegPto,
        \Praxigento\Downline\Service\ISnap $callDwnlSnap
    ) {
        $this->toolScheme = $toolScheme;
        $this->toolDownlineTree = $toolTree;
        $this->hlpSignupDebitCust = $hlpSignupDebitCust;
        $this->repoAcc = $repoAcc;
        $this->repoRegPto = $repoRegPto;
        $this->callDwnlSnap = $callDwnlSnap;
    }

    public function exec($ctx)
    {
        /* extract working data from execution context*/
        $calcId = $ctx[self::OPT_CALC_ID];
        $periodEnd = $ctx[self::OPT_PERIOD_END];
        $updates = $ctx[self::OPT_UPDATES];
        /* load accounts map */
        $mapAccs = $this->loadCustomersAccounts();
        /* get 'Sign Up Volume Debit' customers and customers with forced qualification */
        $signupDebitCustomers = $this->hlpSignupDebitCust->exec();
        $forcedQualCustomers = $this->prepareForcedQualification();
        /* load customers downline tree and map by depth in descending order */
        $tree = $this->loadDownline($periodEnd);
        $mapByDepth = $this->mapByTreeDepthDesc(
            $tree,
            \Praxigento\Downline\Data\Entity\Snap::ATTR_CUSTOMER_ID,
            \Praxigento\Downline\Data\Entity\Snap::ATTR_DEPTH
        );
        /* create registries for collected data (OV & Teams) */
        $regOv = [];
        $regTeam = [];      // custId => [teamCustId, ...]
        $reqQual = [];      // custId => true|false
        /* PV to be transited through unqualified children (sum for all unqual. children of custId) */
        $reqJumps = [];     // custId=> pv
        /* scan downline tree from the lowest level up to the top */
        foreach ($mapByDepth as $level => $customers) {
            /* process all customers from one level of the downline tree */
            foreach ($customers as $custId) {
                $custData = $tree[$custId];
                $custParentId = $custData[\Praxigento\Downline\Data\Entity\Snap::ATTR_PARENT_ID];
                $custScheme = $this->toolScheme->getSchemeByCustomer($custData);
                /* register current customer in the parent's team */
                if (!isset($regTeam[$custParentId])) $regTeam[$custParentId] = [];
                $regTeam[$custParentId][] = $custId;
                /* get PV for current customer */
                /* TODO: should we add 'Sign Up Debit' PV here ??? */
                $custPv = $this->getPv($custId, $mapAccs, $updates, $signupDebitCustomers);
                /* get customer qualification */
                $isQualifedByPvDef = ($custScheme == Def::SCHEMA_DEFAULT)
                    && ($custPv > (Def::PV_QUALIFICATION_LEVEL_DEF - Cfg::DEF_ZERO));
                $isQualifedByPvEu = ($custScheme == Def::SCHEMA_EU)
                    && ($custPv > (Def::PV_QUALIFICATION_LEVEL_EU - Cfg::DEF_ZERO));
                $isForced = isset($forcedQualCustomers[$custScheme][$custId]);
                $isCustQualified = $isQualifedByPvDef || $isQualifedByPvEu || $isForced;
                $reqQual[$custId] = $isCustQualified;

                /* register customer in OV reg. with initial values for TV/OV (own PV)*/
                $regOv[$custId] = [
                    EPto::ATTR_CUSTOMER_REF => $custId,
                    EPto::ATTR_PARENT_REF => $custParentId,
                    EPto::ATTR_PV => $custPv,
                    EPto::ATTR_TV => 0,
                    EPto::ATTR_OV => 0
                ];

                /* walk trough the team and add children OV to the customer OV */
                $team = $regTeam[$custId] ?? [];
                $custTv = $custPv;  // TV = (PV.own + SUM(PV.team)
                $custOv = ($isCustQualified) ? $custPv : 0;  // OV = (PV.own + SUM(OV.team)
                foreach ($team as $childId) {
                    /* all children should be registered before */
                    $childPv = $regOv[$childId][EPto::ATTR_PV];
                    $childOv = $regOv[$childId][EPto::ATTR_OV];
                    $isChildQual = $reqQual[$childId];

                    if (!$isCustQualified && !$isChildQual) {
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
                        if (!$isChildQual) $custOv += $childPv; // PV for unqualified children are not in his OV.
                    }
                }
                /* update customer TV & OV */
                $regOv[$custId][EPto::ATTR_TV] += $custTv;
                $regOv[$custId][EPto::ATTR_OV] += $custOv;
                /* add jumped PV of the unqualified children */
                if (isset($reqJumps[$custId])) {
                    $regOv[$custId][EPto::ATTR_OV] += $reqJumps[$custId];
                    unset($reqJumps[$custId]);
                }

//                /* get account ID */
//                if (isset($mapAccs[$custId])) {
//                    /* get calculated PV for period */
//                    $account = $mapAccs[$custId];
//                    $accId = $account->getId();
//                    $pv = (isset($updates[$accId])) ? $updates[$accId] : 0;
//                    /* correct PV value */
//                    $pv = $this->toolScheme->getForcedPv($custId, $custScheme, $pv);
//                    /* correct PV for 'Sign Up Debit' customers */
//                    $isSignupDebit = in_array($custId, $signupDebitCustomers);
//                    if ($isSignupDebit) {
//                        $pv += \Praxigento\BonusHybrid\Defaults::SIGNUP_DEBIT_PV;
//                    }
//                    /**
//                     * Qualify current customer. PV for unqualified customers will not be added to unqualified
//                     * parents' OV.
//                     */
//                    $isCustQualified = false;
//                    if (
//                        ($custScheme == Def::SCHEMA_DEFAULT) && ($pv > (Def::PV_QUALIFICATION_LEVEL_DEF - 0.0001)) ||
//                        ($custScheme == Def::SCHEMA_EU) && ($pv > (Def::PV_QUALIFICATION_LEVEL_EU - 0.0001))
//                    ) {
//                        $isCustQualified = true;
//                    }
//                    $pvForOv = ($isCustQualified) ? $pv : 0;
//                    if (!isset($regOv[$custId])) {
//                        /* create entry in the registry */
//                        $regOv[$custId] = [
//                            EPto::ATTR_CUSTOMER_REF => $custId,
//                            EPto::ATTR_PARENT_REF => $parentId,
//                            EPto::ATTR_PV => $pv,
//                            EPto::ATTR_TV => $pv,
//                            EPto::ATTR_OV => $pvForOv
//                        ];
//                    } else {
//                        /* update entry in the registry */
//                        $regOv[$custId][EPto::ATTR_PV] += $pv;
//                        $regOv[$custId][EPto::ATTR_TV] += $pv;
//                        $regOv[$custId][EPto::ATTR_OV] += $pvForOv;
//                    }
//
//                    /* process upline */
//                    $path = $tree[$custId][\Praxigento\Downline\Data\Entity\Snap::ATTR_PATH];
//                    $parents = $this->toolDownlineTree->getParentsFromPathReversed($path);
//                    $isFather = true;
//                    foreach ($parents as $pCustId) {
//                        $parentData = $tree[$pCustId];
//                        $parentScheme = $this->toolScheme->getSchemeByCustomer($parentData);
//                        $pvParent = 0;
//                        if (isset($mapAccs[$pCustId])) {
//                            $accountParent = $mapAccs[$pCustId];
//                            $accIdParent = $accountParent->getId();
//                            $pvParent = isset($updates[$accIdParent]) ? $updates[$accIdParent] : 0;
//                        }
//                        $pvParent = $this->toolScheme->getForcedPv($pCustId, $custScheme, $pvParent);
//                        /* correct PV for 'Sign Up Debit' customers */
//                        $isSignupDebit = in_array($pCustId, $signupDebitCustomers);
//                        if ($isSignupDebit) {
//                            $pvParent += \Praxigento\BonusHybrid\Defaults::SIGNUP_DEBIT_PV;
//                        }
//                        /* don't add PV of the unqualified customer to OV for parents w/o personal qualification */
//                        $isParentQualified = false;
//                        if (
//                            ($parentScheme == Def::SCHEMA_DEFAULT) && ($pvParent > (Def::PV_QUALIFICATION_LEVEL_DEF - 0.0001)) ||
//                            ($parentScheme == Def::SCHEMA_EU) && ($pvParent > (Def::PV_QUALIFICATION_LEVEL_EU - 0.0001))
//                        ) {
//                            $isParentQualified = true;
//                        }
//                        if (!$isParentQualified && !$isCustQualified && $isFather) {
//                            /* skip PV in OV for not qualified parent & customer (for first generation only) */
//                        } else {
//                            if (!isset($regOv[$pCustId])) {
//                                $parentId = $tree[$pCustId][\Praxigento\Downline\Data\Entity\Snap::ATTR_PARENT_ID];
//                                $regOv[$pCustId] = [
//                                    EPto::ATTR_CUSTOMER_REF => $pCustId,
//                                    EPto::ATTR_PARENT_REF => $parentId,
//                                    EPto::ATTR_PV => 0,
//                                    EPto::ATTR_TV => 0,
//                                    EPto::ATTR_OV => $pv
//                                ];
//                            } else {
//                                $regOv[$pCustId][EPto::ATTR_OV] += $pv;
//                            }
//                            /* collect TV */
//                            if ($isFather) {
//                                $regOv[$pCustId][EPto::ATTR_TV] += $pv;
//                            }
//                        }
//                        $isFather = false;
//                    }
//                }
            }
        }
        /* save data into PTO registry */
        foreach ($regOv as $item) {
            $custPv = $item[EPto::ATTR_PV];
            $tv = $item[EPto::ATTR_TV];
            $ov = $item[EPto::ATTR_OV];
            if (($custPv + $tv + $ov) > 0) {
                /* save not empty items only */
                $item[EPto::ATTR_CALC_REF] = $calcId;
                $this->repoRegPto->create($item);
            }
        }
    }

    /**
     * Get PV for customer (real PV from 'PV Write Off' calculation adjusted for 'Sign Up Debit' customers).
     */
    protected function getPv($custId, $mapAccs, $updates, $signupDebitCustomers)
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
            $result += \Praxigento\BonusHybrid\Defaults::SIGNUP_DEBIT_PV;
        }
        return $result;
    }

    /**
     * Load accounts by asset type code and map its using $customerId as a key.
     *
     * @return \Praxigento\Accounting\Data\Entity\Account[]
     */
    protected function loadCustomersAccounts()
    {
        $mapAccs = $this->repoAcc->getAllByAssetTypeCode(Cfg::CODE_TYPE_ASSET_PV);
        $result = [];
        foreach ($mapAccs as $one) {
            $custId = $one->getCustomerId();
            $result[$custId] = $one;
        }
        return $result;
    }

    protected function loadDownline($periodEnd)
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
    protected function prepareForcedQualification()
    {
        $result = [
            Def::SCHEMA_DEFAULT => [],
            Def::SCHEMA_EU => []
        ];
        $customers = $this->toolScheme->getForcedQualificationCustomers();
        foreach ($customers as $custId => $item) {
            if (isset($item[Def::SCHEMA_DEFAULT])) $result[Def::SCHEMA_DEFAULT][] = $custId;
            if (isset($item[Def::SCHEMA_EU])) $result[Def::SCHEMA_EU][] = $custId;
        }
        return $result;
    }
}