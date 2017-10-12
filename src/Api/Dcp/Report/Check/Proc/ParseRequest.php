<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Check\Proc;

use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Context as Context;

/**
 * Process to parse & validate input data then put validated values back into context.
 *
 */
class ParseRequest
{
    public function exec(Context $ctx): Context
    {
        $request = $ctx->getWebRequest();

        $customerId = $request->getCustomerId();
        $period = $request->getPeriod();

        $ctx->setCustomerId($customerId);
        $ctx->setPeriod($period);

        return $ctx;
    }
}