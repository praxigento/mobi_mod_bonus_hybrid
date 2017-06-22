<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Sub;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Defaults as Def;
use Praxigento\BonusHybrid\Repo\Data\Entity\Retro\Downline\Plain as EDwnlPlain;

/**
 * Process aggregated PV movements and calculate PV/TV/OV values for plain downline tree.
 */
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
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Retro\Downline\IPlain */
    protected $repoDwnlPlain;
    /** @var \Praxigento\Downline\Tool\ITree */
    protected $toolDownlineTree;
    /** @var  \Praxigento\BonusHybrid\Tool\IScheme */
    protected $toolScheme;

    public function __construct(
        \Praxigento\BonusHybrid\Tool\IScheme $toolScheme,
        \Praxigento\Downline\Tool\ITree $toolTree,
        \Praxigento\BonusHybrid\Helper\SignupDebit\GetCustomersIds $hlpSignupDebitCust,
        \Praxigento\Accounting\Repo\Entity\IAccount $repoAcc,
        \Praxigento\BonusHybrid\Repo\Entity\Retro\Downline\IPlain $repoDwnlPlain,
        \Praxigento\Downline\Service\ISnap $callDwnlSnap
    ) {
        $this->toolScheme = $toolScheme;
        $this->toolDownlineTree = $toolTree;
        $this->hlpSignupDebitCust = $hlpSignupDebitCust;
        $this->repoAcc = $repoAcc;
        $this->repoDwnlPlain = $repoDwnlPlain;
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
            \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder::A_CUST_ID,
            \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder::A_DEPTH
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
                $custParentId = $custData[\Praxigento\Downline\Repo\Query\Snap\OnDate\Builder::A_PARENT_ID];
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

                /* jump unqualif. children's PV if customer is not qualified itself */
                if (!$isCustQualified && isset($reqJumps[$custId])) {
                    $reqJumps[$custParentId] = $reqJumps[$custId];
                    unset($reqJumps[$custId]);
                }

                /* register customer in OV reg. with initial values for TV/OV (own PV)*/
                $regOv[$custId] = [
                    EDwnlPlain::ATTR_CUSTOMER_REF => $custId,
                    EDwnlPlain::ATTR_PARENT_REF => $custParentId,
                    EDwnlPlain::ATTR_PV => $custPv,
                    EDwnlPlain::ATTR_TV => 0,
                    EDwnlPlain::ATTR_OV => 0
                ];

                /* walk trough the team and add children OV to the customer OV */
                $team = $regTeam[$custId] ?? [];
                $custTv = $custPv;  // TV = (PV.own + SUM(PV.team)
                $custOv = ($isCustQualified) ? $custPv : 0;  // OV = (PV.own + SUM(OV.team)
                foreach ($team as $childId) {
                    /* all children should be registered before */
                    $childPv = $regOv[$childId][EDwnlPlain::ATTR_PV];
                    $childOv = $regOv[$childId][EDwnlPlain::ATTR_OV];
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
                $regOv[$custId][EDwnlPlain::ATTR_TV] += $custTv;
                $regOv[$custId][EDwnlPlain::ATTR_OV] += $custOv;
                /* add jumped PV of the unqualified children */
                if (isset($reqJumps[$custId])) {
                    $regOv[$custId][EDwnlPlain::ATTR_OV] += $reqJumps[$custId];
                    unset($reqJumps[$custId]);
                }
            }
        }
        /* save data into PTO registry */
        foreach ($regOv as $item) {
            $custPv = $item[EDwnlPlain::ATTR_PV];
            $tv = $item[EDwnlPlain::ATTR_TV];
            $ov = $item[EDwnlPlain::ATTR_OV];
            if (($custPv + $tv + $ov) > 0) {
                /* save not empty items only */
                $item[EDwnlPlain::ATTR_CALC_REF] = $calcId;
                $this->repoDwnlPlain->create($item);
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