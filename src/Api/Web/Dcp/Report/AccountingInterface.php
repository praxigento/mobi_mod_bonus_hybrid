<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Web\Dcp\Report;

/**
 * Get data for DCP Accounting report.
 */
interface AccountingInterface
{
    /**
     * @param \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting\Request $request
     * @return \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting\Response
     */
    public function exec(\Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting\Request $request);
}