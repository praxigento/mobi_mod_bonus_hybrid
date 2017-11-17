<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Dcp\Report\Accounting\Repo\Query\GetCustomer;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\Downline\Repo\Entity\Data\Customer as EDwnlCust;

class Builder
    extends \Praxigento\Core\Repo\Query\Builder
{
    /** Tables aliases for external usage ('camelCase' naming) */
    const AS_CUST = 'cust';
    const AS_DWNL = 'dwnl';

    /** Columns/expressions aliases for external usage ('camelCase' naming) */
    const A_ID = 'id';
    const A_MLM_ID = 'mlmId';
    const A_NAME_FIRST = 'nameFirst';
    const A_NAME_LAST = 'nameLast';

    /** Bound variables names ('camelCase' naming) */
    const BND_CUST_ID = 'custId';

    public function build(\Magento\Framework\DB\Select $source = null)
    {
        $result = $this->conn->select();
        /* define tables aliases for internal usage (in this method) */
        $asCust = self::AS_CUST;
        $asDwnl = self::AS_DWNL;

        /* FROM customer_entity */
        $tbl = $this->resource->getTableName(Cfg::ENTITY_MAGE_CUSTOMER);
        $as = $asCust;
        $cols = [
            self::A_ID => Cfg::E_CUSTOMER_A_ENTITY_ID,
            self::A_NAME_FIRST => Cfg::E_CUSTOMER_A_FIRSTNAME,
            self::A_NAME_LAST => Cfg::E_CUSTOMER_A_LASTNAME
        ];
        $result->from([$as => $tbl], $cols);    // standard names for the variables


        /* JOIN prxgt_dwnl_customer */
        $tbl = $this->resource->getTableName(EDwnlCust::ENTITY_NAME);
        $as = $asDwnl;
        $cols = [
            self::A_MLM_ID => EDwnlCust::ATTR_MLM_ID
        ];
        $cond = $as . '.' . EDwnlCust::ATTR_CUSTOMER_ID . '=' . $asCust . '.' . Cfg::E_CUSTOMER_A_ENTITY_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* add filter by customer */
        $where = $asCust . '.' . Cfg::E_CUSTOMER_A_ENTITY_ID . '=:' . self::BND_CUST_ID;
        $result->where($where);

        return $result;
    }
}