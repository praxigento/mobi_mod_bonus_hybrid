<?php

namespace Praxigento\BonusHybrid\Ui\DataProvider\Grid\Customer\Downgrade;

use Praxigento\BonusBase\Repo\Data\Calculation as ECalc;
use Praxigento\BonusBase\Repo\Data\Period as EPeriod;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Registry\Downgrade as ERegDwngrd;
use Praxigento\Downline\Repo\Data\Customer as EDwnlCust;

class Query
    extends \Praxigento\Core\App\Ui\DataProvider\Grid\Query\Builder
{

    /**#@+ Tables aliases for external usage ('camelCase' naming) */
    const AS_CALC = 'calc';
    const AS_CUST = 'cust';
    const AS_CUST_DWNL = 'dwnl';
    const AS_PERIOD = 'period';
    const AS_REG_DWNGRD = 'reg';
    /**#@- */

    /**#@+ Columns/expressions aliases for external usage */
    const A_CALC_ID = 'calcId';
    const A_COUNTRY_CODE = 'countryCode';
    const A_CUST_ID = 'custId';
    const A_CUST_MLM_ID = 'custMlmId';
    const A_CUST_NAME = 'custName';
    const A_DATE_CREATED = 'dateCreated';
    const A_EMAIL = 'email';
    const A_PERIOD = 'period';
    /**#@- */

    /**
     * Construct expression for customer name ("firstName lastName").
     */
    public function getExpForCustName()
    {
        $value = 'CONCAT(' . Cfg::E_CUSTOMER_A_FIRSTNAME . ", ' ', " . Cfg::E_CUSTOMER_A_LASTNAME . ')';
        $result = new \Praxigento\Core\App\Repo\Query\Expression($value);
        return $result;
    }

    /**
     * Construct expression for period (YYYYMMDD => YYYYMM).
     */
    public function getExpForPeriod()
    {
        $value = 'SUBSTRING(' . EPeriod::A_DSTAMP_END . ', 1, 6)';
        $result = new \Praxigento\Core\App\Repo\Query\Expression($value);
        return $result;
    }

    protected function getMapper()
    {
        if (is_null($this->mapper)) {
            $map = [
                self::A_CALC_ID => self::AS_REG_DWNGRD . '.' . ERegDwngrd::A_CALC_REF,
                self::A_COUNTRY_CODE => self::AS_CUST_DWNL . '.' . EDwnlCust::A_COUNTRY_CODE,
                self::A_CUST_ID => self::AS_REG_DWNGRD . '.' . ERegDwngrd::A_CUST_REF,
                self::A_CUST_MLM_ID => self::AS_CUST_DWNL . '.' . EDwnlCust::A_MLM_ID,
                self::A_CUST_NAME => $this->getExpForCustName(),
                self::A_DATE_CREATED => self::AS_CUST . '.' . Cfg::E_CUSTOMER_A_CREATED_AT,
                self::A_EMAIL => self::AS_CUST . '.' . Cfg::E_CUSTOMER_A_EMAIL,
                self::A_PERIOD => $this->getExpForPeriod()
            ];
            $this->mapper = new \Praxigento\Core\App\Repo\Query\Criteria\Def\Mapper($map);
        }
        $result = $this->mapper;
        return $result;
    }

    protected function getQueryItems()
    {
        $result = $this->conn->select();

        /* define tables aliases for internal usage (in this method) */
        $asCalc = self::AS_CALC;
        $asCust = self::AS_CUST;
        $asDwnl = self::AS_CUST_DWNL;
        $asReg = self::AS_REG_DWNGRD;
        $asPeriod = self::AS_PERIOD;

        /* SELECT FROM prxgt_bon_hyb_reg_downgrade */
        $tbl = $this->resource->getTableName(ERegDwngrd::ENTITY_NAME);
        $as = $asReg;
        $cols = [
            self::A_CALC_ID => ERegDwngrd::A_CALC_REF,
            self::A_CUST_ID => ERegDwngrd::A_CUST_REF
        ];
        $result->from([$as => $tbl], $cols);

        /* LEFT JOIN prxgt_bon_base_calc */
        $tbl = $this->resource->getTableName(ECalc::ENTITY_NAME);
        $as = $asCalc;
        $cols = [];
        $cond = $as . '.' . ECalc::A_ID . '=' . $asReg . '.' . ERegDwngrd::A_CALC_REF;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN prxgt_bon_base_period */
        $tbl = $this->resource->getTableName(EPeriod::ENTITY_NAME);
        $as = $asPeriod;
        $exp = $this->getExpForPeriod();
        $cols = [
            self::A_PERIOD => $exp
        ];
        $cond = $as . '.' . EPeriod::A_ID . '=' . $asCalc . '.' . ECalc::A_PERIOD_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN customer_entity */
        $tbl = $this->resource->getTableName(Cfg::ENTITY_MAGE_CUSTOMER);
        $as = $asCust;
        $exp = $this->getExpForCustName();
        $cols = [
            self::A_CUST_NAME => $exp,
            self::A_DATE_CREATED => Cfg::E_CUSTOMER_A_CREATED_AT,
            self::A_EMAIL=> Cfg::E_CUSTOMER_A_EMAIL
        ];
        $cond = $as . '.' . Cfg::E_CUSTOMER_A_ENTITY_ID . '=' . $asReg . '.' . ERegDwngrd::A_CUST_REF;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN prxgt_dwnl_customer */
        $tbl = $this->resource->getTableName(EDwnlCust::ENTITY_NAME);
        $as = $asDwnl;
        $cols = [
            self::A_CUST_MLM_ID => EDwnlCust::A_MLM_ID,
            self::A_COUNTRY_CODE => EDwnlCust::A_COUNTRY_CODE
        ];
        $cond = $as . '.' . EDwnlCust::A_CUSTOMER_REF . '=' . $asReg . '.' . ERegDwngrd::A_CUST_REF;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* return  result */
        return $result;
    }

    protected function getQueryTotal()
    {
        /* get query to select items */
        /** @var \Magento\Framework\DB\Select $result */
        $result = $this->getQueryItems();
        /* ... then replace "columns" part with own expression */
        $value = 'COUNT(' . self::AS_REG_DWNGRD . '.' . ERegDwngrd::A_CUST_REF . ')';

        /**
         * See method \Magento\Framework\DB\Select\ColumnsRenderer::render:
         */
        /**
         * if ($column instanceof \Zend_Db_Expr) {...}
         */
        $exp = new \Praxigento\Core\App\Repo\Query\Expression($value);
        /**
         *  list($correlationName, $column, $alias) = $columnEntry;
         */
        $entry = [null, $exp, null];
        $cols = [$entry];
        $result->setPart('columns', $cols);
        return $result;
    }
}
