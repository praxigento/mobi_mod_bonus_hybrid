<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Compress;

use Praxigento\BonusHybrid\Repo\Query\Compress\Phase1\GetPv\Builder as QBldGetPv;

/**
 * Helper for compressions calculations.
 */
class Helper
{

    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\Traits\TMap {
        mapValueById as private;
    }

    /** @var \Praxigento\BonusHybrid\Repo\Query\Compress\Phase1\GetPv\Builder */
    protected $qbGetPv;


    public function __construct(
        \Praxigento\BonusHybrid\Repo\Query\Compress\Phase1\GetPv\Builder $qbGetPv
    )
    {
        $this->qbGetPv = $qbGetPv;
    }

    /**
     * Get PV that are debited inside 'PV Write Off' operation related for the $calcId.
     *
     * @param int $calcId
     * @return array [custId => PV]
     */
    public function getPv($calcId)
    {
        $query = $this->qbGetPv->getSelectQuery();
        $conn = $query->getConnection();
        $bind = [QBldGetPv::BIND_CALC_ID => $calcId];
        $data = $conn->fetchAll($query, $bind);
        $result = $this->mapValueById($data, QBldGetPv::A_CUST_ID, QBldGetPv::A_PV);
        return $result;
    }
}