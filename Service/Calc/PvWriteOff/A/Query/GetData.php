<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A\Query;

use Praxigento\Accounting\Repo\Data\Account as EAcc;
use Praxigento\Accounting\Repo\Data\Transaction as ETrans;
use Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A\Data\Trans as DTrans;

/**
 * Compose query to get accounting data for "PV Write Off" calculation:
 */
class GetData
    extends \Praxigento\Core\App\Repo\Query\Builder
{
    /** Tables aliases for external usage ('camelCase' naming) */
    const AS_ACC = 'paa';
    const AS_TRANS = 'pat';

    /** Columns/expressions aliases for external usage ('underscore' naming for database fields; 'camelCase' naming for aliases) */
    const A_ACC_ID_CREDIT = DTrans::A_ACC_ID_CREDIT;
    const A_ACC_ID_DEBIT = DTrans::A_ACC_ID_DEBIT;
    const A_AMOUNT = DTrans::A_AMOUNT;

    /** Bound variables names ('camelCase' naming) */
    const BND_ASSET_TYPE_ID = 'assetTypeId';
    const BND_DATE_FROM = 'dateFrom';
    const BND_DATE_TO = 'dateTo';

    public function build(\Magento\Framework\DB\Select $source = null)
    {
        $result = $this->conn->select();

        /* define tables aliases for internal usage (in this method) */
        $asTrans = self::AS_TRANS;
        $asAcc = self::AS_ACC;

        /* FROM prxgt_acc_transaction  */
        $tbl = $this->resource->getTableName(ETrans::ENTITY_NAME);
        $as = $asTrans;
        $cols = [
            self::A_ACC_ID_DEBIT => ETrans::A_DEBIT_ACC_ID,
            self::A_ACC_ID_CREDIT => ETrans::A_CREDIT_ACC_ID,
            self::A_AMOUNT => ETrans::A_VALUE
        ];
        $result->from([$as => $tbl], $cols);

        /* LEFT JOIN prxgt_acc_account (to filter by asset type ID) */
        $tbl = $this->resource->getTableName(EAcc::ENTITY_NAME);
        $as = $asAcc;
        $cols = [];
        $cond = $as . '.' . EAcc::A_ID . '=' . $asTrans . '.' . ETrans::A_DEBIT_ACC_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* query tuning */
        $result->where($asAcc . '.' . EAcc::A_ASSET_TYPE_ID . '=:' . self::BND_ASSET_TYPE_ID);
        $result->where($asTrans . '.' . ETrans::A_DATE_APPLIED . '>=:' . self::BND_DATE_FROM);
        $result->where($asTrans . '.' . ETrans::A_DATE_APPLIED . '<:' . self::BND_DATE_TO);

        return $result;
    }

}