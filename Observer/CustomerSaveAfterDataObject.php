<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Observer;

use Praxigento\BonusHybrid\Service\United\Forecast\Request as ARequest;
use Praxigento\BonusHybrid\Service\United\Forecast\Response as AResponse;

/**
 * Run forecast calc on customer group change.
 */
class CustomerSaveAfterDataObject
    implements \Magento\Framework\Event\ObserverInterface
{
    /** @var \Praxigento\Downline\Api\Helper\Group\Transition */
    private $hlpGroupTrans;
    /** @var \Praxigento\BonusHybrid\Service\United\Forecast */
    private $servUnqual;

    public function __construct(
        \Praxigento\Downline\Api\Helper\Group\Transition $hlpGroupTrans,
        \Praxigento\BonusHybrid\Service\United\Forecast $servUnqual
    ) {
        $this->hlpGroupTrans = $hlpGroupTrans;
        $this->servUnqual = $servUnqual;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Customer\Model\Data\Customer $beforeSave */
        $beforeSave = $observer->getData('orig_customer_data_object');
        /** @var \Magento\Customer\Model\Data\Customer $afterSave */
        $afterSave = $observer->getData('customer_data_object');
        $idBefore = $beforeSave && $beforeSave->getId() ?? null;
        $idAfter = $afterSave->getId();
        if ($idBefore == $idAfter) {
            /* this is customer update */
            $groupIdBefore = $beforeSave->getGroupId();
            $groupIdAfter = $afterSave->getGroupId();
            if ($groupIdBefore != $groupIdAfter) {
                $isDowngrade = $this->hlpGroupTrans->isDowngrade($groupIdBefore, $groupIdAfter);
                $isUpgrade = $this->hlpGroupTrans->isUpgrade($groupIdBefore, $groupIdAfter);
                if ($isDowngrade || $isUpgrade) {
                    $req = new ARequest();
                    /** @var AResponse $resp */
                    $resp = $this->servUnqual->exec($req);
                }
            }
        }
    }
}