<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc;

use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Context as AContext;

/**
 * Process step to authorize process further completion.
 */
class Authorize
{

    /** @var \Praxigento\Core\App\Api\Web\IAuthenticator */
    private $authenticator;

    public function __construct(
        \Praxigento\Core\App\Api\Web\Authenticator\Front $authenticator
    ) {
        $this->authenticator = $authenticator;
    }

    public function exec(AContext $ctx): AContext {
        /* if current instance is active */
        if ($ctx->state == AContext::DEF_STATE_ACTIVE) {

            /* get step's local data from the context */
            $customerId = $ctx->getCustomerId();

            /* step's activity */
            /* TODO: add authorization */
            $request = new \Praxigento\Core\App\Api\Web\Request();
            $dev = new \Praxigento\Core\App\Api\Web\Request\Dev();
            $dev->setCustId($customerId);
            $request->setDev($dev);
            $customerId = $this->authenticator->getCurrentUserId($request);

            /* put step's result data back into the context */
            $ctx->setCustomerId($customerId);
        }
        return $ctx;
    }
}