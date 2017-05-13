<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report;

/**
 * Get data for DCP Downline report.
 */
interface DownlineInterface
{
    /**
     * @param \Praxigento\BonusHybrid\Api\Dcp\Report\Downline\Request $data
     * @return \Praxigento\BonusHybrid\Api\Dcp\Report\Downline\Response
     */
    public function exec(\Praxigento\BonusHybrid\Api\Dcp\Report\Downline\Request $data);
}