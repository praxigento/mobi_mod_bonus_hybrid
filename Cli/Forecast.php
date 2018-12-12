<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Cli;

use Praxigento\BonusBase\Api\Service\Period\Calc\Get\Last\Request as ACalcLastRequest;
use Praxigento\BonusBase\Api\Service\Period\Calc\Get\Last\Response as ACalcLastResponse;
use Praxigento\BonusBase\Repo\Data\Period as EPeriod;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\Forecast\Plain as APlain;
use Praxigento\BonusHybrid\Service\Calc\Forecast\Unqualified\Request as ACalcUnqualRequest;
use Praxigento\BonusHybrid\Service\Calc\Forecast\Unqualified\Response as ACalcUnqualResponse;
use Praxigento\Core\Api\Helper\Period as HPeriod;

/**
 * Daily calculation to forecast results on final bonus calc.
 */
class Forecast
    extends \Praxigento\Core\App\Cli\Cmd\Base
{
    /** @var \Praxigento\BonusBase\Repo\Dao\Period */
    private $daoPeriod;
    /** @var \Praxigento\BonusBase\Repo\Dao\Type\Calc */
    private $daoTypeCalc;
    /** @var \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Praxigento\Core\Api\App\Repo\Transaction\Manager */
    private $manTrans;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\Compress */
    private $servCalcCompress;
    /** @var \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Last */
    private $servCalcGetLast;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\Plain */
    private $servCalcPlain;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\Unqualified */
    private $servCalcUnqual;
    /** @var \Praxigento\Downline\Api\Service\Snap\Calc */
    private $servSnapCalc;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\Core\Api\App\Repo\Transaction\Manager $manTrans,
        \Praxigento\BonusBase\Repo\Dao\Period $daoPeriod,
        \Praxigento\BonusBase\Repo\Dao\Type\Calc $daoTypeCalc,
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        \Praxigento\Downline\Api\Service\Snap\Calc $servSnapCalc,
        \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Last $servCalcGetLast,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\Plain $servCalcPlain,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\Compress $servCalcCompress,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\Unqualified $servCalcUnqual
    ) {
        parent::__construct(
            $manObj,
            'prxgt:bonus:forecast',
            'Daily calculations to forecast results on final bonus calc.'
        );
        $this->logger = $logger;
        $this->manTrans = $manTrans;
        $this->daoPeriod = $daoPeriod;
        $this->daoTypeCalc = $daoTypeCalc;
        $this->hlpPeriod = $hlpPeriod;
        $this->servSnapCalc = $servSnapCalc;
        $this->servCalcGetLast = $servCalcGetLast;
        $this->servCalcPlain = $servCalcPlain;
        $this->servCalcCompress = $servCalcCompress;
        $this->servCalcUnqual = $servCalcUnqual;
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

    protected function execute(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    ) {
        $msg = 'Start forecast calculations.';
        $output->writeln("<info>$msg<info>");
        $this->logger->info($msg);
        /* perform the main processing */
        $def = $this->manTrans->begin();
        try {
            /* rebuild downline snaps for the last day */
            $this->rebuildDwnlSnaps();
            /* should we calculate one or two periods? */
            list($periodPrev, $periodCurr) = $this->calcPeriods();
            /* clean up forecast calculations data */
            $this->cleanForecastCalcs();
            /* if previous period is not closed yet */
            if ($periodPrev) {
                $ctx = new \Praxigento\Core\Data();
                $ctx->set(APlain::CTX_IN_PERIOD, $periodPrev);
                /* ... then perform forecast calculations */
                $this->servCalcPlain->exec($ctx);
                $this->servCalcCompress->exec($ctx);
                $this->calcUnqualified($periodPrev);
            }
            /* calculation for current period */
            $ctx = new \Praxigento\Core\Data();
            $ctx->set(APlain::CTX_IN_PERIOD, $periodCurr);
            /* ... then perform forecast calculations */
            $this->servCalcPlain->exec($ctx);
            $this->servCalcCompress->exec($ctx);
            $this->calcUnqualified($periodCurr);

            $this->manTrans->commit($def);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $trace = $e->getTraceAsString();
            $output->writeln("<error>$msg<error>\n$trace");
            $this->logger->error($msg);
            $this->manTrans->rollback($def);
        }
        $msg = 'Command is completed.';
        $output->writeln("<info>$msg<info>");
        $this->logger->info($msg);
    }

    private function calcUnqualified($period)
    {
        $req = new ACalcUnqualRequest();
        $req->setPeriod($period);
        /** @var ACalcUnqualResponse $resp */
        $resp = $this->servCalcUnqual->exec($req);
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