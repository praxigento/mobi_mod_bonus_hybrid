<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Cli\Cmd;

/**
 * Daily calculation to forecast results on final bonus calc.
 */
class Forecast
    extends \Praxigento\Core\Cli\Cmd\Base
{
    /** @var \Magento\Framework\DB\Adapter\AdapterInterface */
    private $conn;
    /** @var \Magento\Framework\App\ResourceConnection */
    private $resource;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\ICompress */
    private $servCalcCompress;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\IPlain */
    private $servCalcPlain;
    private $servSnap;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Downline\Service\ISnap $servSnap,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\IPlain $servCalcPlain,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\ICompress $servCalcCompress
    )
    {
        parent::__construct(
            $manObj,
            'prxgt:bonus:forecast',
            'Daily calculations to forecast results on final bonus calc.'
        );
        $this->resource = $resource;
        $this->conn = $this->resource->getConnection();
        $this->servSnap = $servSnap;
        $this->servCalcPlain = $servCalcPlain;
        $this->servCalcCompress = $servCalcCompress;
    }

    protected function execute(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    )
    {
        $output->writeln("<info>Start forecast calculations.<info>");
        $this->conn->beginTransaction();
        try {
            $ctx = new \Praxigento\Core\Data();
            /* MOBI-1026: re-build downline snaps before calculations */
            $this->rebuildSnaps();
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

    private function rebuildSnaps()
    {
        $req = new \Praxigento\Downline\Service\Snap\Request\Calc();
        $this->servSnap->calc($req);
    }
}