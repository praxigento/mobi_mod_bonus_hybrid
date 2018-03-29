<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Calc\SignUpDebit;

class GetOrders
{
    /** @var \Praxigento\BonusHybrid\Service\Calc\SignUpDebit\A\Repo\Query\GetOrders */
    private $qbuildGetOrders;

    public function __construct(
        \Praxigento\BonusHybrid\Service\Calc\SignUpDebit\A\Repo\Query\GetOrders $qbuildGetOrders
    )
    {
        $this->qbuildGetOrders = $qbuildGetOrders;
    }

    /**
     * @param \Praxigento\BonusHybrid\Service\Calc\SignUpDebit\GetOrders\Request $opts
     * @return array
     */
    public function exec($opts)
    {
        $dateFrom = $opts->dateFrom;
        $dateTo = $opts->dateTo;
        /** @var  $query */
        $query = $this->qbuildGetOrders->build();
        $conn = $query->getConnection();
        $rs = $conn->fetchAll($query, [
            \Praxigento\BonusHybrid\Service\Calc\SignUpDebit\A\Repo\Query\GetOrders::BND_DATE_FROM => $dateFrom,
            \Praxigento\BonusHybrid\Service\Calc\SignUpDebit\A\Repo\Query\GetOrders::BND_DATE_TO => $dateTo
        ]);
        /* leave first order for customer */
        $result = [];
        foreach ($rs as $one) {
            $orderId = $one[\Praxigento\BonusHybrid\Service\Calc\SignUpDebit\A\Repo\Query\GetOrders::A_ORDER_ID];
            if (!isset($result[$orderId])) {
                $result[$orderId] = $one;
            }
        }
        return $result;
    }
}