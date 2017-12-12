<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Web\Dcp\Report;

/**
 * Get data for DCP Downline report.
 */
interface DownlineInterface
{
    /**
     * @param \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Downline\Request $request
     * @return \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Downline\Response
     */
    public function exec(\Praxigento\BonusHybrid\Api\Web\Dcp\Report\Downline\Request $request);
}