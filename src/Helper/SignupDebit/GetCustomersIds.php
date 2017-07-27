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
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Registry\SignupDebit */
    protected $repoRegistry;

    public function __construct(
        \Praxigento\BonusHybrid\Repo\Entity\Registry\SignupDebit $repoRegistry,
        \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetLastCalcIdForPeriod $queryGetCalcId
    ) {
        $this->repoRegistry = $repoRegistry;
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
            $where = \Praxigento\BonusHybrid\Repo\Data\Entity\Registry\SignupDebit::ATTR_CALC_REF . '=' . (int)$calcId;
            $rs = $this->repoRegistry->get($where);
            /** @var \Praxigento\BonusHybrid\Repo\Data\Entity\Registry\SignupDebit $one */
            foreach ($rs as $one) {
                $ids[] = $one->getCustomerRef();
            }
            $this->cachedIds[$calcId] = $ids;
        }
        return $this->cachedIds[$calcId];
    }
}