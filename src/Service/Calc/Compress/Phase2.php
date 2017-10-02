<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Compress;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase2 as PCpmrsPhase2;

class Phase2
    implements \Praxigento\BonusHybrid\Service\Calc\Compress\IPhase2
{

    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\A\Traits\TMap {
        mapValueById as protected;
    }

    private $hlp;
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase2 */
    private $procCmprsPhase2;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent */
    private $procPeriodGet;
    /** @var \Praxigento\BonusBase\Repo\Entity\Calculation */
    private $repoCalc;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoDwnlBon;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Compression\Phase2\Legs */
    private $repoLegs;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\BonusHybrid\Service\Calc\Compress\Helper $hlp,
        \Praxigento\BonusBase\Repo\Entity\Calculation $repoCalc,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoDwnlBon,
        \Praxigento\BonusHybrid\Repo\Entity\Compression\Phase2\Legs $repoLegs,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase2 $procCmprsPhase2
    )
    {
        $this->logger = $logger;
        $this->hlp = $hlp;
        $this->repoCalc = $repoCalc;
        $this->repoDwnlBon = $repoDwnlBon;
        $this->repoLegs = $repoLegs;
        $this->procPeriodGet = $procPeriodGet;
        $this->procCmprsPhase2 = $procCmprsPhase2;
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
        $pv = $this->hlp->getPv($calcIdWriteOff);
        $dwnlPlain = $this->repoDwnlBon->getByCalcId($calcIdWriteOff);
        $dwnlPhase1 = $this->repoDwnlBon->getByCalcId($calcIdPhase1);
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(PCpmrsPhase2::IN_CALC_ID_PHASE2, $calcIdPhase2);
        $ctx->set(PCpmrsPhase2::IN_SCHEME, $scheme);
        $ctx->set(PCpmrsPhase2::IN_DWNL_PLAIN, $dwnlPlain);
        $ctx->set(PCpmrsPhase2::IN_DWNL_PHASE1, $dwnlPhase1);
        $ctx->set(PCpmrsPhase2::IN_MAP_PV, $pv);
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
         * @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $writeOffCalc
         * @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $phase1Calc
         * @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $phase2Calc
         * @var \Praxigento\BonusBase\Repo\Entity\Data\Period $phase2Period
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
        $this->saveDownline($downline);
        $this->saveLegs($legs);
        /* mark this calculation complete */
        $this->repoCalc->markComplete($phase2CalcId);
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
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->procPeriodGet::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_VALUE_OV);
        $ctx->set($this->procPeriodGet::CTX_IN_DEP_TYPE_CODE, $calcTypeCode);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Period $phase2Period */
        $phase2Period = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_PERIOD_DATA);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $phaseCalc */
        $phaseCalc = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_CALC_DATA);
        /**
         * Get data for PV Write Off & phase1 compression calculations
         */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->procPeriodGet::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $ctx->set($this->procPeriodGet::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1);
        $ctx->set($this->procPeriodGet::CTX_IN_DEP_IGNORE_COMPLETE, true);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $writeOffCalc */
        $writeOffCalc = $ctx->get($this->procPeriodGet::CTX_OUT_BASE_CALC_DATA);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $phase1Calc */
        $phase1Calc = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_CALC_DATA);
        /**
         * Compose results vector.
         */
        $result = [$writeOffCalc, $phase1Calc, $phaseCalc, $phase2Period];
        return $result;
    }

    private function saveDownline($entries)
    {
        foreach ($entries as $entry) {
            $this->repoDwnlBon->create($entry);
        }
    }

    private function saveLegs($entries)
    {
        foreach ($entries as $entry) {
            $this->repoLegs->create($entry);
        }
    }

}