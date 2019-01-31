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

/**
 * Routine to process plain downline & to perform qualification compression (re-link downlines of the unqualified
 * customers to the his parents).
 */
class Calc
{
    /** @var \Praxigento\Downline\Api\Helper\Config */
    private $hlpCfgDwnl;
    /** @var \Praxigento\Core\Api\Helper\Customer\Group */
    private $hlpCustGroup;
    /** @var \Praxigento\Downline\Api\Helper\Group\Transition */
    private $hlpGroupTrans;
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
        \Praxigento\Downline\Api\Helper\Config $hlpCfgDwnl,
        \Praxigento\Downline\Api\Helper\Group\Transition $hlpGroupTrans,
        \Praxigento\Downline\Api\Service\Customer\Downline\SwitchUp $servSwitchUp
    ) {
        $this->logger = $logger;
        $this->repoCust = $repoCust;
        $this->hlpCustGroup = $hlpCustGroup;
        $this->hlpCfgDwnl = $hlpCfgDwnl;
        $this->hlpGroupTrans = $hlpGroupTrans;
        $this->servSwitchUp = $servSwitchUp;
    }

    /**
     * @param EBonDwnl[] $treePlain
     * @throws \Throwable
     */
    public function exec($treePlain)
    {
        /* group ID for unqualified customers */
        $groupIdUnq = $this->hlpCfgDwnl->getDowngradeGroupUnqual();
        foreach ($treePlain as $one) {
            $custId = $one->getCustomerRef();
            $unqMonths = $one->getUnqMonths();
            if ($unqMonths >= Cfg::MAX_UNQ_MONTHS) {
                /* get current group and */
                $groupIdCurrent = $this->hlpCustGroup->getIdByCustomerId($custId);
                $isAllowed = $this->hlpGroupTrans->isAllowedGroupTransition($groupIdCurrent, $groupIdUnq);
                if ($isAllowed) {
                    /* we should change customer group */
                    try {
                        $cust = $this->repoCust->getById($custId);
                        $groupId = $cust->getGroupId();
                        if ($groupId != $groupIdUnq) {
                            $cust->setGroupId($groupIdUnq);
                            /* ... then to switch all customer's children to the customer's parent (on save event) */
                            $this->repoCust->save($cust);
                            $this->logger->info("Customer #$custId is downgraded (from group $groupId to #$groupIdUnq).");
                        }
                    } catch (\Throwable $e) {
                        $this->logger->error("Cannot update customer group on unqualified customer ($custId) downgrade.");
                        throw $e;
                    }
                } else {
                    $this->logger->info("Downgrade for customer #$custId is not allowed (group id: $groupIdCurrent).");
                }

            }
        }
    }
}