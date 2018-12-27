<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;
use Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A\SaveDownline\A\Repo\Query\GetSnap as QGetSnap;
use Praxigento\Downline\Repo\Data\Snap as ESnap;

/**
 * Process PV Write Offs and calculate PV/TV/OV values for plain downline tree.
 */
class SaveDownline
{
    /** @var \Praxigento\Accounting\Repo\Dao\Account */
    private $daoAcc;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $daoDwnl;
    /** @var \Praxigento\Core\Api\App\Repo\Generic */
    private $daoGeneric;
    /** @var \Praxigento\BonusBase\Repo\Dao\Rank */
    private $daoRanks;
    /** @var \Praxigento\Downline\Api\Helper\Config */
    private $hlpCfgDwnl;
    /** @var \Praxigento\Downline\Api\Helper\Tree */
    private $hlpDwnlTree;
    /** @var  \Praxigento\BonusHybrid\Api\Helper\Scheme */
    private $hlpScheme;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Z\Helper\GetCustomersIds */
    private $hlpSignUpDebitCust;
    /** @var \Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A\SaveDownline\A\Repo\Query\GetSnap */
    private $qDwnlSnap;

    public function __construct(
        \Praxigento\Core\Api\App\Repo\Generic $daoGeneric,
        \Praxigento\Accounting\Repo\Dao\Account $daoAcc,
        \Praxigento\BonusBase\Repo\Dao\Rank $daoRank,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoDwnl,
        \Praxigento\BonusHybrid\Api\Helper\Scheme $hlpScheme,
        \Praxigento\BonusHybrid\Service\Calc\Z\Helper\GetCustomersIds $hlpSignUpDebitCust,
        \Praxigento\Downline\Api\Helper\Tree $hlpDwnlTree,
        \Praxigento\Downline\Api\Helper\Config $hlpCfgDwnl,
        \Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A\SaveDownline\A\Repo\Query\GetSnap $qDwnlSnap
    )
    {
        $this->daoGeneric = $daoGeneric;
        $this->daoAcc = $daoAcc;
        $this->daoRanks = $daoRank;
        $this->daoDwnl = $daoDwnl;
        $this->hlpScheme = $hlpScheme;
        $this->hlpSignUpDebitCust = $hlpSignUpDebitCust;
        $this->hlpDwnlTree = $hlpDwnlTree;
        $this->hlpCfgDwnl = $hlpCfgDwnl;
        $this->qDwnlSnap = $qDwnlSnap;
    }

    public function exec($calcId, $periodEnd, $updates)
    {
        /* load accounts map */
        $mapAccs = $this->loadCustomersAccounts();
        /* get 'Sign Up Volume Debit' customers and customers with forced qualification */
        $signupDebitCustomers = $this->hlpSignUpDebitCust->exec();
        $forcedQualCustomers = $this->prepareForcedQualification();
        /* load customers downline tree and map by depth in descending order */
        $tree = $this->loadDownline($periodEnd);
        $mapByDepth = $this->hlpDwnlTree->mapByTreeDepthDesc(
            $tree,
            QGetSnap::A_CUST_ID,
            QGetSnap::A_DEPTH
        );
        /* default & unqualified ranks IDs */
        $mapRanks = $this->mapDefRanksByCustId();

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
                $custParentId = $custData->get(QGetSnap::A_PARENT_ID);
                $custDepth = $custData->get(QGetSnap::A_DEPTH);
                $custPath = $custData->get(QGetSnap::A_PATH);
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
                $rankIdDef = $mapRanks[$custId];
                $dwnlData->setRankRef($rankIdDef);
                $dwnlData->setTv(0);
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
            $this->daoDwnl->create($item);
        }
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
        $isSignUpDebit = in_array($custId, $signupDebitCustomers);
        if ($isSignUpDebit) {
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
        $mapAccs = $this->daoAcc->getAllByAssetTypeCode(Cfg::CODE_TYPE_ASSET_PV);
        $result = [];
        foreach ($mapAccs as $one) {
            $custId = $one->getCustomerId();
            $result[$custId] = $one;
        }
        return $result;
    }

    private function loadDownline($periodEnd)
    {
        $result = [];
        $query = $this->qDwnlSnap->build();
        $conn = $query->getConnection();
        $bind = [
            QGetSnap::BND_ON_DATE => $periodEnd
        ];
        $rows = $conn->fetchAll($query, $bind);
        foreach ($rows as $one) {
            $item = new ESnap($one);
            $custId = $item->getCustomerRef();
            $result[$custId] = $item;
        }
        return $result;
    }

    /**
     * @return array [custId => rankId]
     */
    private function mapDefRanksByCustId()
    {
        $result = [];
        /* unqual. customer's group ID & rank */
        $groupIdUnqual = $this->hlpCfgDwnl->getDowngradeGroupUnqual();
        $rankIdUnranked = $this->daoRanks->getIdByCode(Cfg::RANK_UNRANKED);
        $rankIdDefault = $this->daoRanks->getIdByCode(Cfg::RANK_DISTRIBUTOR);

        /* get all customers & map ranks by groups */
        $entity = Cfg::ENTITY_MAGE_CUSTOMER;
        $cols = [
            Cfg::E_CUSTOMER_A_ENTITY_ID,
            Cfg::E_CUSTOMER_A_GROUP_ID
        ];
        $all = $this->daoGeneric->getEntities($entity, $cols);
        foreach ($all as $one) {
            $custId = $one[Cfg::E_CUSTOMER_A_ENTITY_ID];
            $groupId = $one[Cfg::E_CUSTOMER_A_GROUP_ID];
            $rankId = ($groupId == $groupIdUnqual) ? $rankIdUnranked : $rankIdDefault;
            $result[$custId] = $rankId;
        }
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