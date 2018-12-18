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
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Praxigento\Core\Api\App\Repo\Transaction\Manager */
    private $manTrans;
    /** @var \Praxigento\BonusHybrid\Service\United\Forecast */
    private $servUnqual;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\Core\Api\App\Repo\Transaction\Manager $manTrans,
        \Praxigento\BonusHybrid\Service\United\Forecast $servUnqual
    ) {
        parent::__construct(
            $manObj,
            'prxgt:bonus:forecast',
            'Daily calculations to forecast results on final bonus calc.'
        );
        $this->logger = $logger;
        $this->manTrans = $manTrans;
        $this->servUnqual = $servUnqual;
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
            /* rebuild downline snaps for the last days */
            $req = new ARequest();
            /** @var AResponse $resp */
            $resp = $this->servUnqual->exec($req);

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

}