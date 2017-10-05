<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report;

/**
 * Get data for DCP Accounting report.
 */
interface AccountingInterface
{
    /**
     * @param \Praxigento\BonusHybrid\Api\Dcp\Report\Accounting\Request $data
     * @return \Praxigento\BonusHybrid\Api\Dcp\Report\Accounting\Response
     */
    public function exec(\Praxigento\BonusHybrid\Api\Dcp\Report\Accounting\Request $data);
}