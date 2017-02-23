<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\Pv\Data\Entity\Sale as Pv;

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
    const AS_TBL_ORDER = 'sale';
    const AS_TBL_PV = 'pv';
    /**
     * Attributes aliases.
     */
    const A_CUST_ID = 'cust_id';
    const A_ORDER_ID = 'order_id';
    /**
     * Bound variables names
     */
    const BIND_DATE_FROM = 'date_from';
    const BIND_DATE_TO = 'date_to';

    public function getSelectQuery(\Praxigento\Core\Repo\Query\IBuilder $qbuild = null)
    {
        $asCust = self::AS_TBL_CUSTOMER;
        $asOrder = self::AS_TBL_ORDER;
        $asPv = self::AS_TBL_PV;
        $result = $this->conn->select();
        /* SELECT FROM customer_entity */
        $tblCust = $this->resource->getTableName(Cfg::ENTITY_MAGE_CUSTOMER);
        $cols = [self::A_CUST_ID => Cfg::E_CUSTOMER_A_ENTITY_ID];
        $result->from([$asCust => $tblCust], $cols);
        /* LEFT JOIN sales_order */
        $tblOrder = $this->resource->getTableName(Cfg::ENTITY_MAGE_SALES_ORDER);
        $on = $asOrder . '.' . Cfg::E_SALE_ORDER_A_CUSTOMER_ID . '=' . $asCust . '.' . Cfg::E_CUSTOMER_A_ENTITY_ID;
        $cols = [self::A_ORDER_ID => Cfg::E_SALE_ORDER_A_ENTITY_ID];
        $result->joinLeft([$asOrder => $tblOrder], $on, $cols);
        /* LEFT JOIN prxgt_pv_sale  */
        $tblPv = $this->resource->getTableName(Pv::ENTITY_NAME);
        $on = $asPv . '.' . Pv::ATTR_SALE_ID . '=' . $asOrder . '.' . Cfg::E_SALE_ORDER_A_ENTITY_ID;
        $cols = [self::AS_TBL_PV => Pv::ATTR_TOTAL];
        $result->joinLeft([$asPv => $tblPv], $on, $cols);
        /* WHERE */
        $where = $asCust . '.' . Cfg::E_CUSTOMER_A_CREATED_AT . '>=:' . self::BIND_DATE_FROM;
        $where .= ' AND ' . $asCust . '.' . Cfg::E_CUSTOMER_A_CREATED_AT . '<=:' . self::BIND_DATE_TO;
        $where .= ' AND ' . $asPv . '.' . Pv::ATTR_TOTAL . ' IS NOT NULL';
        $result->where($where);
        /* ORDER */
        $order = $asOrder . '.' . Cfg::E_SALE_ORDER_A_ENTITY_ID . ' ASC';
        $result->order($order);
        return $result;
    }

}