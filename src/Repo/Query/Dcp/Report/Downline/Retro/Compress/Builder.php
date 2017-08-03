<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Retro\Compress;

use Praxigento\BonusHybrid\Repo\Data\Agg\Dcp\Report\Downline\Entry as AReport;
use Praxigento\BonusHybrid\Repo\Data\Entity\Retro\Downline\Compressed\Phase1 as DwnlCompress;
use Praxigento\Downline\Data\Entity\Customer as EDwnlCust;
use Praxigento\Downline\Repo\Query\Snap\OnDate\ForDcp\Builder as DwnlSnap;

/**
 * Build query to get DCP Downline Report data for retrospective compressed tree.
 */
class Builder
    extends \Praxigento\Core\Repo\Query\Def\Builder
{
    /** Tables aliases */
    const AS_CUSTOMER = DwnlSnap::AS_CUSTOMER;
    const AS_DOWNLINE_CUSTOMER = DwnlSnap::AS_DOWNLINE_CUSTOMER;
    const AS_RETRO_COMPRESS = 'dwnlRetroCompress';

    /** Columns aliases */
    const A_COUNTRY_CODE = DwnlSnap::A_COUNTRY_CODE;
    const A_EMAIL = DwnlSnap::A_EMAIL;
    const A_MLM_ID = DwnlSnap::A_MLM_ID;
    const A_NAME_FIRST = DwnlSnap::A_NAME_FIRST;
    const A_NAME_LAST = DwnlSnap::A_NAME_LAST;
    const A_NAME_MIDDLE = DwnlSnap::A_NAME_MIDDLE;
    const A_OV = AReport::A_OV;
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
        $asCompress = self::AS_RETRO_COMPRESS;
        $tbl = $this->resource->getTableName(DwnlCompress::ENTITY_NAME);
        $as = $asCompress;
        $cols = [
            self::A_PV => DwnlCompress::ATTR_PV,
            self::A_TV => DwnlCompress::ATTR_TV,
            self::A_OV => DwnlCompress::ATTR_OV,
//            self::A_RANK_CODE => DwnlCompress::ATTR_RANK_CODE,
//            self::A_UNQ_MONTHS => DwnlCompress::ATTR_UNQ_MONTHS
        ];
        $cond = $asCompress . '.' . DwnlCompress::ATTR_CALC_ID. '=:' . self::BIND_CALC_ID;
        $cond .= ' AND ' . $asCompress . '.' . DwnlCompress::ATTR_CUSTOMER_ID. '='
            . self::AS_DOWNLINE_CUSTOMER . '.' . EDwnlCust::ATTR_CUSTOMER_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        return $result;
    }


}