<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Service\Calc\Aggregate\A\Repo\Query;

use Praxigento\Accounting\Repo\Data\Account as EAcc;
use Praxigento\Accounting\Repo\Data\Operation as EOper;
use Praxigento\Accounting\Repo\Data\Transaction as ETran;
use Praxigento\Accounting\Repo\Data\Type\Asset as ETypeAsset;
use Praxigento\BonusBase\Repo\Data\Calculation as ECalc;
use Praxigento\BonusBase\Repo\Data\Log\Opers as ELog;
use Praxigento\BonusBase\Repo\Data\Period as EPeriod;
use Praxigento\BonusBase\Repo\Data\Type\Calc as ETypeCalc;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\Core\App\Repo\Query\Expression as AnExpression;

/**
 * Aggregate all bonus transactions for period by customer.
 */
class GetBonusTotals
    extends \Praxigento\Core\App\Repo\Query\Builder
{
    /** Tables aliases for external usage ('camelCase' naming) */
    const AS_ACC = 'acc';
    const AS_CALC = 'calc';
    const AS_LOG = 'log';
    const AS_OPER = 'oper';
    const AS_PERIOD = 'period';
    const AS_TRANS = 'tran';
    const AS_TYPE_ASSET = 'assetType';
    const AS_TYPE_CALC = 'calcType';

    /** Columns/expressions aliases for external usage ('camelCase' naming) */
    const A_ACC_ID = 'accId';
    const A_CUST_ID = 'custId';
    const A_TOTAL = 'total';

    /** Bound variables names ('camelCase' naming) */
    const BND_PERIOD_BEGIN = 'periodBegin';
    const BND_PERIOD_END = 'periodEnd';

    /** Entities are used in the query */
    const E_ACC = EAcc::ENTITY_NAME;
    const E_CALC = ECalc::ENTITY_NAME;
    const E_LOG = ELog::ENTITY_NAME;
    const E_OPER = EOper::ENTITY_NAME;
    const E_PERIOD = EPeriod::ENTITY_NAME;
    const E_TRAN = ETran::ENTITY_NAME;
    const E_TYPE_ASSET = ETypeAsset::ENTITY_NAME;
    const E_TYPE_CALC = ETypeCalc::ENTITY_NAME;


    public function build(\Magento\Framework\DB\Select $source = null)
    {
        $result = $this->conn->select();

        /* define tables aliases for internal usage (in this method) */
        $asAcc = self::AS_ACC;
        $asCalc = self::AS_CALC;
        $asLog = self::AS_LOG;
        $asOper = self::AS_OPER;
        $asPeriod = self::AS_PERIOD;
        $asTrans = self::AS_TRANS;
        $asTypeAsset = self::AS_TYPE_ASSET;
        $asTypeCalc = self::AS_TYPE_CALC;

        /* FROM prxgt_bon_base_type_calc  */
        $tbl = $this->resource->getTableName(self::E_TYPE_CALC);
        $as = $asTypeCalc;
        $cols = [];
        $result->from([$as => $tbl], $cols);

        /* LEFT JOIN prxgt_bon_base_period */
        $tbl = $this->resource->getTableName(self::E_PERIOD);
        $as = $asPeriod;
        $cols = [];
        $cond = "$as." . EPeriod::A_CALC_TYPE_ID . "=$asTypeCalc." . ETypeCalc::A_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN prxgt_bon_base_calc */
        $tbl = $this->resource->getTableName(self::E_CALC);
        $as = $asCalc;
        $cols = [];
        $cond = "$as." . ECalc::A_PERIOD_ID . "=$asPeriod." . EPeriod::A_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN prxgt_bon_base_log_opers */
        $tbl = $this->resource->getTableName(self::E_LOG);
        $as = $asLog;
        $cols = [];
        $cond = "$as." . ELog::A_CALC_ID . "=$asCalc." . ECalc::A_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN prxgt_acc_operation */
        $tbl = $this->resource->getTableName(self::E_OPER);
        $as = $asOper;
        $cols = [];
        $cond = "$as." . EOper::A_ID . "=$asLog." . ELog::A_OPER_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN prxgt_acc_transaction */
        $tbl = $this->resource->getTableName(self::E_TRAN);
        $as = $asTrans;
        $exp = "SUM($asTrans." . ETran::A_VALUE . ')';
        $exp = new AnExpression($exp);
        $cols = [
            self::A_TOTAL => $exp
        ];
        $cond = "$as." . ETran::A_OPERATION_ID . "=$asOper." . EOper::A_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN prxgt_acc_account */
        $tbl = $this->resource->getTableName(self::E_ACC);
        $as = $asAcc;
        $cols = [
            self::A_ACC_ID => EAcc::A_ID,
            self::A_CUST_ID => EAcc::A_CUST_ID
        ];
        $cond = "$as." . EAcc::A_ID . "=$asTrans." . ETran::A_CREDIT_ACC_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN prxgt_acc_type_asset */
        $tbl = $this->resource->getTableName(self::E_TYPE_ASSET);
        $as = $asTypeAsset;
        $cols = [];
        $cond = "$as." . ETypeAsset::A_ID . "=$asAcc." . EAcc::A_ASSET_TYPE_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* WHERE */
        $byType = "$asTypeCalc." . ETypeCalc::A_CODE . " IN (" .
            "'" . Cfg::CODE_TYPE_CALC_BONUS_SIGN_UP_DEBIT . "', " .
            "'" . Cfg::CODE_TYPE_CALC_BONUS_SIGN_UP_CREDIT . "', " .
            "'" . Cfg::CODE_TYPE_CALC_BONUS_PERSONAL . "', " .
            "'" . Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF . "', " .
            "'" . Cfg::CODE_TYPE_CALC_BONUS_TEAM_EU . "', " .
            "'" . Cfg::CODE_TYPE_CALC_BONUS_COURTESY . "', " .
            "'" . Cfg::CODE_TYPE_CALC_BONUS_OVERRIDE_DEF . "', " .
            "'" . Cfg::CODE_TYPE_CALC_BONUS_OVERRIDE_EU . "', " .
            "'" . Cfg::CODE_TYPE_CALC_BONUS_INFINITY_DEF . "', " .
            "'" . Cfg::CODE_TYPE_CALC_BONUS_INFINITY_EU . "')";
        $byBegin = "$asPeriod." . EPeriod::A_DSTAMP_BEGIN . "=:" . self::BND_PERIOD_BEGIN;
        $byEnd = "$asPeriod." . EPeriod::A_DSTAMP_END . "=:" . self::BND_PERIOD_END;
        $byState = "$asCalc." . ECalc::A_STATE . "='" . Cfg::CALC_STATE_COMPLETE . "'";
        $byAsset = "$asTypeAsset." . ETypeAsset::A_CODE . "='" . Cfg::CODE_TYPE_ASSET_BONUS . "'";
        $result->where("($byType) AND ($byBegin) AND ($byEnd) AND ($byState) AND ($byAsset)");

        /* GROUP */
        $result->group("$asAcc." . EAcc::A_ID);

        return $result;
    }
}
