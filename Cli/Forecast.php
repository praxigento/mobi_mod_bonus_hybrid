<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Cli;

use Praxigento\BonusHybrid\Service\United\Forecast\Request as ARequest;
use Praxigento\BonusHybrid\Service\United\Forecast\Response as AResponse;

/**
 * Daily calculation to forecast results on final bonus calc.
 */
class Forecast
    extends \Praxigento\Core\App\Cli\Cmd\Base
{
    /** @var \Magento\Framework\DB\Adapter\AdapterInterface */
    private $conn;
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Magento\Framework\App\ResourceConnection */
    private $resource;
    /** @var \Praxigento\BonusHybrid\Service\United\Forecast */
    private $servUnited;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\BonusHybrid\Service\United\Forecast $servUnited
    ) {
        parent::__construct(
            $manObj,
            'prxgt:bonus:forecast',
            'Daily calculations to forecast results on final bonus calc.'
        );
        $this->resource = $resource;
        $this->conn = $resource->getConnection();
        $this->logger = $logger;
        $this->servUnited = $servUnited;
    }

    protected function execute(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    ) {
        $msg = 'Start forecast calculations.';
        $output->writeln("<info>$msg<info>");
        $this->logger->info($msg);
        /* perform the main processing */
        $this->conn->beginTransaction();
        try {
            $req = new ARequest();
            /** @var AResponse $resp */
            $resp = $this->servUnited->exec($req);

            $this->conn->commit();
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $trace = $e->getTraceAsString();
            $output->writeln("<error>$msg<error>\n$trace");
            $this->logger->error($msg);
            $this->conn->rollBack();
        }
        $msg = 'Command is completed.';
        $output->writeln("<info>$msg<info>");
        $this->logger->info($msg);
    }

}