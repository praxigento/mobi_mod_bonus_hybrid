<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Check\Proc;

use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Context as Ctx;

/**
 * Process to build queries to get data from DB.
 *
 */
class BuildQueries
{
    /** @var \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Db\Query\GetCustomer\Builder */
    private $qbGetCustomer;

    public function __construct(
        \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Db\Query\GetCustomer\Builder $qbGetCustomer
    )
    {
        $this->qbGetCustomer = $qbGetCustomer;
    }

    public function exec(Ctx $ctx): Ctx
    {
        $ctx->queryCustomer = $this->qbGetCustomer->build();
        return $ctx;
    }

}