<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Web\Dcp\Report;

use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Data\Request as Request;
use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Data\Response as Response;

/**
 * Get data for DCP Check report.
 */
interface CheckInterface
{
    /**
     * @param \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Data\Request $request
     * @return \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Data\Response
     */
    public function exec(Request $request): Response;
}