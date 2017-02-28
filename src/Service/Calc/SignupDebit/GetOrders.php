<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Calc\SignupDebit;

class GetOrders
{

    const A_CUST_ID = \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders\Builder::A_CUST_ID;
    const A_ORDER_ID = \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders\Builder::A_ORDER_ID;
    const A_PARENT_ID = \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders\Builder::A_PARENT_ID;
    const A_PV = \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders\Builder::A_PV;
    /** @var \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders\Builder */
    protected $qbuildGetOrders;

    public function __construct(
        \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders\Builder $qbuildGetOrders
    ) {
        $this->qbuildGetOrders = $qbuildGetOrders;
    }

    /**
     * @param \Praxigento\BonusHybrid\Service\Calc\SignupDebit\GetOrders\Request $opts
     * @return array
     */
    public function do($opts)
    {
        $dateFrom = $opts->dateFrom;
        $dateTo = $opts->dateTo;
        /** @var  $query */
        $query = $this->qbuildGetOrders->getSelectQuery();
        $conn = $query->getConnection();
        $rs = $conn->fetchAll($query, [
            \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders\Builder::BIND_DATE_FROM => $dateFrom,
            \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders\Builder::BIND_DATE_TO => $dateTo
        ]);
        /* leave first order for customer */
        $result = [];
        foreach ($rs as $one) {
            $orderId = $one[\Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders\Builder::A_ORDER_ID];
            if (!isset($result[$orderId])) {
                $result[$orderId] = $one;
            }
        }
        return $result;
    }
}