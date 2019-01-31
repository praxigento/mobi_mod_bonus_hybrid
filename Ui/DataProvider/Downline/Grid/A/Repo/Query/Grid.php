<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Ui\DataProvider\Downline\Grid\A\Repo\Query;

use Praxigento\BonusBase\Repo\Data\Rank as ERank;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;
use Praxigento\Core\App\Repo\Query\Expression as AnExpression;
use Praxigento\Downline\Repo\Data\Customer as EDwnlCust;

class Grid
    extends \Praxigento\Core\App\Ui\DataProvider\Grid\Query\Builder
{

    /** Tables aliases for external usage ('camelCase' naming) */
    const AS_CUST = 'cust';
    const AS_DWNL_BON = 'dwnlBon';
    const AS_DWNL_CUST = 'dwnlCust';
    const AS_DWNL_PARENT = 'dwnlParent';
    const AS_PARENT = 'parent';
    const AS_RANK = 'ranks';

    /** Columns/expressions aliases for external usage */
    const A_CALC_ID = 'calcId';
    const A_CUST_ID = 'custId';
    const A_CUST_MLM_ID = 'custMlmId';
    const A_CUST_NAME = 'custName';
    const A_DEPTH = 'depth';
    const A_MONTH_UNQ = 'monthUnq';
    const A_OV = 'ov';
    const A_PARENT_ID = 'parentId';
    const A_PARENT_MLM_ID = 'parentMlmId';
    const A_PARENT_NAME = 'parentName';
    const A_PATH = 'path';
    const A_PV = 'pv';
    const A_RANK = 'rank';
    const A_TV = 'tv';

    /** Bound variables names ('camelCase' naming) */
    const BND_CALC_ID = 'calcId';

    /** Entities are used in the query */
    const E_BON = EBonDwnl::ENTITY_NAME;
    const E_CUST = Cfg::ENTITY_MAGE_CUSTOMER;
    const E_DWNL = EDwnlCust::ENTITY_NAME;
    const E_RANK = ERank::ENTITY_NAME;

    private function expFullNameCust()
    {
        $fullName = 'CONCAT(' . self::AS_CUST . '.' . Cfg::E_CUSTOMER_A_FIRSTNAME . ', " ", '
            . self::AS_CUST . '.' . Cfg::E_CUSTOMER_A_LASTNAME . ')';
        $result = new AnExpression($fullName);
        return $result;
    }

    private function expFullNameParent()
    {
        $fullName = 'CONCAT(' . self::AS_PARENT . '.' . Cfg::E_CUSTOMER_A_FIRSTNAME . ', " ", '
            . self::AS_PARENT . '.' . Cfg::E_CUSTOMER_A_LASTNAME . ')';
        $result = new AnExpression($fullName);
        return $result;
    }


    protected function getMapper()
    {
        if (is_null($this->mapper)) {
            $expNameCust = $this->expFullNameCust();
            $expNameParent = $this->expFullNameParent();
            $map = [
                self::A_CALC_ID => self::AS_DWNL_BON . '.' . EBonDwnl::A_CALC_REF,
                self::A_CUST_ID => self::AS_DWNL_BON . '.' . EBonDwnl::A_CUST_REF,
                self::A_DEPTH => self::AS_DWNL_BON . '.' . EBonDwnl::A_DEPTH,
                self::A_OV => self::AS_DWNL_BON . '.' . EBonDwnl::A_OV,
                self::A_PARENT_ID => self::AS_DWNL_BON . '.' . EBonDwnl::A_PARENT_REF,
                self::A_PATH => self::AS_DWNL_BON . '.' . EBonDwnl::A_PATH,
                self::A_PV => self::AS_DWNL_BON . '.' . EBonDwnl::A_PV,
                self::A_TV => self::AS_DWNL_BON . '.' . EBonDwnl::A_TV,
                self::A_RANK => self::AS_RANK . '.' . ERank::A_CODE,
                self::A_MONTH_UNQ => self::AS_DWNL_BON . '.' . EBonDwnl::A_UNQ_MONTHS,
                self::A_CUST_MLM_ID => self::AS_DWNL_CUST . '.' . EDwnlCust::A_MLM_ID,
                self::A_CUST_NAME => $expNameCust,
                self::A_PARENT_MLM_ID => self::AS_DWNL_PARENT . '.' . EDwnlCust::A_MLM_ID,
                self::A_PARENT_NAME => $expNameParent

            ];
            $this->mapper = new \Praxigento\Core\App\Repo\Query\Criteria\Def\Mapper($map);
        }
        $result = $this->mapper;
        return $result;
    }

    /**
     * SELECT
     * `dwnlBon`.`calc_ref` AS `calcId`,
     * `dwnlBon`.`cust_ref` AS `custId`,
     * `dwnlBon`.`depth`,
     * `dwnlBon`.`ov`,
     * `dwnlBon`.`parent_ref` AS `parentId`,
     * `dwnlBon`.`path`,
     * `dwnlBon`.`pv`,
     * `dwnlBon`.`tv`,
     * `dwnlBon`.`unq_months` AS `monthUnq`,
     * `ranks`.`code` AS `rank`,
     * `dwnlCust`.`mlm_id` AS `custMlmId`,
     * (CONCAT(cust.firstname,
     * " ",
     * cust.lastname)) AS `custName`,
     * `dwnlParent`.`mlm_id` AS `parentMlmId`,
     * (CONCAT(parent.firstname,
     * " ",
     * parent.lastname)) AS `parentName`
     * FROM
     * `prxgt_bon_hyb_dwnl` AS `dwnlBon`
     * LEFT JOIN `prxgt_bon_base_rank` AS `ranks` ON
     * ranks.id = dwnlBon.rank_ref
     * LEFT JOIN `prxgt_dwnl_customer` AS `dwnlCust` ON
     * dwnlCust.customer_ref = dwnlBon.cust_ref
     * LEFT JOIN `customer_entity` AS `cust` ON
     * cust.entity_id = dwnlBon.cust_ref
     * LEFT JOIN `prxgt_dwnl_customer` AS `dwnlParent` ON
     * dwnlParent.customer_ref = dwnlBon.parent_ref
     * LEFT JOIN `customer_entity` AS `parent` ON
     * parent.entity_id = dwnlBon.parent_ref
     * WHERE
     * (dwnlBon.calc_ref =:calcId)
     */
    protected function getQueryItems()
    {
        $result = $this->conn->select();

        /* define tables aliases for internal usage (in this method) */
        $asCust = self::AS_CUST;
        $asDwnlBon = self::AS_DWNL_BON;
        $asDwnlCust = self::AS_DWNL_CUST;
        $asDwnlParent = self::AS_DWNL_PARENT;
        $asParent = self::AS_PARENT;
        $asRank = self::AS_RANK;

        /* SELECT FROM prxgt_bon_hyb_dwnl */
        $tbl = $this->resource->getTableName(self::E_BON);
        $as = $asDwnlBon;
        $cols = [
            self::A_CALC_ID => EBonDwnl::A_CALC_REF,
            self::A_CUST_ID => EBonDwnl::A_CUST_REF,
            self::A_DEPTH => EBonDwnl::A_DEPTH,
            self::A_OV => EBonDwnl::A_OV,
            self::A_PARENT_ID => EBonDwnl::A_PARENT_REF,
            self::A_PATH => EBonDwnl::A_PATH,
            self::A_PV => EBonDwnl::A_PV,
            self::A_TV => EBonDwnl::A_TV,
            self::A_MONTH_UNQ => EBonDwnl::A_UNQ_MONTHS
        ];
        $result->from([$as => $tbl], $cols);

        /* LEFT JOIN prxgt_bon_base_rank */
        $tbl = $this->resource->getTableName(self::E_RANK);
        $as = $asRank;
        $cols = [
            self::A_RANK => ERank::A_CODE
        ];
        $cond = $as . '.' . ERank::A_ID . '=' . $asDwnlBon . '.' . EBonDwnl::A_RANK_REF;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN prxgt_dwnl_customer as customer */
        $tbl = $this->resource->getTableName(self::E_DWNL);
        $as = $asDwnlCust;
        $cols = [
            self::A_CUST_MLM_ID => EDwnlCust::A_MLM_ID
        ];
        $cond = $as . '.' . EDwnlCust::A_CUSTOMER_REF . '=' . $asDwnlBon . '.' . EBonDwnl::A_CUST_REF;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN customer_entity as customer */
        $tbl = $this->resource->getTableName(Cfg::ENTITY_MAGE_CUSTOMER);
        $as = $asCust;
        $exp = $this->expFullNameCust();
        $cols = [
            self::A_CUST_NAME => $exp
        ];
        $cond = $as . '.' . Cfg::E_CUSTOMER_A_ENTITY_ID . '=' . $asDwnlBon . '.' . EBonDwnl::A_CUST_REF;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN prxgt_dwnl_customer as parent */
        $tbl = $this->resource->getTableName(self::E_DWNL);
        $as = $asDwnlParent;
        $cols = [
            self::A_PARENT_MLM_ID => EDwnlCust::A_MLM_ID
        ];
        $cond = $as . '.' . EDwnlCust::A_CUSTOMER_REF . '=' . $asDwnlBon . '.' . EBonDwnl::A_PARENT_REF;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN customer_entity as parent */
        $tbl = $this->resource->getTableName(Cfg::ENTITY_MAGE_CUSTOMER);
        $as = $asParent;
        $exp = $this->expFullNameParent();
        $cols = [
            self::A_PARENT_NAME => $exp
        ];
        $cond = $as . '.' . Cfg::E_CUSTOMER_A_ENTITY_ID . '=' . $asDwnlBon . '.' . EBonDwnl::A_PARENT_REF;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* WHERE */
        $byCust = "$asDwnlBon." . EBonDwnl::A_CALC_REF . "=:" . self::BND_CALC_ID;
        $result->where($byCust);

        /* return  result */
        return $result;
    }

    protected function getQueryTotal()
    {
        /* get query to select items */
        /** @var \Magento\Framework\DB\Select $result */
        $result = $this->getQueryItems();
        /* ... then replace "columns" part with own expression */
        $value = 'COUNT(' . self::AS_DWNL_BON . '.' . EBonDwnl::A_ID . ')';

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