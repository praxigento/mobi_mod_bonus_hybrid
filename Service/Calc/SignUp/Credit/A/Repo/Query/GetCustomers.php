<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2020
 */

namespace Praxigento\BonusHybrid\Service\Calc\SignUp\Credit\A\Repo\Query;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;
use Praxigento\BonusHybrid\Repo\Data\Registry\SignUpDebit as ERegSignUp;
use Praxigento\Downline\Repo\Data\Customer as EDwnlCust;

/**
 * Get SignUp PV debit customers from registry and its paths in compressed downline.
 */
class GetCustomers
    extends \Praxigento\Core\App\Repo\Query\Builder
{
    /** Tables aliases for external usage ('camelCase' naming) */
    const AS_BON_DWNL = 'bd';
    const AS_DWNL = 'dwnl';
    const AS_REG = 'reg';
    const AS_SALE = 'sale';

    /** Columns/expressions aliases for external usage ('camelCase' naming) */
    const A_CUST_COUNTRY = 'custCountry';
    const A_CUST_ID = 'custId';
    const A_CUST_MLM_ID = 'custMlmId';
    const A_PATH = 'path';
    const A_SALE_ID = 'saleId';
    const A_SALE_INC_ID = 'saleIncId';

    /** Bound variables names ('camelCase' naming) */
    const BND_CALC_ID_COMPRESS = 'calcIdCompress';
    const BND_CALC_ID_DEBIT = 'calcIdDebit';

    /** Entities are used in the query */
    const E_BON_DWNL = EBonDwnl::ENTITY_NAME;
    const E_DWNL = EDwnlCust::ENTITY_NAME;
    const E_REG = ERegSignUp::ENTITY_NAME;
    const E_SALE = Cfg::ENTITY_MAGE_SALES_ORDER;

    public function build(\Magento\Framework\DB\Select $source = null)
    {
        /* this is root query builder (started from SELECT) */
        $result = $this->conn->select();

        /* define tables aliases for internal usage (in this method) */
        $asBonDwnl = self::AS_BON_DWNL;
        $asDwnl = self::AS_DWNL;
        $asReg = self::AS_REG;
        $asSale = self::AS_SALE;

        /* SELECT FROM prxgt_bon_hyb_reg_signup */
        $tbl = $this->resource->getTableName(self::E_REG);
        $as = $asReg;
        $cols = [
            self::A_CUST_ID => ERegSignUp::A_CUST_REF,
            self::A_SALE_ID => ERegSignUp::A_SALE_REF
        ];
        $result->from([$as => $tbl], $cols);

        /* LEFT JOIN prxgt_dwnl_customer */
        $tbl = $this->resource->getTableName(self::E_DWNL);
        $as = $asDwnl;
        $cols = [
            self::A_CUST_MLM_ID => EDwnlCust::A_MLM_ID,
            self::A_CUST_COUNTRY => EDwnlCust::A_COUNTRY_CODE
        ];
        $cond = "$as." . EDwnlCust::A_CUSTOMER_REF . "=$asReg." . ERegSignUp::A_CUST_REF;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN sales_order */
        $tbl = $this->resource->getTableName(self::E_SALE);
        $as = $asSale;
        $cols = [
            self::A_SALE_INC_ID => Cfg::E_SALE_ORDER_A_INCREMENT_ID
        ];
        $cond = "$as." . Cfg::E_SALE_ORDER_A_ENTITY_ID . "=$asReg." . ERegSignUp::A_SALE_REF;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN prxgt_bon_hyb_dwnl */
        $tbl = $this->resource->getTableName(self::E_BON_DWNL);
        $as = $asBonDwnl;
        $cols = [
            self::A_PATH => EBonDwnl::A_PATH
        ];
        $cond = "$as." . EBonDwnl::A_CUST_REF . "=$asReg." . ERegSignUp::A_CUST_REF;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* WHERE */
        $byCalcDebit = "$asReg." . ERegSignUp::A_CALC_REF . '=:' . self::BND_CALC_ID_DEBIT;
        $byCalcCompress = "$asBonDwnl." . EBonDwnl::A_CALC_REF . '=:' . self::BND_CALC_ID_COMPRESS;
        $where = "($byCalcDebit) AND ($byCalcCompress)";
        $result->where($where);

        return $result;
    }
}
