<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Web\Dcp\Report;

use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Context as AContext;
use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Request as ARequest;
use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Response as AResponse;

class Check
    implements \Praxigento\BonusHybrid\Api\Web\Dcp\Report\CheckInterface
{

    /** @var \Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\Authorize */
    private $procAuthorize;
    /** @var \Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\ComposeResponse */
    private $procComposeResp;
    /** @var \Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData */
    private $procMineData;
    /** @var \Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\ParseRequest */
    private $procParseRequest;

    public function __construct(
        \Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\Authorize $procAuthorize,
        \Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\ComposeResponse $procComposeResp,
        \Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData $procMineData,
        \Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\ParseRequest $procParseRequest
    )
    {
        $this->procAuthorize = $procAuthorize;
        $this->procComposeResp = $procComposeResp;
        $this->procMineData = $procMineData;
        $this->procParseRequest = $procParseRequest;
    }

    public function exec(ARequest $request): AResponse
    {
        /* prepare processing context */
        $ctx = new AContext();
        $ctx->setWebRequest($request);
        $ctx->setWebResponse(new AResponse());
        $ctx->state = AContext::DEF_STATE_ACTIVE;

        /* perform processing: step by step */
        $this->procParseRequest->exec($ctx);
        $this->procAuthorize->exec($ctx);
        $this->procMineData->exec($ctx);
        $this->procComposeResp->exec($ctx);

        /* get result from context */
        $result = $ctx->getWebResponse();
        return $result;
    }

}