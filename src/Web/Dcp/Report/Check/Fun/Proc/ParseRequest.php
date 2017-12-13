<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc;

use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Context as AContext;

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
            $reqData = $request->getData();
            $reqDev = $request->getDev();

            /* step's activity */
            $period = (string)$reqData->getPeriod();
            $customerId = (int)$reqDev->getCustId();

            /* put step's result data back into the context */
            $ctx->setCustomerId($customerId);
            $ctx->setPeriod($period);
        }
        return $ctx;
    }
}