<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\PvWriteOff\Query\GetData;

use Praxigento\Accounting\Repo\Entity\Data\Account as EAcc;
use Praxigento\Accounting\Repo\Entity\Data\Transaction as ETrans;
use Praxigento\BonusHybrid\Service\Calc\PvWriteOff\Data\Trans as DTrans;

/**
 * Compose query to get accounting data for "PV Write Off" calculation:
 *
 * SELECT
 * `pat`.`debit_acc_id` AS `accIdDebit`,
 * `pat`.`credit_acc_id` AS `accIdCredit`,
 * `pat`.`value` AS `amount`
 * FROM
 * `prxgt_acc_transaction` AS `pat`
 * LEFT JOIN `prxgt_acc_account` AS `paa` ON
 * paa.id = pat.debit_acc_id
 * WHERE
 * (
 * paa.asset_type_id =:assetTypeId
 * )
 * AND(
 * pat.date_applied >=:dateFrom
 * )
 * AND(
 * pat.date_applied <:dateTo
 * )
 */
class Builder
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
            self::A_ACC_ID_DEBIT => ETrans::ATTR_DEBIT_ACC_ID,
            self::A_ACC_ID_CREDIT => ETrans::ATTR_CREDIT_ACC_ID,
            self::A_AMOUNT => ETrans::ATTR_VALUE
        ];
        $result->from([$as => $tbl], $cols);

        /* LEFT JOIN prxgt_acc_account (to filter by asset type ID) */
        $tbl = $this->resource->getTableName(EAcc::ENTITY_NAME);
        $as = $asAcc;
        $cols = [];
        $cond = $as . '.' . EAcc::ATTR_ID . '=' . $asTrans . '.' . ETrans::ATTR_DEBIT_ACC_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* query tuning */
        $result->where($asAcc . '.' . EAcc::ATTR_ASSET_TYPE_ID . '=:' . self::BND_ASSET_TYPE_ID);
        $result->where($asTrans . '.' . ETrans::ATTR_DATE_APPLIED . '>=:' . self::BND_DATE_FROM);
        $result->where($asTrans . '.' . ETrans::ATTR_DATE_APPLIED . '<:' . self::BND_DATE_TO);

        return $result;
    }

}