<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A\SaveDownline\A\Repo\Query;

use Praxigento\Downline\Repo\Data\Customer as EDwnlCust;
use Praxigento\Downline\Repo\Data\Snap as ESnap;
use Praxigento\Downline\Repo\Query\Snap\OnDate\Builder as QBase;

/**
 * Load downline snap with country data to resolve Scheme (DEF|EU) later.
 */
class GetSnap
    extends \Praxigento\Core\App\Repo\Query\Builder
{
    /** Tables aliases for external usage ('camelCase' naming) */
    const AS_DWNL_CUST = 'prxgtDwnlCust';
    const AS_DWNL_SNAP = QBase::AS_DWNL_SNAP;
    const AS_DWNL_SNAP_4_MAX = QBase::AS_DWNL_SNAP_4_MAX;
    const AS_DWNL_SNAP_MAX = QBase::AS_DWNL_SNAP_MAX;

    /** Columns/expressions aliases for external usage ('camelCase' naming) */
    const A_COUNTRY = EDwnlCust::A_COUNTRY_CODE;
    const A_CUST_ID = QBase::A_CUST_ID;
    const A_DEPTH = QBase::A_DEPTH;
    const A_MLM_ID = EDwnlCust::A_MLM_ID;
    const A_PARENT_ID = QBase::A_PARENT_ID;
    const A_PATH = QBase::A_PATH;

    /** Bound variables names */
    const BND_ON_DATE = QBase::BND_ON_DATE;

    /** Entities are used in the query */
    const E_DWNL_CUST = EDwnlCust::ENTITY_NAME;

    /** @var \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder */
    private $qBase;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder $qBase
    ) {
        parent::__construct($resource);
        $this->qBase = $qBase;
    }

    public function build(\Magento\Framework\DB\Select $source = null)
    {
        /* this builder extends existing query */
        $result = $this->qBase->build();

        /* define tables aliases for internal usage (in this method) */
        $as = self::AS_DWNL_CUST;

        $tbl = $this->resource->getTableName(EDwnlCust::ENTITY_NAME);
        $on = $as . '.' . EDwnlCust::A_CUSTOMER_ID . '='
            . QBase::AS_DWNL_SNAP . '.' . ESnap::A_CUSTOMER_ID;
        $cols = [
            self::A_COUNTRY => EDwnlCust::A_COUNTRY_CODE,
            self::A_MLM_ID => EDwnlCust::A_MLM_ID
        ];
        $result->joinLeft([$as => $tbl], $on, $cols);

        /* ORDER by depth */
        $result->order(QBase::AS_DWNL_SNAP . '.' . ESnap::A_DEPTH);

        return $result;
    }
}