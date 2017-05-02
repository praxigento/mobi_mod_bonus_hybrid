<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Cli\Cmd;

use Praxigento\Accounting\Repo\Query\Balance\OnDate\Closing\ByAsset\Builder as QBalanceClose;

/**
 * Daily calculation to forecast results on final bonus calc.
 */
class Forecast
    extends \Praxigento\Core\Cli\Cmd\Base
{
    /** @var \Praxigento\Accounting\Service\IBalance */
    protected $callBalance;
    /** @var \Praxigento\BonusHybrid\Service\Calc\IForecast */
    protected $callCalcForecast;
    /** @var \Magento\Framework\DB\Adapter\AdapterInterface */
    protected $conn;
    /** @var \Praxigento\Accounting\Repo\Query\Balance\OnDate\Closing\ByAsset\Builder */
    protected $qbldBalClose;
    /** @var \Magento\Framework\App\ResourceConnection */
    protected $resource;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Accounting\Repo\Query\Balance\OnDate\Closing\ByAsset\Builder $qbldBalClose,
        \Praxigento\BonusHybrid\Service\Calc\IForecast $callCalcForecast,
        \Praxigento\Accounting\Service\IBalance $callBalance
    ) {
        parent::__construct(
            $manObj,
            'prxgt:bonus:forecast',
            'Daily calculation to forecast results on final bonus calc.'
        );
        $this->resource = $resource;
        $this->conn = $this->resource->getConnection();
        $this->qbldBalClose = $qbldBalClose;
        $this->callCalcForecast = $callCalcForecast;
        $this->callBalance = $callBalance;
    }

    protected function execute(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    ) {
        $output->writeln("<info>Start forecast calculation.<info>");
        $this->conn->beginTransaction();
        try {
            $req = new \Praxigento\BonusHybrid\Service\Calc\Forecast\Request();
            $resp = $this->callCalcForecast->exec($req);

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