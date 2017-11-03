<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\Totals\Db\Query;

use Praxigento\Accounting\Repo\Entity\Data\Account as EAcc;
use Praxigento\Accounting\Repo\Entity\Data\Transaction as ETrans;
use Praxigento\BonusBase\Repo\Entity\Data\Log\Opers as ELogOper;

/**
 * Build query to get summary for amounts of many transactional bonus (team, for example).
 */
class GetSum
    extends \Praxigento\Core\Repo\Query\Builder
{
    /** Tables aliases for external usage ('camelCase' naming) */
    const AS_ACC = 'acc';
    const AS_LOG = 'log';
    const AS_TRANS = 'trans';

    /** Columns/expressions aliases for external usage ('camelCase' naming) */
    const A_AMOUNT = 'amount';

    /** Bound variables names ('camelCase' naming) */
    const BND_CALC_ID = 'calcId';
    const BND_CUST_ID = 'custId';

    /** Entities are used in the query */
    const E_ACC = EAcc::ENTITY_NAME;
    const E_LOG = ELogOper::ENTITY_NAME;
    const E_TRANS = ETrans::ENTITY_NAME;

    public function build(\Magento\Framework\DB\Select $source = null)
    {
        $result = $this->conn->select();

        /* define tables aliases for internal usage (in this method) */
        $asAcc = self::AS_ACC;
        $asLog = self::AS_LOG;
        $asTrans = self::AS_TRANS;

        /* FROM prxgt_bon_base_log_opers */
        $tbl = $this->resource->getTableName(ELogOper::ENTITY_NAME);
        $as = $asLog;
        $cols = [];
        $result->from([$as => $tbl], $cols);

        /* LEFT JOIN prxgt_acc_transaction to get link to accounts */
        $tbl = $this->resource->getTableName(ETrans::ENTITY_NAME);
        $as = $asTrans;
        $source = 'SUM(' . ETrans::ATTR_VALUE . ')';
        $exp = new \Praxigento\Core\Repo\Query\Expression($source);
        $cols = [
            self::A_AMOUNT => $exp
        ];
        $cond = $as . '.' . ETrans::ATTR_OPERATION_ID . '=' . $asLog . '.' . ELogOper::ATTR_OPER_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN prxgt_acc_account to filter by customer id */
        $tbl = $this->resource->getTableName(EAcc::ENTITY_NAME);
        $as = $asAcc;
        $cols = [];
        $cond = $as . '.' . EAcc::ATTR_ID . '=' . $asTrans . '.' . ETrans::ATTR_CREDIT_ACC_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* query tuning */
        $byCalcId = "$asLog." . ELogOper::ATTR_CALC_ID . "=:" . self::BND_CALC_ID;
        $byCustId = "$asAcc." . EAcc::ATTR_CUST_ID . "=:" . self::BND_CUST_ID;
        $result->where("($byCalcId) AND ($byCustId)");

        return $result;
    }


}