<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Cli;

use Praxigento\BonusHybrid\Service\Calc\Forecast\Plain as APlain;

/**
 * Daily calculation to forecast results on final bonus calc.
 */
class Forecast
    extends \Praxigento\Core\App\Cli\Cmd\Base
{
    /** @var \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var \Praxigento\Core\Api\App\Repo\Transaction\Manager */
    private $manTrans;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\Compress */
    private $servCalcCompress;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\Plain */
    private $servCalcPlain;
    /** @var \Praxigento\Downline\Api\Service\Snap\Calc */
    private $servSnapCalc;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\Core\Api\App\Repo\Transaction\Manager $manTrans,
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        \Praxigento\Downline\Api\Service\Snap\Calc $servSnapCalc,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\Plain $servCalcPlain,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\Compress $servCalcCompress
    ) {
        parent::__construct(
            $manObj,
            'prxgt:bonus:forecast',
            'Daily calculations to forecast results on final bonus calc.'
        );
        $this->manTrans = $manTrans;
        $this->hlpPeriod = $hlpPeriod;
        $this->servSnapCalc = $servSnapCalc;
        $this->servCalcPlain = $servCalcPlain;
        $this->servCalcCompress = $servCalcCompress;

    }

    private function calcPeriods()
    {

    }

    protected function execute(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    ) {
        $output->writeln("<info>Start forecast calculations.<info>");
        /* perform the main processing */
        $def = $this->manTrans->begin();
        try {
            /* rebuild downline snaps for the last day */
            $this->rebuildDwnlSnaps();
            /* should we calculate one or two periods? */
            list($periodPrev, $periodCurr) = $this->calcPeriods();
            /* if previous period is not closed yet */
            if ($periodPrev) {
                $ctx = new \Praxigento\Core\Data();
                $ctx->set(APlain::CTX_IN_PERIOD, $periodPrev);
                /* ... then perform forecast calculations */
                $this->servCalcPlain->exec($ctx);
                $this->servCalcCompress->exec($ctx);
            }
            /* calculation for current period */
            $ctx = new \Praxigento\Core\Data();
            $ctx->set(APlain::CTX_IN_PERIOD, $periodCurr);
            /* ... then perform forecast calculations */
            $this->servCalcPlain->exec($ctx);
            $this->servCalcCompress->exec($ctx);

            $this->manTrans->commit($def);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $trace = $e->getTraceAsString();
            $output->writeln("<error>$msg<error>\n$trace");
            $this->manTrans->rollback($def);
        }
        $output->writeln('<info>Command is completed.<info>');
    }

    /**
     * MOBI-1026: re-build downline snaps before calculations.
     *
     * Clean up downline tree snaps for the last 2 days then rebuild it.
     * The last day of the snap would contain incomplete information.
     *
     * ATTENTION: clean up service uses TRUNCATE statement and cannot be performed inside transaction.
     *
     * TODO: remove it after the last day of the snap will be processed correctly.
     */
    private function rebuildDwnlSnaps()
    {
        $req = new \Praxigento\Downline\Api\Service\Snap\Calc\Request();
        $this->servSnapCalc->exec($req);
    }
}