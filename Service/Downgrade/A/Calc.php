<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 17.4.12
 * Time: 20:29
 */

namespace Praxigento\BonusHybrid\Service\Downgrade\A;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;
use Praxigento\BonusHybrid\Repo\Data\Registry\Downgrade as ERegDwngrd;
use Praxigento\Downline\Repo\Data\Change\Group as EDwnlChangeGroup;

/**
 * Routine to process plain downline & to perform qualification compression (re-link downlines of the unqualified
 * customers to the his parents).
 */
class Calc
{
    /** @var \Praxigento\Downline\Repo\Dao\Change\Group */
    private $daoDwnlChangeGroup;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Registry\Downgrade */
    private $daoRegDwngrd;
    /** @var \Praxigento\Downline\Api\Helper\Config */
    private $hlpCfgDwnl;
    /** @var \Praxigento\Core\Api\Helper\Customer\Group */
    private $hlpCustGroup;
    /** @var \Praxigento\Downline\Api\Helper\Group\Transition */
    private $hlpGroupTrans;
    /** @var \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var \Praxigento\BonusHybrid\Api\Helper\Scheme */
    private $hlpScheme;
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Magento\Customer\Api\CustomerRepositoryInterface */
    private $repoCust;
    /** @var \Praxigento\Downline\Api\Service\Customer\Downline\SwitchUp */
    private $servSwitchUp;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Magento\Customer\Api\CustomerRepositoryInterface $repoCust,
        \Praxigento\Core\Api\Helper\Customer\Group $hlpCustGroup,
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        \Praxigento\Downline\Api\Helper\Config $hlpCfgDwnl,
        \Praxigento\Downline\Api\Helper\Group\Transition $hlpGroupTrans,
        \Praxigento\BonusHybrid\Api\Helper\Scheme $hlpScheme,
        \Praxigento\Downline\Repo\Dao\Change\Group $daoDwnlChangeGroup,
        \Praxigento\BonusHybrid\Repo\Dao\Registry\Downgrade $daoRegDwngrd,
        \Praxigento\Downline\Api\Service\Customer\Downline\SwitchUp $servSwitchUp
    ) {
        $this->logger = $logger;
        $this->repoCust = $repoCust;
        $this->hlpCustGroup = $hlpCustGroup;
        $this->hlpPeriod = $hlpPeriod;
        $this->hlpCfgDwnl = $hlpCfgDwnl;
        $this->hlpGroupTrans = $hlpGroupTrans;
        $this->hlpScheme = $hlpScheme;
        $this->daoDwnlChangeGroup = $daoDwnlChangeGroup;
        $this->daoRegDwngrd = $daoRegDwngrd;
        $this->servSwitchUp = $servSwitchUp;
    }

    /**
     * @param EBonDwnl[] $treePlain
     * @param \Praxigento\BonusBase\Repo\Data\Period $period
     * @param int $calcId
     * @throws \Throwable
     */
    public function exec($treePlain, $period, $calcId)
    {
        /* group ID for unqualified customers */
        $groupIdUnq = $this->hlpCfgDwnl->getDowngradeGroupUnqual();
        $groupIdsDistr = $this->hlpCfgDwnl->getDowngradeGroupsDistrs();
        $dsEnd = $period->getDstampEnd();
        foreach ($treePlain as $one) {
            $custId = $one->getCustomerRef();
            $unqMonths = $one->getUnqMonths();
            if ($unqMonths >= Cfg::MAX_UNQ_MONTHS) {
                $forcedCustIds = $this->hlpScheme->getForcedQualificationCustomersIds();
                if (!in_array($custId, $forcedCustIds)) {
                    /* get current group and */
                    $groupIdCurrent = $this->hlpCustGroup->getIdByCustomerId($custId);
                    $isTransAllowed = $this->hlpGroupTrans->isAllowedGroupTransition($groupIdCurrent, $groupIdUnq);
                    if ($isTransAllowed) {
                        $isNew = $this->isNewDistr($custId, $groupIdsDistr, $dsEnd);
                        if (!$isNew) {
                            /* we should change customer group */
                            try {
                                $cust = $this->repoCust->getById($custId);
                                $groupId = $cust->getGroupId();
                                if ($groupId != $groupIdUnq) {
                                    $cust->setGroupId($groupIdUnq);
                                    /* ... then to switch all customer's children to the customer's parent (on save event) */
                                    $this->repoCust->save($cust);
                                    /* save item do downgrade registry */
                                    $dwngrd = new ERegDwngrd();
                                    $dwngrd->setCalcRef($calcId);
                                    $dwngrd->setCustomerRef($custId);
                                    $this->daoRegDwngrd->create($dwngrd);
                                    $this->logger->info("Customer #$custId is downgraded (from group $groupId to #$groupIdUnq).");
                                }
                            } catch (\Throwable $e) {
                                $this->logger->error("Cannot update customer group on unqualified customer ($custId) downgrade.");
                                throw $e;
                            }
                        } else {
                            $this->logger->info("Customer #$custId should not be downgraded (group is assigned after bonus period).");
                        }
                    } else {
                        $this->logger->info("Downgrade for customer #$custId is not allowed (group id: $groupIdCurrent).");
                    }
                } else {
                    $this->logger->info("Downgrade for customer #$custId is not allowed (forced qualification).");
                }
            }
        }
    }

    /**
     * Return 'true' if customer has got distributor group after upper bound of bonus period.
     *
     * @param $custId
     * @param $groupsDistr
     * @param $periodEnd
     * @return bool
     */
    private function isNewDistr($custId, $groupsDistr, $periodEnd)
    {
        // 'false' by default, because old customers don't have records in 'Group Changed' registry.
        $result = false;
        $byCust = EDwnlChangeGroup::A_CUSTOMER_REF . '=' . (int)$custId;
        $groups = implode(',', $groupsDistr);
        $byGroup = EDwnlChangeGroup::A_GROUP_NEW . " IN($groups)";
        $where = "($byCust) AND ($byGroup)";
        $order = EDwnlChangeGroup::A_DATE_CHANGED . ' DESC';
        $limit = 1;
        /** @var EDwnlChangeGroup[] $found */
        $found = $this->daoDwnlChangeGroup->get($where, $order, $limit, null, null);
        if (count($found) == 1) {
            $record = reset($found);
            $dateChanged = $record->getDateChanged();
            $dsDay = $this->hlpPeriod->getPeriodForDate($dateChanged);
            if ($dsDay > $periodEnd) {
                $result = true;
            }
        }
        return $result;
    }
}