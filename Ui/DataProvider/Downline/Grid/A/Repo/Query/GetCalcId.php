<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Ui\DataProvider\Downline\Grid\A\Repo\Query;

use Praxigento\BonusBase\Repo\Data\Calculation as ECalc;
use Praxigento\BonusBase\Repo\Data\Period as EPeriod;
use Praxigento\BonusBase\Repo\Data\Type\Calc as ETypeCalc;
use Praxigento\BonusHybrid\Config as Cfg;

/**
 * Query calculation ID by period & calculation type.
 */
class GetCalcId
    extends \Praxigento\Core\App\Repo\Query\Builder
{
    /** Tables aliases for external usage ('camelCase' naming) */
    const AS_CALC = 'c';
    const AS_PERIOD = 'p';
    const AS_TYPE = 't';

    /** Columns/expressions aliases for external usage */
    const A_CALC_ID = 'calcId';

    /** Bound variables names ('camelCase' naming) */
    const BND_DS_BEGIN = 'dsBegin';
    const BND_TYPE_CODE_FORECAST = 'typeCodeFor';
    const BND_TYPE_CODE_REGULAR = 'typeCodeReg';

    /** Entities are used in the query */
    const E_CALC = ECalc::ENTITY_NAME;
    const E_PERIOD = EPeriod::ENTITY_NAME;
    const E_TYPE = ETypeCalc::ENTITY_NAME;


    public function build(\Magento\Framework\DB\Select $source = null)
    {
        /* this is root query builder (started from SELECT) */
        $result = $this->conn->select();

        /* define tables aliases for internal usage (in this method) */
        $asCalc = self::AS_CALC;
        $asPeriod = self::AS_PERIOD;
        $asType = self::AS_TYPE;

        /* FROM prxgt_bon_base_period */
        $tbl = $this->resource->getTableName(self::E_PERIOD);    // name with prefix
        $as = $asPeriod;    // alias for 'current table' (currently processed in this block of code)
        $cols = [];
        $result->from([$as => $tbl], $cols);    // standard names for the variables

        /* LEFT JOIN prxgt_bon_base_calc */
        $tbl = $this->resource->getTableName(self::E_CALC);
        $as = $asCalc;
        $cols = [
            self::A_CALC_ID => ECalc::A_ID
        ];
        $cond = "$as." . ECalc::A_PERIOD_ID . "=$asPeriod." . EPeriod::A_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN prxgt_bon_base_type_calc */
        $tbl = $this->resource->getTableName(self::E_TYPE);
        $as = $asType;
        $cols = [];
        $cond = "$as." . ETypeCalc::A_ID . "=$asPeriod." . EPeriod::A_CALC_TYPE_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* WHERE */
        $byDsBegin = "$asPeriod." . EPeriod::A_DSTAMP_BEGIN . "=:" . self::BND_DS_BEGIN;
        $byCalcTypeReg = "$asType." . ETypeCalc::A_CODE . "=:" . self::BND_TYPE_CODE_REGULAR;
        $byCalcTypeFor = "$asType." . ETypeCalc::A_CODE . "=:" . self::BND_TYPE_CODE_FORECAST;
        $byCalcType = "($byCalcTypeReg) OR ($byCalcTypeFor)";
        $quoted = $this->conn->quote(Cfg::CALC_STATE_COMPLETE);
        $byComplete = "$asCalc." . ECalc::A_STATE . "=$quoted";
        $result->where("($byDsBegin) AND ($byCalcType) AND ($byComplete)");

        /* ORDER*/
        $result->order("$asCalc." . ECalc::A_DATE_ENDED . " DESC");

        return $result;
    }

}