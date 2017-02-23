<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Calc\Sub;

class SignupDebit
{
    /** @var \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders\Builder */
    protected $qbuildGetOrders;

    public function __construct(
        \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders\Builder $qbuildGetOrders
    ) {
        $this->qbuildGetOrders = $qbuildGetOrders;
    }

    /**
     * @param \Praxigento\BonusHybrid\Service\Calc\Sub\SignupDebit\Request $opts
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
            $custId = $one[\Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders\Builder::A_CUST_ID];
            if (!isset($result[$custId])) {
                $result[$custId] = $one;
            }
        }
        return $result;
    }
}