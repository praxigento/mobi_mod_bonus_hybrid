<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Compress;

use Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Request as AGetPeriodRequest;
use Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Response as AGetPeriodResponse;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Compress\Phase2 as PCpmrsPhase2;

class Phase2
    implements \Praxigento\Core\Api\App\Service\Process
{
    /** Calculation scheme (DEFAULT or EU) */
    const CTX_IN_SCHEME = 'in.scheme';

    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Compress\Phase2 */
    private $procCmprsPhase2;
    /** @var \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent */
    private $servPeriodGet;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $daoBonDwnl;
    /** @var \Praxigento\BonusBase\Repo\Dao\Calculation */
    private $daoCalc;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Compress\Phase2\SaveDownline */
    private $rouSaveDwnl;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\BonusBase\Repo\Dao\Calculation $daoCalc,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoBonDwnl,
        \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent $servPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Compress\Phase2 $procCmprsPhase2,
        \Praxigento\BonusHybrid\Service\Calc\Compress\Phase2\SaveDownline $rouSaveDwnl
    ) {
        $this->logger = $logger;
        $this->daoCalc = $daoCalc;
        $this->daoBonDwnl = $daoBonDwnl;
        $this->servPeriodGet = $servPeriodGet;
        $this->procCmprsPhase2 = $procCmprsPhase2;
        $this->rouSaveDwnl = $rouSaveDwnl;
    }

    /**
     * Collect data for Phase2 compression and call process common for general & forecast calculations.
     *
     * @param $calcIdWriteOff
     * @param $calcIdPhase1
     * @param $calcIdPhase2
     * @param $scheme
     * @return array
     */
    private function compressPhase2($calcIdWriteOff, $calcIdPhase1, $calcIdPhase2, $scheme)
    {
        $dwnlPlain = $this->daoBonDwnl->getByCalcId($calcIdWriteOff);
        $dwnlPhase1 = $this->daoBonDwnl->getByCalcId($calcIdPhase1);
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(PCpmrsPhase2::IN_CALC_ID_PHASE2, $calcIdPhase2);
        $ctx->set(PCpmrsPhase2::IN_SCHEME, $scheme);
        $ctx->set(PCpmrsPhase2::IN_DWNL_PLAIN, $dwnlPlain);
        $ctx->set(PCpmrsPhase2::IN_DWNL_PHASE1, $dwnlPhase1);
        $out = $this->procCmprsPhase2->exec($ctx);
        $dwnlPhase2 = $out->get(PCpmrsPhase2::OUT_DWNL_PHASE2);
        $legs = $out->get(PCpmrsPhase2::OUT_LEGS);
        $result = [$dwnlPhase2, $legs];
        return $result;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /* get working data from context */
        $scheme = $ctx->get(self::CTX_IN_SCHEME) ?? Cfg::SCHEMA_DEFAULT;
        /**
         * perform processing
         */
        $ctx->set(self::CTX_OUT_SUCCESS, false);
        $this->logger->info("Phase2 compression for '$scheme' scheme is started.");
        /**
         * Get dependent calculation data
         *
         * @var \Praxigento\BonusBase\Repo\Data\Calculation $writeOffCalc
         * @var \Praxigento\BonusBase\Repo\Data\Calculation $phase1Calc
         * @var \Praxigento\BonusBase\Repo\Data\Calculation $phase2Calc
         * @var \Praxigento\BonusBase\Repo\Data\Period $phase2Period
         */
        list($writeOffCalc, $phase1Calc, $phase2Calc, $phase2Period) = $this->getCalcData($scheme);
        $writeOffCalcId = $writeOffCalc->getId();
        $phase1CalcId = $phase1Calc->getId();
        $phase2CalcId = $phase2Calc->getId();
        $phase2PeriodId = $phase2Period->getId();
        $dsBegin = $phase2Period->getDstampBegin();
        $dsEnd = $phase2Period->getDstampEnd();
        $this->logger->info("Phase2 compression period #$phase2PeriodId ($dsBegin-$dsEnd)");
        /* perform calculation for given source calculations */
        list($downline, $legs) = $this->compressPhase2($writeOffCalcId, $phase1CalcId, $phase2CalcId, $scheme);
        /* save calculation results */
        $this->rouSaveDwnl->exec($downline, $legs, $writeOffCalcId, $phase1CalcId,$scheme);
        /* mark this calculation complete */
        $this->daoCalc->markComplete($phase2CalcId);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("Phase2 compression for '$scheme' scheme is completed.");
    }

    /**
     * Get calculation/period data related to current calculation.
     *
     * @param string $scheme see \Praxigento\BonusHybrid\Config::SCHEMA_XXX
     * @return array [$writeOffCalc, $phase1Calc, $phaseCalc, $phase2Period]
     */
    private function getCalcData($scheme)
    {
        $calcTypeCode = ($scheme == Cfg::SCHEMA_EU)
            ? Cfg::CODE_TYPE_CALC_COMPRESS_PHASE2_EU
            : Cfg::CODE_TYPE_CALC_COMPRESS_PHASE2_DEF;
        /**
         * Get data for phase2 compression and OV (base) calculations
         */
        $req = new AGetPeriodRequest();
        $req->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_VALUE_OV);
        $req->setDepCalcTypeCode($calcTypeCode);
        /** @var AGetPeriodResponse $resp */
        $resp = $this->servPeriodGet->exec($req);
        /** @var \Praxigento\BonusBase\Repo\Data\Period $phase2Period */
        $phase2Period = $resp->getDepPeriodData();
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $phaseCalc */
        $phaseCalc = $resp->getDepCalcData();
        /**
         * Get data for PV Write Off & phase1 compression calculations
         */
        $req = new AGetPeriodRequest();
        $req->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $req->setDepCalcTypeCode(Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1);
        $req->setDepIgnoreComplete(true);
        /** @var AGetPeriodResponse $resp */
        $resp = $this->servPeriodGet->exec($req);
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $writeOffCalc */
        $writeOffCalc = $resp->getBaseCalcData();
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $phase1Calc */
        $phase1Calc = $resp->getDepCalcData();
        /**
         * Compose results.
         */
        $result = [$writeOffCalc, $phase1Calc, $phaseCalc, $phase2Period];
        return $result;
    }

}