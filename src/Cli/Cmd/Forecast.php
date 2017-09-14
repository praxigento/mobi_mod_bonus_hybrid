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
    protected $conn;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\IPlain */
    protected $procCalcPlain;
    /** @var \Magento\Framework\App\ResourceConnection */
    protected $resource;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\IPlain $procCalcPlain
    )
    {
        parent::__construct(
            $manObj,
            'prxgt:bonus:forecast',
            'Daily calculations to forecast results on final bonus calc.'
        );
        $this->resource = $resource;
        $this->conn = $this->resource->getConnection();
        $this->procCalcPlain = $procCalcPlain;
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
            $this->procCalcPlain->exec($ctx);

            $this->conn->commit();
//            $this->conn->rollBack();
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $trace = $e->getTraceAsString();
            $output->writeln("<error>$msg<error>\n$trace");
            $this->conn->rollBack();
        }
        $output->writeln('<info>Command is completed.<info>');

    }

}