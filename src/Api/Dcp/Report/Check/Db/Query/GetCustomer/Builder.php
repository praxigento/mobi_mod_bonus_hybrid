<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Check\Db\Query\GetCustomer;

class Builder
    extends \Praxigento\Core\Repo\Query\Builder
{
    /** @var \Praxigento\Downline\Repo\Query\Snap\OnDate\ForDcp\Builder */
    private $qbSnap;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Downline\Repo\Query\Snap\OnDate\ForDcp\Builder $qbSnap

    )
    {
        parent::__construct($resource);
        $this->qbSnap = $qbSnap;
    }

    public function build(\Magento\Framework\DB\Select $source = null)
    {
        $result = $this->qbSnap->build();
        return $result;
    }

}