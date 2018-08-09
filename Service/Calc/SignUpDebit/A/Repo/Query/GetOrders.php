<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\SignUpDebit\A\Repo\Query;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\Downline\Repo\Data\Customer as EDwnlCust;
use Praxigento\Pv\Repo\Data\Sale as EPvSale;

/**
 * Query to get data to process 'Sign Up Volume Debit' bonus
 * (this month's signed customers from distributors group with first order more then 100 PV).
 * This query return all sales for selected customers not only first ones, postprocessing is required for result set.
 * All sales are ordered by ID (natural creation order).
 */
class GetOrders
    extends \Praxigento\Core\App\Repo\Query\Builder
{
    /** Tables aliases for external usage ('camelCase' naming) */
    const AS_CUST = 'cust';
    const AS_DWNL = 'dwnl';
    const AS_DWNL_PARENT = 'dwnp';
    const AS_PV = 'pv';
    const AS_SALE = 'sale';

    /** Columns/expressions aliases for external usage ('camelCase' naming) */
    const A_COUNTRY = EDwnlCust::A_COUNTRY_CODE;
    const A_CUST_ID = 'custId';
    const A_PARENT_GRAND_ID = 'parentGrandId';
    const A_PARENT_ID = 'parentId';
    const A_PV = 'pv';
    const A_SALE_ID = 'saleId';

    /** Bound variables names ('camelCase' naming) */
    const BND_CUST_GROUP_ID = 'custGroupId';
    const BND_DATE_FROM = 'dateFrom';
    const BND_DATE_TO = 'dateTo';

    /** Entities are used in the query */
    const E_CUST = Cfg::ENTITY_MAGE_CUSTOMER;
    const E_DWNL = EDwnlCust::ENTITY_NAME;
    const E_DWNL_PARENT = EDwnlCust::ENTITY_NAME;
    const E_PV = EPvSale::ENTITY_NAME;
    const E_SALE = Cfg::ENTITY_MAGE_SALES_ORDER;

    public function build(\Magento\Framework\DB\Select $source = null)
    {
        /* this is root query builder (started from SELECT) */
        $result = $this->conn->select();

        /* define tables aliases for internal usage (in this method) */
        $asCust = self::AS_CUST;
        $asDwnl = self::AS_DWNL;
        $asSale = self::AS_SALE;
        $asPv = self::AS_PV;
        $asParent = self::AS_DWNL_PARENT;

        /* SELECT FROM customer_entity */
        $tbl = $this->resource->getTableName(Cfg::ENTITY_MAGE_CUSTOMER);
        $as = $asCust;
        $cols = [
            self::A_CUST_ID => Cfg::E_CUSTOMER_A_ENTITY_ID
        ];
        $result->from([$as => $tbl], $cols);

        /* LEFT JOIN prxgt_dwnl_customer */
        $tbl = $this->resource->getTableName(EDwnlCust::ENTITY_NAME);
        $as = $asDwnl;
        $cols = [
            self::A_COUNTRY => EDwnlCust::A_COUNTRY_CODE,
            self::A_PARENT_ID => EDwnlCust::A_PARENT_ID
        ];
        $cond = $as . '.' . EDwnlCust::A_CUSTOMER_ID . '=' . $asCust . '.' . Cfg::E_CUSTOMER_A_ENTITY_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN sales_order */
        $tbl = $this->resource->getTableName(Cfg::ENTITY_MAGE_SALES_ORDER);
        $as = $asSale;
        $cols = [
            self::A_SALE_ID => Cfg::E_SALE_ORDER_A_ENTITY_ID
        ];
        $cond = $as . '.' . Cfg::E_SALE_ORDER_A_CUSTOMER_ID . '=' . $asCust . '.' . Cfg::E_CUSTOMER_A_ENTITY_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN prxgt_pv_sale  */
        $tbl = $this->resource->getTableName(EPvSale::ENTITY_NAME);
        $as = $asPv;
        $cols = [
            self::A_PV => EPvSale::A_TOTAL
        ];
        $cond = $as . '.' . EPvSale::A_SALE_REF . '=' . $asSale . '.' . Cfg::E_SALE_ORDER_A_ENTITY_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN prxgt_dwnl_customer (as parent) */
        $tbl = $this->resource->getTableName(EDwnlCust::ENTITY_NAME);
        $as = $asParent;
        $cols = [
            self::A_PARENT_GRAND_ID => EDwnlCust::A_PARENT_ID
        ];
        $cond = $as . '.' . EDwnlCust::A_CUSTOMER_ID . '=' . $asDwnl . '.' . EDwnlCust::A_PARENT_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* WHERE */
        $byCreatedAfter = $asCust . '.' . Cfg::E_CUSTOMER_A_CREATED_AT . '>=:' . self::BND_DATE_FROM;
        $byCreatedBefore = $asCust . '.' . Cfg::E_CUSTOMER_A_CREATED_AT . '<:' . self::BND_DATE_TO;
        $byCustGroup = $asCust . '.' . Cfg::E_CUSTOMER_A_GROUP_ID . '=:' . self::BND_CUST_GROUP_ID;
        $byPv = $asPv . '.' . EPvSale::A_TOTAL . ' >= 100';
        $where = "($byCreatedAfter) AND ($byCreatedBefore) AND ($byCustGroup) AND ($byPv)";
        $result->where($where);

        /* ORDER by sale id */
        $order = $asSale . '.' . Cfg::E_SALE_ORDER_A_ENTITY_ID . ' ASC';
        $result->order($order);

        return $result;
    }
}