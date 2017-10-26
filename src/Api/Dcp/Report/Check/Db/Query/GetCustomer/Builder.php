<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Check\Db\Query\GetCustomer;

use Praxigento\Downline\Config as Cfg;
use Praxigento\Downline\Repo\Entity\Data\Customer as EDwnlCust;
use Praxigento\Downline\Repo\Entity\Data\Snap as EDwnlSnap;
use Praxigento\Downline\Repo\Query\Snap\OnDate\Builder as QBBase;

/**
 * Query builder to be used in "Praxigento\BonusHybrid\Api\Dcp\Report\Check" context
 * to get customer data for the given date.
 */
class Builder
    extends \Praxigento\Core\Repo\Query\Builder
{
    /** Tables aliases for external usage ('camelCase' naming) */
    const AS_DWNL_CUST = 'dwnlCust';
    const AS_SNAP = QBBase::AS_DWNL_SNAP;
    const AS_MAGE_CUST = 'mageCust';

    /** Columns/expressions aliases for external usage ('camelCase' naming) */
    const A_CUST_ID = QBBase::A_CUST_ID;
    const A_DEPTH = QBBase::A_DEPTH;
    const A_MLM_ID = 'mlmId';
    const A_NAME_FIRST = 'nameFirst';
    const A_NAME_LAST = 'nameLast';
    const A_PARENT_ID = QBBase::A_PARENT_ID;
    const A_PATH = QBBase::A_PATH;

    /** Bound variables names ('camelCase' naming) */
    const BND_CUST_ID = 'custId';
    const BND_ON_DATE = QBBase::BND_ON_DATE;

    /** @var \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder */
    private $qbSnap;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder $qbSnap

    )
    {
        parent::__construct($resource);
        $this->qbSnap = $qbSnap;
    }

    public function build(\Magento\Framework\DB\Select $source = null)
    {
        /* build own query based on downline snap query */
        $result = $this->qbSnap->build();

        /* define tables aliases for internal usage (in this method) */
        $asDwnlCust = self::AS_DWNL_CUST;
        $asMageCust = self::AS_MAGE_CUST;
        $asSnap = self::AS_SNAP;

        /* LEFT JOIN prxgt_dwnl_customer to get MLM ID */
        $tbl = $this->resource->getTableName(EDwnlCust::ENTITY_NAME);
        $as = $asDwnlCust;
        $cols = [
            self::A_MLM_ID => EDwnlCust::ATTR_HUMAN_REF
        ];
        $cond = "$as." . EDwnlCust::ATTR_CUSTOMER_ID . "=$asSnap." . EDwnlSnap::ATTR_CUSTOMER_ID;
        $result->join([$as => $tbl], $cond, $cols);

        /* LEFT JOIN customer_entity to get first/last names */
        $tbl = $this->resource->getTableName(Cfg::ENTITY_MAGE_CUSTOMER);
        $as = $asMageCust;
        $cols = [
            self::A_NAME_FIRST => Cfg::E_CUSTOMER_A_FIRSTNAME,
            self::A_NAME_LAST => Cfg::E_CUSTOMER_A_LASTNAME
        ];
        $cond = "$as." . Cfg::E_CUSTOMER_A_ENTITY_ID . "=$asSnap." . EDwnlSnap::ATTR_CUSTOMER_ID;
        $result->join([$as => $tbl], $cond, $cols);

        /* where */
        $where = "$asDwnlCust." . EDwnlCust::ATTR_CUSTOMER_ID . '=:' . self::BND_CUST_ID;
        $result->where($where);

        return $result;
    }

}