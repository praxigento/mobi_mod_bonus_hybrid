<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Accounting\Trans;

use Praxigento\Accounting\Repo\Entity\Data\Account as EAcc;
use Praxigento\Accounting\Repo\Entity\Data\Operation as EOper;
use Praxigento\Accounting\Repo\Entity\Data\Transaction as ETran;
use Praxigento\Accounting\Repo\Entity\Data\Type\Asset as ETypeAsset;
use Praxigento\Accounting\Repo\Entity\Data\Type\Operation as ETypeOper;
use Praxigento\BonusBase\Repo\Entity\Data\Log\Customers as ELogCust;
use Praxigento\Downline\Repo\Entity\Data\Customer as EDwnlCust;

/**
 * Build query to get transactions data for DCP Accounting Report.
 */
class Builder
    extends \Praxigento\Core\Repo\Query\Builder
{
    /** Tables aliases for external usage ('camelCase' naming) */
    const AS_ACC = 'acc';
    const AS_ASSET_TYPE = 'assType';
    const AS_DWNL_CUST = 'dwnlCust';
    const AS_LOG_CUST = 'logCust';
    const AS_OPER = 'opr';
    const AS_OPER_TYPE = 'oprType';
    const AS_TRAN = 'trn';

    /** Columns/expressions aliases for external usage ('camelCase' naming) */
    const A_ASSET = 'asset';
    const A_DATE = 'date';
    const A_DETAILS = 'details';
    const A_ITEM_ID = 'itemId';
    const A_OTHER_CUST_ID = 'otherCustId';
    const A_TYPE = 'type';
    const A_VALUE = 'value';

    /** Bound variables names ('camelCase' naming) */
    const BND_CUST_ID = 'custId';
    const BND_DATE_FROM = 'dateFrom';
    const BND_DATE_TO = 'dateTo';

    public function build(\Magento\Framework\DB\Select $source = null)
    {
        /* this is root query builder (started from SELECT) */
        $result = $this->conn->select();

        /* define tables aliases for internal usage (in this method) */
        $asAcc = self::AS_ACC;
        $asAssType = self::AS_ASSET_TYPE;
        $asDwnlCust = self::AS_DWNL_CUST;
        $asLogCust = self::AS_LOG_CUST;
        $asOper = self::AS_OPER;
        $asOperType = self::AS_OPER_TYPE;
        $asTrans = self::AS_TRAN;

        /* FROM prxgt_dwnl_customer */
        $tbl = $this->resource->getTableName(EDwnlCust::ENTITY_NAME);
        $as = $asDwnlCust;
        $cols = [];
        $result->from([$as => $tbl], $cols);    // standard names for the variables

        /* LEFT JOIN prxgt_acc_account */
        $tbl = $this->resource->getTableName(EAcc::ENTITY_NAME);
        $as = $asAcc;
        $cols = [];
        $cond = $as . '.' . EAcc::ATTR_CUST_ID . '=' . $asDwnlCust . '.' . EDwnlCust::ATTR_CUSTOMER_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN prxgt_acc_transaction */
        $tbl = $this->resource->getTableName(ETran::ENTITY_NAME);
        $as = $asTrans;
        $cols = [
            self::A_ITEM_ID => ETran::ATTR_ID,
            self::A_DATE => ETran::ATTR_DATE_APPLIED,
            self::A_DETAILS => ETran::ATTR_NOTE,
            self::A_VALUE => ETran::ATTR_VALUE
        ];
        $condDeb = $as . '.' . ETran::ATTR_DEBIT_ACC_ID . '=' . $asAcc . '.' . EAcc::ATTR_ID;
        $condCred = $as . '.' . ETran::ATTR_CREDIT_ACC_ID . '=' . $asAcc . '.' . EAcc::ATTR_ID;
        $cond = "($condDeb) OR ($condCred)";
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN prxgt_acc_operation */
        $tbl = $this->resource->getTableName(EOper::ENTITY_NAME);
        $as = $asOper;
        $cols = [];
        $cond = $as . '.' . EOper::ATTR_ID . '=' . $asTrans . '.' . ETran::ATTR_OPERATION_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN prxgt_acc_type_operation */
        $tbl = $this->resource->getTableName(ETypeOper::ENTITY_NAME);
        $as = $asOperType;
        $cols = [
            self::A_TYPE => ETypeOper::ATTR_CODE
        ];
        $cond = $as . '.' . ETypeOper::ATTR_ID . '=' . $asOper . '.' . EOper::ATTR_TYPE_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN prxgt_acc_type_asset */
        $tbl = $this->resource->getTableName(ETypeAsset::ENTITY_NAME);
        $as = $asAssType;
        $cols = [
            self::A_ASSET => ETypeAsset::ATTR_CODE
        ];
        $cond = $as . '.' . ETypeAsset::ATTR_ID . '=' . $asAcc . '.' . EAcc::ATTR_ASSET_TYPE_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN prxgt_bon_base_log_cust */
        $tbl = $this->resource->getTableName(ELogCust::ENTITY_NAME);
        $as = $asLogCust;
        $cols = [
            self::A_OTHER_CUST_ID => ELogCust::ATTR_CUSTOMER_ID
        ];
        $cond = $as . '.' . ELogCust::ATTR_TRANS_ID . '=' . $asTrans . '.' . ETran::ATTR_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /**
         * Query tuning.
         */
        /* WHERE */
        $result->where($asDwnlCust . '.' . EDwnlCust::ATTR_CUSTOMER_ID . '=:' . self::BND_CUST_ID);
        $result->where($asTrans . '.' . ETran::ATTR_DATE_APPLIED . '>:' . self::BND_DATE_FROM);
        $result->where($asTrans . '.' . ETran::ATTR_DATE_APPLIED . '<:' . self::BND_DATE_TO);

        /* ORDER */
        $result->order(self::A_ITEM_ID);

        return $result;
    }

}