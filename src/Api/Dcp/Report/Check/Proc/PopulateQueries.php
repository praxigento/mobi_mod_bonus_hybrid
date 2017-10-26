<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Check\Proc;

use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Context as Ctx;

/**
 * Process to bind parameters to DB queries.
 *
 */
class PopulateQueries
{
    public function __construct()
    {

    }

    public function exec(Ctx $ctx): Ctx
    {
        $queryCustomer = $ctx->queryCustomer;

        return $ctx;
    }

}