<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\Downline\Repo\Entity\Data\Customer as Dwnl;
use Praxigento\Pv\Repo\Entity\Data\Sale as Pv;

/**
 * Build query to get data to process 'Sign Up Volume Debit' bonus (signed customers with first order more then 100 PV).
 */
class Builder
    extends \Praxigento\Core\Repo\Query\Def\Builder
{
    /**
     * Tables aliases.
     */
    const AS_TBL_CUSTOMER = 'cust';
    const AS_TBL_DOWNLINE = 'dwnl';
    const AS_TBL_DWNL_PARENT = 'dwnp';
    const AS_TBL_ORDER = 'sale';
    const AS_TBL_PV = 'pv';

    /**
     * Attributes aliases.
     */
    const A_COUNTRY = Dwnl::ATTR_COUNTRY_CODE;
    const A_CUST_ID = 'cust_id';
    const A_ORDER_ID = 'order_id';
    const A_PARENT_GRAND_ID = 'parent_grand_id';
    const A_PARENT_ID = 'parent_id';
    const A_PV = 'pv';
    /**
     * Bound variables names
     */
    const BIND_DATE_FROM = 'date_from';
    const BIND_DATE_TO = 'date_to';

    public function getSelectQuery(\Praxigento\Core\Repo\Query\IBuilder $qbuild = null)
    {
        $asCust = self::AS_TBL_CUSTOMER;
        $asDwnl = self::AS_TBL_DOWNLINE;
        $asOrder = self::AS_TBL_ORDER;
        $asPv = self::AS_TBL_PV;
        $asParent = self::AS_TBL_DWNL_PARENT;
        $result = $this->conn->select();
        /* SELECT FROM customer_entity */
        $tbl = $this->resource->getTableName(Cfg::ENTITY_MAGE_CUSTOMER);
        $cols = [self::A_CUST_ID => Cfg::E_CUSTOMER_A_ENTITY_ID];
        $result->from([$asCust => $tbl], $cols);
        /* LEFT JOIN prxgt_dwnl_customer */
        $tbl = $this->resource->getTableName(Dwnl::ENTITY_NAME);
        $on = $asDwnl . '.' . Dwnl::ATTR_CUSTOMER_ID . '=' . $asCust . '.' . Cfg::E_CUSTOMER_A_ENTITY_ID;
        $cols = [
            self::A_COUNTRY => Dwnl::ATTR_COUNTRY_CODE,
            self::A_PARENT_ID => Dwnl::ATTR_PARENT_ID
        ];
        $result->joinLeft([$asDwnl => $tbl], $on, $cols);
        /* LEFT JOIN sales_order */
        $tbl = $this->resource->getTableName(Cfg::ENTITY_MAGE_SALES_ORDER);
        $on = $asOrder . '.' . Cfg::E_SALE_ORDER_A_CUSTOMER_ID . '=' . $asCust . '.' . Cfg::E_CUSTOMER_A_ENTITY_ID;
        $cols = [self::A_ORDER_ID => Cfg::E_SALE_ORDER_A_ENTITY_ID];
        $result->joinLeft([$asOrder => $tbl], $on, $cols);
        /* LEFT JOIN prxgt_pv_sale  */
        $tbl = $this->resource->getTableName(Pv::ENTITY_NAME);
        $on = $asPv . '.' . Pv::ATTR_SALE_ID . '=' . $asOrder . '.' . Cfg::E_SALE_ORDER_A_ENTITY_ID;
        $cols = [self::A_PV => Pv::ATTR_TOTAL];
        $result->joinLeft([$asPv => $tbl], $on, $cols);
        /* LEFT JOIN prxgt_dwnl_customer (as parent) */
        $tbl = $this->resource->getTableName(Dwnl::ENTITY_NAME);
        $on = $asParent . '.' . Dwnl::ATTR_CUSTOMER_ID . '=' . $asDwnl . '.' . Dwnl::ATTR_PARENT_ID;
        $cols = [
            self::A_PARENT_GRAND_ID => Dwnl::ATTR_PARENT_ID
        ];
        $result->joinLeft([$asParent => $tbl], $on, $cols);
        /* WHERE */
        $where = $asCust . '.' . Cfg::E_CUSTOMER_A_CREATED_AT . '>=:' . self::BIND_DATE_FROM;
        $where .= ' AND ' . $asCust . '.' . Cfg::E_CUSTOMER_A_CREATED_AT . '<:' . self::BIND_DATE_TO;
        $where .= ' AND ' . $asPv . '.' . Pv::ATTR_TOTAL . ' IS NOT NULL';
        $result->where($where);
        /* ORDER */
        $order = $asOrder . '.' . Cfg::E_SALE_ORDER_A_ENTITY_ID . ' ASC';
        $result->order($order);
        return $result;
    }

}