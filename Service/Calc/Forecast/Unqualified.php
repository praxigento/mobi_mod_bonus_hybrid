<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\Forecast\Unqualified\Request as ARequest;
use Praxigento\BonusHybrid\Service\Calc\Forecast\Unqualified\Response as AResponse;

/**
 * Unqualified customers forecast calc for the given period (not closed).
 */
class Unqualified
{
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;


    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * @param ARequest $request
     * @return AResponse
     */
    public function exec($request)
    {
        assert($request instanceof ARequest);
        /** define local working data */
        $period = $request->getPeriod();
        $this->logger->info("Forecast Unqualified calculation is started for period $period.");


        Cfg::CODE_TYPE_CALC_FORECAST_PLAIN;
        $this->logger->info("Forecast Unqualified calculation is completed.");

        $result = new AResponse();
        $result->markSucceed();
        return $result;
    }

}