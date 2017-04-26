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
    /** @var \Magento\Framework\App\ResourceConnection */
    protected $resource;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Magento\Framework\App\ResourceConnection $resource

    ) {
        parent::__construct(
            $manObj,
            'prxgt:bonus:forecast',
            'Daily calculation to forecast results on final bonus calc.'
        );
        $this->resource = $resource;
        $this->conn = $this->resource->getConnection();
    }

    protected function execute(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    ) {
        $output->writeln("<info>Start forecast calculation.<info>");
        $this->conn->beginTransaction();
        try {

//            $this->conn->commit();
            $this->conn->rollBack();

        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $trace = $e->getTraceAsString();
            $output->writeln("<error>$msg<error>\n$trace");
            $this->conn->rollBack();
        }
        $output->writeln('<info>Command is completed.<info>');

    }

}