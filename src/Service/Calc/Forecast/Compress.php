<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast;


use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EBonDwnl;
use Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase1 as PCpmrsPhase1;
use Praxigento\BonusHybrid\Service\Calc\A\Proc\Ov as POv;
use Praxigento\BonusHybrid\Service\Calc\A\Proc\Tv as PTv;
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
    /** @var \Praxigento\BonusHybrid\Service\Calc\A\Proc\Ov */
    private $procOv;
    /** @var \Praxigento\BonusHybrid\Service\Calc\A\Proc\Tv */
    private $procTv;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase1 $procCmprsPhase1,
        \Praxigento\BonusHybrid\Service\Calc\A\Proc\Tv $procTv,
        \Praxigento\BonusHybrid\Service\Calc\A\Proc\Ov $procOv,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc\Clean $procCalcClean,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc\Register $procCalcReg,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\Compress\GetPlainData $procGetPlainData
    )
    {
        $this->logger = $logger;
        $this->procCmprsPhase1 = $procCmprsPhase1;
        $this->procTv = $procTv;
        $this->procOv = $procOv;
        $this->procCalcClean = $procCalcClean;
        $this->procCalcReg = $procCalcReg;
        $this->procGetPlainData = $procGetPlainData;
    }

    private function calcOv($dwnl)
    {
        $in = new \Praxigento\Core\Data();
        $in->set(POv::IN_DWNL, $dwnl);
        $in->set(POv::IN_USE_SIGN_UP, false);
        $out = $this->procOv->exec($in);
        $result = $out->get(POv::OUT_DWNL);
        return $result;
    }

    private function calcTv($dwnl)
    {
        $in = new \Praxigento\Core\Data();
        $in->set(PTv::IN_DWNL, $dwnl);
        $out = $this->procTv->exec($in);
        $result = $out->get(PTv::OUT_DWNL);
        return $result;
    }

    /**
     * Clean up existing forecast calculation data.
     */
    private function cleanCalc()
    {
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(PCalcClean::IN_CALC_TYPE_CODE, Cfg::CODE_TYPE_CALC_FORECAST_PHASE2_DEF);
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
        $ctx->set(PCpmrsPhase1::IN_DWNL_PLAIN, $downline);
        $ctx->set(PCpmrsPhase1::IN_KEY_CUST_ID, EBonDwnl::ATTR_CUST_REF);
        $ctx->set(PCpmrsPhase1::IN_KEY_PARENT_ID, EBonDwnl::ATTR_PARENT_REF);
        $ctx->set(PCpmrsPhase1::IN_KEY_DEPTH, EBonDwnl::ATTR_DEPTH);
        $ctx->set(PCpmrsPhase1::IN_KEY_PATH, EBonDwnl::ATTR_PATH);
        $ctx->set(PCpmrsPhase1::IN_KEY_PV, EBonDwnl::ATTR_PV);

        $outPhase1 = $this->procCmprsPhase1->exec($ctx);
        $result = $outPhase1->get(PCpmrsPhase1::OUT_COMPRESSED);
        return $result;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        $this->logger->info("'Forecast Compress' calculation is started.");

        /* clean up existing calculation data and register new one */
        $this->cleanCalc();
        $calcId = $this->registerCalc();

        /* perform Phase1 compression */
        $dwnlPhase1 = $this->compressPhase1($calcId);

        /* calculate TV & OV on compressed tree */
        $dwnlPhase1 = $this->calcTv($dwnlPhase1);
        $dwnlPhase1 = $this->calcOv($dwnlPhase1);

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
        $ctx->set(PCalcReg::IN_CALC_TYPE_CODE, Cfg::CODE_TYPE_CALC_FORECAST_PHASE2_DEF);
        /** @var \Praxigento\Core\Data $res */
        $res = $this->procCalcReg->exec($ctx);
        $result = $res->get(PCalcReg::OUT_CALC_ID);
        return $result;
    }
}