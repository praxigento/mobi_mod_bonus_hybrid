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
 * (this month's signed customers with first order more then 100 PV).
 */
class GetOrders
    extends \Praxigento\Core\App\Repo\Query\Builder
{
    /**
     * Tables aliases.
     */
    const AS_CUSTOMER = 'cust';
    const AS_DOWNLINE = 'dwnl';
    const AS_DWNL_PARENT = 'dwnp';
    const AS_ORDER = 'sale';
    const AS_PV = 'pv';

    /**
     * Attributes aliases.
     */
    const A_COUNTRY = EDwnlCust::A_COUNTRY_CODE;
    const A_CUST_ID = 'cust_id';
    const A_ORDER_ID = 'order_id';
    const A_PARENT_GRAND_ID = 'parent_grand_id';
    const A_PARENT_ID = 'parent_id';
    const A_PV = 'pv';

    /**
     * Bound variables names
     */
    const BND_DATE_FROM = 'date_from';
    const BND_DATE_TO = 'date_to';

    public function build(\Magento\Framework\DB\Select $source = null)
    {
        $asCust = self::AS_CUSTOMER;
        $asDwnl = self::AS_DOWNLINE;
        $asOrder = self::AS_ORDER;
        $asPv = self::AS_PV;
        $asParent = self::AS_DWNL_PARENT;
        $result = $this->conn->select();
        /* SELECT FROM customer_entity */
        $tbl = $this->resource->getTableName(Cfg::ENTITY_MAGE_CUSTOMER);
        $cols = [self::A_CUST_ID => Cfg::E_CUSTOMER_A_ENTITY_ID];
        $result->from([$asCust => $tbl], $cols);
        /* LEFT JOIN prxgt_dwnl_customer */
        $tbl = $this->resource->getTableName(EDwnlCust::ENTITY_NAME);
        $on = $asDwnl . '.' . EDwnlCust::A_CUSTOMER_ID . '=' . $asCust . '.' . Cfg::E_CUSTOMER_A_ENTITY_ID;
        $cols = [
            self::A_COUNTRY => EDwnlCust::A_COUNTRY_CODE,
            self::A_PARENT_ID => EDwnlCust::A_PARENT_ID
        ];
        $result->joinLeft([$asDwnl => $tbl], $on, $cols);
        /* LEFT JOIN sales_order */
        $tbl = $this->resource->getTableName(Cfg::ENTITY_MAGE_SALES_ORDER);
        $on = $asOrder . '.' . Cfg::E_SALE_ORDER_A_CUSTOMER_ID . '=' . $asCust . '.' . Cfg::E_CUSTOMER_A_ENTITY_ID;
        $cols = [self::A_ORDER_ID => Cfg::E_SALE_ORDER_A_ENTITY_ID];
        $result->joinLeft([$asOrder => $tbl], $on, $cols);
        /* LEFT JOIN prxgt_pv_sale  */
        $tbl = $this->resource->getTableName(EPvSale::ENTITY_NAME);
        $on = $asPv . '.' . EPvSale::A_SALE_REF . '=' . $asOrder . '.' . Cfg::E_SALE_ORDER_A_ENTITY_ID;
        $cols = [self::A_PV => EPvSale::A_TOTAL];
        $result->joinLeft([$asPv => $tbl], $on, $cols);
        /* LEFT JOIN prxgt_dwnl_customer (as parent) */
        $tbl = $this->resource->getTableName(EDwnlCust::ENTITY_NAME);
        $on = $asParent . '.' . EDwnlCust::A_CUSTOMER_ID . '=' . $asDwnl . '.' . EDwnlCust::A_PARENT_ID;
        $cols = [
            self::A_PARENT_GRAND_ID => EDwnlCust::A_PARENT_ID
        ];
        $result->joinLeft([$asParent => $tbl], $on, $cols);
        /* WHERE */
        $where = $asCust . '.' . Cfg::E_CUSTOMER_A_CREATED_AT . '>=:' . self::BND_DATE_FROM;
        $where .= ' AND ' . $asCust . '.' . Cfg::E_CUSTOMER_A_CREATED_AT . '<:' . self::BND_DATE_TO;
        $where .= ' AND ' . $asPv . '.' . EPvSale::A_TOTAL . ' IS NOT NULL';
        $result->where($where);
        /* ORDER */
        $order = $asOrder . '.' . Cfg::E_SALE_ORDER_A_ENTITY_ID . ' ASC';
        $result->order($order);
        return $result;
    }
}