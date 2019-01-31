<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Service\United;

use Praxigento\BonusBase\Api\Service\Period\Calc\Get\Last\Request as ACalcLastRequest;
use Praxigento\BonusBase\Api\Service\Period\Calc\Get\Last\Response as ACalcLastResponse;
use Praxigento\BonusBase\Repo\Data\Period as EPeriod;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\Forecast\Plain as APlain;
use Praxigento\BonusHybrid\Service\United\Forecast\Request as ARequest;
use Praxigento\BonusHybrid\Service\United\Forecast\Response as AResponse;
use Praxigento\Core\Api\Helper\Period as HPeriod;

/**
 * Aggregated service to perform all activities related to forecast bonus calculation (create calculation,
 * rebuild downline and balances, etc.).
 */
class Forecast
{
    /** @var \Praxigento\BonusBase\Repo\Dao\Period */
    private $daoPeriod;
    /** @var \Praxigento\BonusBase\Repo\Dao\Type\Calc */
    private $daoTypeCalc;
    /** @var \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\Compress */
    private $servCalcCompress;
    /** @var \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Last */
    private $servCalcGetLast;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\Plain */
    private $servCalcPlain;
    /** @var \Praxigento\Downline\Api\Service\Snap\Calc */
    private $servSnapCalc;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\BonusBase\Repo\Dao\Period $daoPeriod,
        \Praxigento\BonusBase\Repo\Dao\Type\Calc $daoTypeCalc,
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        \Praxigento\Downline\Api\Service\Snap\Calc $servSnapCalc,
        \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Last $servCalcGetLast,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\Plain $servCalcPlain,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\Compress $servCalcCompress
    ) {
        $this->logger = $logger;
        $this->daoPeriod = $daoPeriod;
        $this->daoTypeCalc = $daoTypeCalc;
        $this->hlpPeriod = $hlpPeriod;
        $this->servSnapCalc = $servSnapCalc;
        $this->servCalcGetLast = $servCalcGetLast;
        $this->servCalcPlain = $servCalcPlain;
        $this->servCalcCompress = $servCalcCompress;
    }

    private function calcPeriods()
    {
        $req = new ACalcLastRequest();
        $req->setCalcTypeCode(Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        /** @var ACalcLastResponse $resp */
        $resp = $this->servCalcGetLast->exec($req);
        $period = $resp->getPeriod();
        $dsEnd = $period->getDstampEnd();
        $periodNext = $this->hlpPeriod->getPeriodNext($dsEnd, HPeriod::TYPE_MONTH);
        /* compare last calculated period with current period */
        $periodPrev = null;
        $periodCurrent = $this->hlpPeriod->getPeriodCurrent(null, 0, HPeriod::TYPE_MONTH);
        /* period after the last calculated is not equal to current period - we need to calc both */
        if ($periodNext != $periodCurrent) {
            $periodPrev = $periodNext;
        }

        return [$periodPrev, $periodCurrent];
    }

    private function cleanForecastCalcs()
    {
        $typeIdPlain = $this->daoTypeCalc->getIdByCode(Cfg::CODE_TYPE_CALC_FORECAST_PLAIN);
        $typeIdCompress = $this->daoTypeCalc->getIdByCode(Cfg::CODE_TYPE_CALC_FORECAST_PHASE1);
        $byPlain = EPeriod::A_CALC_TYPE_ID . '=' . (int)$typeIdPlain;
        $byCompress = EPeriod::A_CALC_TYPE_ID . '=' . (int)$typeIdCompress;
        $where = "($byPlain) OR ($byCompress)";
        $this->daoPeriod->delete($where);
    }

    /**
     * @param ARequest $request
     * @return AResponse
     * @throws \Exception
     */
    public function exec($request)
    {
        /** define local working data */
        assert($request instanceof ARequest);

        /* perform the main processing */
        $this->logger->info("Aggregated activity to perform forecast calculation is started.");
        $this->logger->info("Rebuild downline snaps for the last days.");
        $this->rebuildDwnlSnaps();
        /* should we calculate one or two periods? */
        list($periodPrev, $periodCurr) = $this->calcPeriods();
        $this->logger->info("Clean up all forecast calculations data.");
        $this->cleanForecastCalcs();
        /* if previous period is not closed yet */
        if ($periodPrev) {
            $this->logger->info("Perform calculation for period '$periodPrev' (previous).");
            $ctx = new \Praxigento\Core\Data();
            $ctx->set(APlain::CTX_IN_PERIOD, $periodPrev);
            /* ... then perform forecast calculations */
            $this->servCalcPlain->exec($ctx);
            $this->servCalcCompress->exec($ctx);
        }
        /* calculation for current period */
        $this->logger->info("Perform calculation for period '$periodCurr' (current).");
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(APlain::CTX_IN_PERIOD, $periodCurr);
        /* ... then perform forecast calculations */
        $this->servCalcPlain->exec($ctx);
        $this->servCalcCompress->exec($ctx);

        /** compose result */
        $this->logger->info("Aggregated activity to perform forecast calculation is completed.");
        $result = new AResponse();
        return $result;
    }

    /**
     * MOBI-1026: re-build downline snaps before calculations.
     *
     * Clean up downline tree snaps for the last 2 days then rebuild it.
     * The last day of the snap would contain incomplete information.
     *
     * TODO: remove it after the last day of the snap will be processed correctly.
     */
    private function rebuildDwnlSnaps()
    {
        $req = new \Praxigento\Downline\Api\Service\Snap\Calc\Request();
        $this->servSnapCalc->exec($req);
    }
}