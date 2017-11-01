<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc;

use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Context as AContext;

/**
 * Process step to parse & validate input data then put validated values back into context.
 */
class ParseRequest
{
    public function exec(AContext $ctx): AContext
    {
        /* if current instance is active */
        if ($ctx->state == AContext::DEF_STATE_ACTIVE) {

            /* get step's local data from the context */
            $request = $ctx->getWebRequest();

            /* step's activity */
            $customerId = (int)$request->getCustomerId();
            $period = (string)$request->getPeriod();

            /* put step's result data back into the context */
            $ctx->setCustomerId($customerId);
            $ctx->setPeriod($period);
        }
        return $ctx;
    }
}