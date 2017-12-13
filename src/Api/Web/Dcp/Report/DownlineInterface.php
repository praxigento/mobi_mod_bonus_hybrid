<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Web\Dcp\Report;

use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Downline\Request as ARequest;
use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Downline\Response as AResponse;

/**
 * Get data for DCP Downline report.
 */
interface DownlineInterface
{
    /**
     * @param \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Downline\Request $request
     * @return \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Downline\Response
     */
    public function exec(ARequest $request): AResponse;
}