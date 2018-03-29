<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Calc\Z\Helper;

class GetCustomersIds
{
    /** @var array of cached Customers IDs keys by calculation ID */
    private $cachedIds = [];
    /** @var \Praxigento\BonusHybrid\Repo\Query\SignUpDebit\GetLastCalcIdForPeriod */
    private $queryGetCalcId;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Registry\SignUpDebit */
    private $daoRegistry;

    public function __construct(
        \Praxigento\BonusHybrid\Repo\Dao\Registry\SignUpDebit $daoRegistry,
        \Praxigento\BonusHybrid\Repo\Query\SignUpDebit\GetLastCalcIdForPeriod $queryGetCalcId
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
            $where = \Praxigento\BonusHybrid\Repo\Data\Registry\SignUpDebit::A_CALC_REF . '=' . (int)$calcId;
            $rs = $this->daoRegistry->get($where);
            /** @var \Praxigento\BonusHybrid\Repo\Data\Registry\SignUpDebit $one */
            foreach ($rs as $one) {
                $ids[] = $one->getCustomerRef();
            }
            $this->cachedIds[$calcId] = $ids;
        }
        return $this->cachedIds[$calcId];
    }
}