<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Web\Dcp\Report;

use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Request as ARequest;
use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Response as AResponse;

/**
 * Get data for DCP Check report.
 */
interface CheckInterface
{
    /**
     * @param \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Request $request
     * @return \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Response
     *
     * Magento 2 WebAPI requires full names in documentation (aliases are not allowed).
     */
    public function exec(ARequest $request): AResponse;
}