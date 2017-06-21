<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Retro\Plain;

use Praxigento\Downline\Repo\Query\Snap\OnDate\ForDcp\Builder as DwnlSnap;

/**
 * Build query to get DCP Downline Report data for retrospective plain tree.
 */
class Builder
    extends \Praxigento\Core\Repo\Query\Def\Builder
{
    /** Tables aliases. */
    const AS_CUSTOMER = DwnlSnap::AS_CUSTOMER;
    const AS_DOWNLINE_CUSTOMER = DwnlSnap::AS_DOWNLINE_CUSTOMER;

    /** Columns aliases. */
    const A_COUNTRY_CODE = DwnlSnap::A_COUNTRY_CODE;
    const A_EMAIL = DwnlSnap::A_EMAIL;
    const A_MLM_ID = DwnlSnap::A_MLM_ID;
    const A_NAME_FIRST = DwnlSnap::A_NAME_FIRST;
    const A_NAME_LAST = DwnlSnap::A_NAME_LAST;
    const A_NAME_MIDDLE = DwnlSnap::A_NAME_MIDDLE;

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
    ) {
        parent::__construct($resource);
        $this->qbldSnapOnDate = $qbldSnapOnDate;
        $this->qbldDwnlSnap = $qbldDwnlSnap;
    }

    public function build(\Magento\Framework\DB\Select $source = null)
    {
        $result = $this->qbldDwnlSnap->getSelectQuery($this->qbldSnapOnDate);

        return $result;
    }


}