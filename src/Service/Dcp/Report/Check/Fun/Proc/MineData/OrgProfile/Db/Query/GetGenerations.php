<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\OrgProfile\Db\Query;

use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EBonDwnl;

/**
 * Get downline tree data by generations.
 */
class GetGenerations
    extends \Praxigento\Core\Repo\Query\Builder
{

    /** Tables aliases for external usage ('camelCase' naming) */
    const AS_BON_DWNL = 'bonDwnl';

    /** Columns/expressions aliases for external usage ('camelCase' naming) */
    const A_COUNT = 'count';
    const A_DEPTH = 'depth';
    const A_VOLUME = 'volume';

    /** Bound variables names ('camelCase' naming) */
    const BND_CALC_ID = 'calcId';
    const BND_PATH = 'path';
    const BND_PV = 'pv';

    /** Entities are used in the query */
    const E_BON_DWNL = EBonDwnl::ENTITY_NAME;


    public function build(\Magento\Framework\DB\Select $source = null)
    {
        /* this is root query builder (started from SELECT) */
        $result = $this->conn->select();

        /* define tables aliases for internal usage (in this method) */
        $asBonDwnl = self::AS_BON_DWNL;

        /* FROM prxgt_bon_hyb_dwnl  */
        $tbl = $this->resource->getTableName(EBonDwnl::ENTITY_NAME);
        $as = $asBonDwnl;
        $expCountSrc = 'COUNT(' . EBonDwnl::ATTR_CUST_REF . ')';
        $expCount = new \Praxigento\Core\Repo\Query\Expression($expCountSrc);
        $expVolumeSrc = 'SUM(' . EBonDwnl::ATTR_PV . ')';
        $expVolume = new \Praxigento\Core\Repo\Query\Expression($expVolumeSrc);
        $cols = [
            self::A_DEPTH => EBonDwnl::ATTR_DEPTH,
            self::A_COUNT => $expCount,
            self::A_VOLUME => $expVolume
        ];
        $result->from([$as => $tbl], $cols);

        /* query tuning */
        $byCalcId = "$asBonDwnl." . EBonDwnl::ATTR_CALC_REF . ' = :' . self::BND_CALC_ID;
        $byPath = "$asBonDwnl." . EBonDwnl::ATTR_PATH . ' LIKE :' . self::BND_PATH;
        $byPv = "$asBonDwnl." . EBonDwnl::ATTR_PV . ' > :' . self::BND_PV;
        $result->where("($byCalcId) AND ($byPath) AND ($byPv)");

        /* group by */
        $result->group($asBonDwnl . '.' . EBonDwnl::ATTR_DEPTH);

        return $result;
    }
}