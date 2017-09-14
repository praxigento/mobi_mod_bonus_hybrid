<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Compress;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Defaults as Def;

class Phase2
    implements \Praxigento\BonusHybrid\Service\Calc\Compress\IPhase2
{

    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\A\Traits\TMap {
        mapValueById as protected;
    }

    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Praxigento\BonusBase\Repo\Entity\Calculation */
    private $repoCalc;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent */
    private $procPeriodGet;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoDwnlBon;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Compression\Phase2\Legs */
    private $repoLegs;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Compress\Phase2\Calc */
    private $subCalc;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\BonusBase\Repo\Entity\Calculation $repoCalc,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoDwnlBon,
        \Praxigento\BonusHybrid\Repo\Entity\Compression\Phase2\Legs $repoLegs,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\Compress\Phase2\Calc $subCalc
    )
    {
        $this->logger = $logger;
        $this->repoCalc = $repoCalc;
        $this->repoDwnlBon = $repoDwnlBon;
        $this->repoLegs = $repoLegs;
        $this->procPeriodGet = $procPeriodGet;
        $this->subCalc = $subCalc;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /* get working data from context */
        $scheme = $ctx->get(self::CTX_IN_SCHEME) ?? Def::SCHEMA_DEFAULT;
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
        $this->logger->info("Phase1 compression period #$phase2PeriodId ($dsBegin-$dsEnd)");
        /* perform calculation for given source calculations */
        $updates = $this->subCalc->exec($writeOffCalcId, $phase1CalcId, $phase2CalcId, $scheme);
        /* save calculation results */
        $downline = $updates->getDownline();
        $this->saveDownline($downline);
        $legs = $updates->getLegs();
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
     * @param string $scheme see \Praxigento\BonusHybrid\Defaults::SCHEMA_XXX
     * @return array [$writeOffCalc, $phase1Calc, $phaseCalc, $phase2Period]
     */
    private function getCalcData($scheme)
    {
        $calcTypeCode = ($scheme == Def::SCHEMA_EU)
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