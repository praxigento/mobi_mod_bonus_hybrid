<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\A\Fun\Rou;

use Praxigento\BonusBase\Repo\Query\Period\Calcs\Get\Builder as QBGetPeriodCalcs;
use Praxigento\BonusHybrid\Config as Cfg;

/**
 * Get complete calculations IDs by calc type code for given period bounds.
 */
class GetCalcs
{
    /** @var \Praxigento\BonusBase\Repo\Query\Period\Calcs\Get\Builder */
    private $qbGetPeriodCalcs;

    public function __construct(
        QBGetPeriodCalcs $qbGetPeriodCalcs
    )
    {
        $this->qbGetPeriodCalcs = $qbGetPeriodCalcs;
    }

    /**
     * Get complete calculations IDs by calc type code for given period bounds.
     *
     * @param string $dsBegin 20170101
     * @param string $dsEnd 20170131
     * @return array [calcCode=>calcId]
     */
    public function exec($dsBegin, $dsEnd)
    {
        $query = $this->qbGetPeriodCalcs->build();
        $bind = [
            QBGetPeriodCalcs::BND_DATE_BEGIN => $dsBegin,
            QBGetPeriodCalcs::BND_DATE_END => $dsEnd,
            QBGetPeriodCalcs::BND_STATE => Cfg::CALC_STATE_COMPLETE,
        ];

        $conn = $query->getConnection();
        $rs = $conn->fetchAll($query, $bind);

        $result = [];
        foreach ($rs as $one) {
            $calcType = $one[QBGetPeriodCalcs::A_CALC_TYPE_CODE];
            $calcId = $one[QBGetPeriodCalcs::A_CALC_ID];
            $result[$calcType] = $calcId;
        }
        return $result;
    }
}