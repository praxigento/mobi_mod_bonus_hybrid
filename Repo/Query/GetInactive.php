<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Repo\Query;

use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;
use Praxigento\BonusHybrid\Repo\Data\Downline\Inactive as EBonInact;

/**
 * Get customers inactivity data for given period (unqualified months).
 */
class GetInactive
    extends \Praxigento\Core\App\Repo\Query\Builder
{
    /** Tables aliases for external usage ('camelCase' naming) */
    const AS_DWNL = 'dwnl';
    const AS_INACT = 'inact';

    /** Columns/expressions aliases for external usage ('camelCase' naming) */
    const A_CUST_REF = 'custRef';
    const A_MONTHS = 'months';

    /** Bound variables names ('camelCase' naming) */
    const BND_CALC_ID = 'calcId';

    /** Entities are used in the query */
    const E_DWNL = EBonDwnl::ENTITY_NAME;
    const E_INACT = EBonInact::ENTITY_NAME;


    public function build(\Magento\Framework\DB\Select $source = null)
    {
        /* this is root query builder (started from SELECT) */
        $result = $this->conn->select();

        /* define tables aliases for internal usage (in this method) */
        $asDwnl = self::AS_DWNL;
        $asInact = self::AS_INACT;

        /* FROM prxgt_bon_hyb_dwnl */
        $tbl = $this->resource->getTableName(self::E_DWNL);    // name with prefix
        $as = $asDwnl;    // alias for 'current table' (currently processed in this block of code)
        $cols = [
            self::A_CUST_REF => EBonDwnl::A_CUST_REF
        ];
        $result->from([$as => $tbl], $cols);    // standard names for the variables

        /* LEFT JOIN prxgt_bon_hyb_dwnl_inact */
        $tbl = $this->resource->getTableName(self::E_INACT);
        $as = $asInact;
        $cols = [
            self::A_MONTHS => EBonInact::A_INACT_MONTHS
        ];
        $cond = "$as." . EBonInact::A_TREE_ENTRY_REF . "=$asDwnl." . EBonDwnl::A_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* query tuning */
        $byCalc = "$asDwnl." . EBonDwnl::A_CALC_REF . "=:" . self::BND_CALC_ID;
        $byNotNull = "$asInact." . EBonInact::A_TREE_ENTRY_REF . " IS NOT NULL";
        $result->where("($byCalc) AND ($byNotNull)");

        return $result;
    }
}