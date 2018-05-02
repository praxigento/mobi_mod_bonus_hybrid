<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Compress\Phase2;

use Praxigento\BonusHybrid\Service\Calc\Compress\Z\Repo\Query\GetPhase1Pv as QBldGetPv;

/**
 * Get PV that are debited inside 'PV Write Off' operation related for the $calcId.
 */
class GetPv
{
    /** @var \Praxigento\Downline\Api\Helper\Tree */
    private $hlpTree;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Compress\Z\Repo\Query\GetPhase1Pv */
    private $qbGetPv;

    public function __construct(
        \Praxigento\Downline\Api\Helper\Tree $hlpTree,
        \Praxigento\BonusHybrid\Service\Calc\Compress\Z\Repo\Query\GetPhase1Pv $qbGetPv
    )
    {
        $this->hlpTree = $hlpTree;
        $this->qbGetPv = $qbGetPv;
    }

    /**
     * Get PV that are debited inside 'PV Write Off' operation related for the $calcId.
     *
     * @param int $calcId
     * @return array [$customer_id=>$pv, ...]
     */
    public function exec($calcId)
    {
        $query = $this->qbGetPv->build();
        $conn = $query->getConnection();
        $bind = [QBldGetPv::BND_CALC_ID => $calcId];
        $data = $conn->fetchAll($query, $bind);
        $result = $this->hlpTree->mapValueById($data, QBldGetPv::A_CUST_ID, QBldGetPv::A_PV);
        return $result;
    }
}