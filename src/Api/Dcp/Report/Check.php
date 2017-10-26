<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report;

use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Context as Context;
use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Request as Request;
use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response as Response;

class Check
    implements \Praxigento\BonusHybrid\Api\Dcp\Report\CheckInterface
{
    /** @var \Praxigento\Core\Api\IAuthenticator */
    private $authenticator;
    /** @var \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Proc\BuildQueries */
    private $procBuildQueries;
    /** @var \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Proc\ParseRequest */
    private $procParseRequest;
    /** @var \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Proc\PerformQueries */
    private $procPerformQueries;
    /** @var \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Proc\PopulateQueries */
    private $procPopulateQueries;

    public function __construct(
        \Praxigento\Core\Api\IAuthenticator $authenticator,
        \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Proc\ParseRequest $procParseRequest,
        \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Proc\BuildQueries $procBuildQueries,
        \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Proc\PerformQueries $procPerformQueries,
        \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Proc\PopulateQueries $procPopulateQueries
    )
    {
        $this->authenticator = $authenticator;
        $this->procParseRequest = $procParseRequest;
        $this->procBuildQueries = $procBuildQueries;
        $this->procPopulateQueries = $procPopulateQueries;
        $this->procPerformQueries = $procPerformQueries;
    }

    public function exec(Request $data): Response
    {
        /* prepare processing context */
        $ctx = new Context();
        $ctx->setWebRequest($data);
        $ctx->setWebResponse(new Response());

        /* perform processing: step by step */
        $ctx = $this->procParseRequest->exec($ctx);
        $ctx = $this->procBuildQueries->exec($ctx);
        $ctx = $this->procPopulateQueries->exec($ctx);
        $ctx = $this->procPerformQueries->exec($ctx);

        /* get result from context */
        $result = $ctx->getWebResponse();
        return $result;
    }

    private function prepareQueryParameters()
    {
    }
}