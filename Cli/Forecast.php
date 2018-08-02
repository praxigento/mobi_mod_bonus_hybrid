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
    const OPT_PERIOD_NAME = 'period';
    const OPT_PERIOD_SHORT = 'p';

    /** @var \Magento\Framework\DB\Adapter\AdapterInterface */
    private $conn;
    /** @var \Magento\Framework\App\ResourceConnection */
    private $resource;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\Compress */
    private $servCalcCompress;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\Plain */
    private $servCalcPlain;
    /** @var \Praxigento\Downline\Api\Service\Snap\Clean */
    private $servDwnlClean;
    /** @var \Praxigento\Downline\Service\ISnap */
    private $servSnap;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Downline\Service\ISnap $servSnap,
        \Praxigento\Downline\Api\Service\Snap\Clean $servDwnlClean,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\Plain $servCalcPlain,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\Compress $servCalcCompress
    ) {
        parent::__construct(
            $manObj,
            'prxgt:bonus:forecast',
            'Daily calculations to forecast results on final bonus calc.'
        );
        $this->resource = $resource;
        $this->conn = $this->resource->getConnection();
        $this->servSnap = $servSnap;
        $this->servDwnlClean = $servDwnlClean;
        $this->servCalcPlain = $servCalcPlain;
        $this->servCalcCompress = $servCalcCompress;

        $this->addOption(
            self::OPT_PERIOD_NAME,
            self::OPT_PERIOD_SHORT,
            \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL,
            'Period forcalculation (201701, 201702, ...).'
        );
    }

    private function buildSnaps()
    {
        $req = new \Praxigento\Downline\Service\Snap\Request\Calc();
        $this->servSnap->calc($req);
    }

    private function cleanSnaps()
    {
        /* Clean up berfore rebuild. TODO: remove it after the last day of the snap will be processed correctly */
        $req = new \Praxigento\Downline\Api\Service\Snap\Clean\Request();
        $this->servDwnlClean->exec($req);
    }

    protected function execute(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    ) {
        $output->writeln("<info>Start forecast calculations.<info>");
        /* DDL statement TRUNCATE cannot be used inside transaction. */
        $this->cleanSnaps();
        /* perform the main processing */
        $this->conn->beginTransaction();
        try {
            $period = $input->getOption(self::OPT_PERIOD_NAME);
            $ctx = new \Praxigento\Core\Data();
            $ctx->set(APlain::CTX_IN_PERIOD, $period);
            /* MOBI-1026: re-build downline snaps before calculations */
            $this->buildSnaps();
            /* ... then perform forecast calculations */
            $this->servCalcPlain->exec($ctx);
            $this->servCalcCompress->exec($ctx);
            $this->conn->commit();
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $trace = $e->getTraceAsString();
            $output->writeln("<error>$msg<error>\n$trace");
            $this->conn->rollBack();
        }
        $output->writeln('<info>Command is completed.<info>');
    }
}