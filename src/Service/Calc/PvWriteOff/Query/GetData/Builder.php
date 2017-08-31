<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\PvWriteOff\Query\GetData;

use Praxigento\Accounting\Repo\Entity\Data\Account as EAcc;
use Praxigento\Accounting\Repo\Entity\Data\Operation as EOper;
use Praxigento\Accounting\Repo\Entity\Data\Transaction as ETrans;
use Praxigento\BonusBase\Repo\Entity\Data\Log\Opers as ELogOpers;
use Praxigento\BonusHybrid\Service\Calc\PvWriteOff\Data\Trans as DTrans;

/**
 * Compose query to get accounting data for "PV Write Off" calculation:
 *
 * SELECT
 * `pao`.`id` AS `operId`,
 * `pat`.`debit_acc_id` AS `accIdDebit`,
 * `pat`.`credit_acc_id` AS `accIdCredit`,
 * `pat`.`value` AS `amount`
 * FROM `prxgt_acc_operation` AS `pao`
 * LEFT JOIN `prxgt_acc_transaction` AS `pat`
 * ON pat.operation_id = pao.id
 * LEFT JOIN `prxgt_acc_account` AS `paa`
 * ON paa.id = pat.debit_acc_id
 * LEFT JOIN `prxgt_bon_base_log_opers` AS `pbblo`
 * ON pbblo.oper_id = pao.id
 * WHERE (paa.asset_type_id = :assetTypeId)
 * AND (pat.date_applied >= :dateFrom)
 * AND (pat.date_applied < :dateTo)
 * AND (pbblo.calc_id IS NULL
 * OR pbblo.calc_id <> :calcId)
 */
class Builder
    extends \Praxigento\Core\Repo\Query\Builder
{
    /** Tables aliases for external usage ('camelCase' naming) */
    const AS_ACC = 'paa';
    const AS_LOG = 'pbblo';
    const AS_OPER = 'pao';
    const AS_TRANS = 'pat';

    /** Columns/expressions aliases for external usage ('underscore' naming for database fields; 'camelCase' naming for aliases) */
    const A_ACC_ID_CREDIT = DTrans::A_ACC_ID_CREDIT;
    const A_ACC_ID_DEBIT = DTrans::A_ACC_ID_DEBIT;
    const A_AMOUNT = DTrans::A_AMOUNT;
    const A_OPER_ID = DTrans::A_OPER_ID;

    /** Bound variables names ('camelCase' naming) */
    const BND_ASSET_TYPE_ID = 'assetTypeId';
    const BND_CALC_ID = 'calcId';
    const BND_DATE_FROM = 'dateFrom';
    const BND_DATE_TO = 'dateTo';
    const BND_OPER_TYPE_ID = 'operTypeId';

    public function build(\Magento\Framework\DB\Select $source = null)
    {
        $result = $this->conn->select();

        /* define tables aliases for internal usage (in this method) */
        $asOper = self::AS_OPER;
        $asTrans = self::AS_TRANS;
        $asAcc = self::AS_ACC;
        $asLog = self::AS_LOG;

        /* FROM prxgt_acc_operation  */
        $tbl = $this->resource->getTableName(EOper::ENTITY_NAME);
        $as = $asOper;
        $cols = [self::A_OPER_ID => EOper::ATTR_ID];
        $result->from([$as => $tbl], $cols);

        /* LEFT JOIN prxgt_acc_transaction */
        $tbl = $this->resource->getTableName(ETrans::ENTITY_NAME);
        $as = $asTrans;
        $cols = [
            self::A_ACC_ID_DEBIT => ETrans::ATTR_DEBIT_ACC_ID,
            self::A_ACC_ID_CREDIT => ETrans::ATTR_CREDIT_ACC_ID,
            self::A_AMOUNT => ETrans::ATTR_VALUE
        ];
        $cond = $as . '.' . ETrans::ATTR_OPERATION_ID . '=' . $asOper . '.' . EOper::ATTR_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN prxgt_acc_account */
        $tbl = $this->resource->getTableName(EAcc::ENTITY_NAME);
        $as = $asAcc;
        $cols = [];
        $cond = $as . '.' . EAcc::ATTR_ID . '=' . $asTrans . '.' . ETrans::ATTR_DEBIT_ACC_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN prxgt_bon_base_log_opers */
        $tbl = $this->resource->getTableName(ELogOpers::ENTITY_NAME);
        $as = $asLog;
        $cols = [];
        $cond = $as . '.' . ELogOpers::ATTR_OPER_ID . '=' . $asOper . '.' . EOper::ATTR_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* query tuning */
        $result->where($asAcc . '.' . EAcc::ATTR_ASSET_TYPE_ID . '=:' . self::BND_ASSET_TYPE_ID);
        $result->where($asTrans . '.' . ETrans::ATTR_DATE_APPLIED . '>=:' . self::BND_DATE_FROM);
        $result->where($asTrans . '.' . ETrans::ATTR_DATE_APPLIED . '<:' . self::BND_DATE_TO);
        $result->where($asOper . '.' . EOper::ATTR_TYPE_ID . '<>:' . self::BND_OPER_TYPE_ID);
        $condIsNull = $asLog . '.' . ELogOpers::ATTR_CALC_ID . ' IS NULL';
        $condNotCalcId = $asLog . '.' . ELogOpers::ATTR_CALC_ID . '<>:' . self::BND_CALC_ID;
        $result->where("$condIsNull OR $condNotCalcId");

        return $result;
    }

}