<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Api\Service\Calc;


use Praxigento\BonusHybrid\Api\Service\Calc\Forecast\Request as ARequest;
use Praxigento\BonusHybrid\Api\Service\Calc\Forecast\Response as AResponse;

/**
 * Wrapper service for controller/CLI.
 * This service uses transaction and should not be called by other services (CLI, cron, controller only).
 */
interface Forecast
{
    /**
     * @param ARequest $request
     * @return AResponse
     * @throws \Exception
     */
    public function exec($request);
}