<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Helper\SignupDebit;

class GetCustomersIds
{
    /** @var array of cached Customers IDs keyd by calculation ID */
    protected $cachedIds = [];
    /** @var \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetLastCalcIdForPeriod */
    protected $queryGetCalcId;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Registry\SignupDebit */
    protected $daoRegistry;

    public function __construct(
        \Praxigento\BonusHybrid\Repo\Dao\Registry\SignupDebit $daoRegistry,
        \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetLastCalcIdForPeriod $queryGetCalcId
    ) {
        $this->daoRegistry = $daoRegistry;
        $this->queryGetCalcId = $queryGetCalcId;
    }

    /**
     * Get IDs of the customers who was processed in Sign Up Volumes Debit calculation.
     *
     * @param int|null $calcId ID of the calculation or null for the last one
     * @return array of customers ids who was processed in Sign Up Volume Debit.
     */
    public function exec($calcId = null)
    {
        if (is_null($calcId)) {
            $calcId = $this->queryGetCalcId->exec();
        }
        if (!isset($this->cachedIds[$calcId])) {
            $ids = [];
            $where = \Praxigento\BonusHybrid\Repo\Data\Registry\SignupDebit::A_CALC_REF . '=' . (int)$calcId;
            $rs = $this->daoRegistry->get($where);
            /** @var \Praxigento\BonusHybrid\Repo\Data\Registry\SignupDebit $one */
            foreach ($rs as $one) {
                $ids[] = $one->getCustomerRef();
            }
            $this->cachedIds[$calcId] = $ids;
        }
        return $this->cachedIds[$calcId];
    }
}