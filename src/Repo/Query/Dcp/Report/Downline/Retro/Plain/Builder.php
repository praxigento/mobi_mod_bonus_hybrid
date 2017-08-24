<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Retro\Plain;

use Praxigento\BonusHybrid\Repo\Data\Agg\Dcp\Report\Downline\Entry as AReport;
use Praxigento\BonusHybrid\Repo\Data\Entity\Retro\Downline\Plain as DwnlPlain;
use Praxigento\Downline\Data\Entity\Customer as EDwnlCust;
use Praxigento\Downline\Repo\Query\Snap\OnDate\Builder as BldOnDate;
use Praxigento\Downline\Repo\Query\Snap\OnDate\ForDcp\Builder as BldForDcp;

/**
 * Build query to get DCP Downline Report data for retrospective plain tree.
 */
class Builder
    extends \Praxigento\Core\Repo\Query\Def\Builder
{
    /** Tables aliases */
    const AS_CUSTOMER = BldForDcp::AS_CUSTOMER;
    const AS_DOWNLINE_CUSTOMER = BldForDcp::AS_DOWNLINE_CUSTOMER;
    const AS_DWNL_SNAP = \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder::AS_DWNL_SNAP;
    const AS_DWNL_SNAP_4_MAX = \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder::AS_DWNL_SNAP_4_MAX;
    const AS_DWNL_SNAP_MAX = \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder::AS_DWNL_SNAP_MAX;
    const AS_RETRO_PLAIN = 'dwnlRetroPlain';

    /** Columns aliases */
    const A_COUNTRY_CODE = BldForDcp::A_COUNTRY_CODE;
    const A_CUST_REF = BldOnDate::A_CUST_ID;
    const A_DEPTH = BldOnDate::A_DEPTH;
    const A_EMAIL = BldForDcp::A_EMAIL;
    const A_MLM_ID = BldForDcp::A_MLM_ID;
    const A_NAME_FIRST = BldForDcp::A_NAME_FIRST;
    const A_NAME_LAST = BldForDcp::A_NAME_LAST;
    const A_NAME_MIDDLE = BldForDcp::A_NAME_MIDDLE;
    const A_OV = AReport::A_OV;
    const A_PARENT_REF = BldOnDate::A_PARENT_ID;
    const A_PATH = BldOnDate::A_PATH;
    const A_PV = AReport::A_PV;
    const A_RANK_CODE = AReport::A_RANK_CODE;
    const A_TV = AReport::A_TV;
    const A_UNQ_MONTHS = AReport::A_UNQ_MONTHS;

    /** Bound variables names ('camelCase' naming) */
    const BIND_CALC_ID = 'calcId';

    /** Bound variables names */
    const BIND_DATE = \Praxigento\Downline\Repo\Query\Snap\OnDate\Max\Builder::BIND_ON_DATE;

    /** @var \Praxigento\Downline\Repo\Query\Snap\OnDate\ForDcp\Builder */
    protected $qbldDwnlSnap;
    /** @var \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder */
    protected $qbldSnapOnDate;


    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder $qbldSnapOnDate,
        \Praxigento\Downline\Repo\Query\Snap\OnDate\ForDcp\Builder $qbldDwnlSnap
    )
    {
        parent::__construct($resource);
        $this->qbldSnapOnDate = $qbldSnapOnDate;
        $this->qbldDwnlSnap = $qbldDwnlSnap;
    }

    public function build(\Magento\Framework\DB\Select $source = null)
    {
        /* use 'Downline Snapshot' query as base */
        $result = $this->qbldDwnlSnap->getSelectQuery($this->qbldSnapOnDate);

        /* define tables aliases for internal usage (in this method) */
        $asPlain = self::AS_RETRO_PLAIN;
        $tbl = $this->resource->getTableName(DwnlPlain::ENTITY_NAME);
        $as = $asPlain;
        $cols = [
            self::A_PV => DwnlPlain::ATTR_PV,
            self::A_TV => DwnlPlain::ATTR_TV,
            self::A_OV => DwnlPlain::ATTR_OV,
            self::A_RANK_CODE => DwnlPlain::ATTR_RANK_CODE,
            self::A_UNQ_MONTHS => DwnlPlain::ATTR_UNQ_MONTHS
        ];
        $cond = $asPlain . '.' . DwnlPlain::ATTR_CALC_REF . '=:' . self::BIND_CALC_ID;
        $cond .= ' AND ' . $asPlain . '.' . DwnlPlain::ATTR_CUST_REF . '='
            . self::AS_DOWNLINE_CUSTOMER . '.' . EDwnlCust::ATTR_CUSTOMER_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        return $result;
    }


}