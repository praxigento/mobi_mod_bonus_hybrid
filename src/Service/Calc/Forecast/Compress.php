<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast;


use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase1 as PCpmrsPhase1;
use Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc\Clean as PCalcClean;
use Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc\Register as PCalcReg;
use Praxigento\BonusHybrid\Service\Calc\Forecast\Compress\GetPlainData as PGetPlainData;

class Compress
    implements \Praxigento\BonusHybrid\Service\Calc\Forecast\ICompress
{
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc\Clean */
    private $procCalcClean;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc\Register */
    private $procCalcReg;
    /** @var \Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase1 */
    private $procCmprsPhase1;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\Compress\GetPlainData */
    private $procGetPlainData;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase1 $procCmprsPhase1,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc\Clean $procCalcClean,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc\Register $procCalcReg,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\Compress\GetPlainData $procGetPlainData
    )
    {
        $this->logger = $logger;
        $this->procCmprsPhase1 = $procCmprsPhase1;
        $this->procCalcClean = $procCalcClean;
        $this->procCalcReg = $procCalcReg;
        $this->procGetPlainData = $procGetPlainData;
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

    private function compressPhase1($calcId)
    {
        /* get the last forecast plain calculation and extract collected PV */
        $inPv = new \Praxigento\Core\Data();
        $outPv = $this->procGetPlainData->exec($inPv);
        $pv = $outPv->get(PGetPlainData::OUT_PV);
        $downline = $outPv->get(PGetPlainData::OUT_DWNL);

        $ctx = new \Praxigento\Core\Data();
        $ctx->set(PCpmrsPhase1::IN_CALC_ID, $calcId);
        $ctx->set(PCpmrsPhase1::IN_PV, $pv);
        $ctx->set(PCpmrsPhase1::IN_DWNL_SNAP, $downline);
        $this->procCmprsPhase1->exec($ctx);
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        $this->logger->info("'Forecast Compress' calculation is started.");

        /* clean up existing calculation data and register new one */
        $this->cleanCalc();
        $calcId = $this->registerCalc();

        /* perform Phase1 compression */
        $this->compressPhase1($calcId);

        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("'Forecast Compress' calculation is completed.");
    }

    /**
     * Register new compression calculation.
     *
     * @return int calculation ID.
     */
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