<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc\Clean as PCalcClean;
use Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc\Register as PCalcReg;


class Compress
    implements \Praxigento\BonusHybrid\Service\Calc\Forecast\ICompress
{
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc\Clean */
    private $procCalcClean;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc\Register */
    private $procCalcReg;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc\Clean $procCalcClean,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc\Register $procCalcReg
    )
    {
        $this->logger = $logger;
        $this->procCalcClean = $procCalcClean;
        $this->procCalcReg = $procCalcReg;
    }

    /**
     * Clean up existing forecast calculation data.
     */
    private function cleanCalc()
    {
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(PCalcClean::IN_CALC_TYPE_CODE, Cfg::CODE_TYPE_CALC_FORECAST_COMPRESS);
        $this->procCalcClean->exec($ctx);
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        $this->logger->info("'Forecast Compress' calculation is started.");

        /* clean up existing calculation data and register new one */
        $this->cleanCalc();
        $calcId = $this->registerCalc();

        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("'Forecast Compress' calculation is completed.");
    }

    private function registerCalc()
    {
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(PCalcReg::IN_CALC_TYPE_CODE, Cfg::CODE_TYPE_CALC_FORECAST_COMPRESS);
        /** @var \Praxigento\Core\Data $res */
        $res = $this->procCalcReg->exec($ctx);
        $result = $res->get(PCalcReg::OUT_CALC_ID);
        return $result;
    }

}