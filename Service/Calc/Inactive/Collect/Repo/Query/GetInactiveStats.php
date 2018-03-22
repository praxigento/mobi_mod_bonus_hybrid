<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 17.2.12
 * Time: 16:38
 */

namespace Praxigento\BonusHybrid\Service\Calc\Inactive\Collect\Repo\Query;


use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;
use Praxigento\BonusHybrid\Repo\Data\Downline\Inactive as EInact;

class GetInactiveStats
    extends \Praxigento\Core\App\Repo\Query\Builder
{
    /** Tables aliases for external usage ('camelCase' naming) */
    const AS_BONUS_DWNL = 'bonDwnl';
    const AS_STATS = 'stats';

    /** Columns/expressions aliases for external usage ('camelCase' naming) */
    const A_CUST_REF = 'custRef';
    const A_MONTHS_INACT = 'monthsInact';

    /** Bound variables names ('camelCase' naming) */
    const BND_CALC_REF = 'calcRef';

    /** Entities are used in the query */
    const E_BON_DWNL = EBonDwnl::ENTITY_NAME;
    const E_INACT = EInact::ENTITY_NAME;

    public function build(\Magento\Framework\DB\Select $source = null)
    {
        $result = $this->conn->select();

        /* define tables aliases for internal usage (in this method) */
        $asBonDwnl = self::AS_BONUS_DWNL;
        $asStats = self::AS_STATS;

        /* FROM prxgt_bon_hyb_dwnl */
        $tbl = $this->resource->getTableName(self::E_BON_DWNL);
        $as = $asBonDwnl;
        $cols = [
            self::A_CUST_REF => EBonDwnl::ATTR_CUST_REF
        ];
        $result->from([$as => $tbl], $cols);

        /* LEFT JOIN prxgt_bon_hyb_dwnl_inact */
        $tbl = $this->resource->getTableName(self::E_INACT);
        $as = $asStats;
        $cols = [
            self::A_MONTHS_INACT => EInact::ATTR_INACT_MONTHS
        ];
        $cond = $as . '.' . EInact::ATTR_TREE_ENTRY_REF . '=' . $asBonDwnl . '.' . EBonDwnl::ATTR_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* query tuning */
        $byCalcId = $asBonDwnl . '.' . EBonDwnl::ATTR_CALC_REF . '=:' . self::BND_CALC_REF;
        $byNotNull = $asStats . '.' . EInact::ATTR_INACT_MONTHS . '>0';
        $result->where("($byCalcId) AND ($byNotNull)");

        return $result;

    }
}