<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Plugin\Magento\Customer\Model\ResourceModel;

use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;

/**
 * Remove customer related data from Bonus Hybrid tables on customer delete from adminhtml.
 */
class CustomerRepository
{
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $daoBonDwnl;

    public function __construct(
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoBonDwnl
    ) {
        $this->daoBonDwnl = $daoBonDwnl;
    }

    /**
     * Remove customer related data from Bonus Hybrid tables on customer delete from adminhtml.
     *
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $subject
     * @param $customerId
     * @return array
     */
    public function beforeDeleteById(
        \Magento\Customer\Api\CustomerRepositoryInterface $subject,
        $customerId
    ) {
        $this->deleteDwnl($customerId);
        $result = [$customerId];
        return $result;
    }

    private function deleteDwnl($custId)
    {
        $where = EBonDwnl::A_CUST_REF . '=' . (int)$custId;
        $this->daoBonDwnl->delete($where);
    }
}