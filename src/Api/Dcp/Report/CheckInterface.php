<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report;

use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Request as Request;
use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response as Response;

/**
 * Get data for DCP Check report.
 */
interface CheckInterface
{
    public function exec(Request $data): Response;
}