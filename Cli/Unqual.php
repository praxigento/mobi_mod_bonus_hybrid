<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Cli;

use Praxigento\Core\App\Service\IProcess as IProcess;
use Praxigento\Core\Data as AData;

/**
 * Process inactive & unqualified customers.
 */
class Unqual
    extends \Praxigento\Core\App\Cli\Cmd\Base
{
    /** @var \Magento\Framework\DB\Adapter\AdapterInterface */
    private $conn;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Inactive\Collect */
    private $procInactCollect;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Unqualified\Collect */
    private $procUnqualCollect;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Unqualified\Process */
    private $procUnqualProcess;
    /** @var \Magento\Framework\App\ResourceConnection */
    private $resource;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\BonusHybrid\Service\Calc\Inactive\Collect $procInactCollect,
        \Praxigento\BonusHybrid\Service\Calc\Unqualified\Collect $servUnqualCollect,
        \Praxigento\BonusHybrid\Service\Calc\Unqualified\Process $servUnqualProcess
    )
    {
        parent::__construct(
            $manObj,
            'prxgt:unqual:process',
            'Process inactive & unqualified customers.'
        );
        $this->resource = $resource;
        $this->conn = $this->resource->getConnection();
        $this->procInactCollect = $procInactCollect;
        $this->procUnqualCollect = $servUnqualCollect;
        $this->procUnqualProcess = $servUnqualProcess;
    }

    private function calcInactCollect()
    {
        $ctx = new AData();
        $this->procInactCollect->exec($ctx);
        $result = (bool)$ctx->get(IProcess::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcUnqualCollect()
    {
        $ctx = new AData();
        $this->procUnqualCollect->exec($ctx);
        $result = (bool)$ctx->get(IProcess::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcUnqualProcess()
    {
        $ctx = new AData();
        $this->procUnqualProcess->exec($ctx);
        $result = (bool)$ctx->get(IProcess::CTX_OUT_SUCCESS);
        return $result;
    }

    protected function execute(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    )
    {
        $output->writeln("<info>Start inactive/unqualified customers processing.<info>");
        $this->conn->beginTransaction();
        try {
            $canContinue = $this->calcInactCollect();
            if ($canContinue) {
                $output->writeln("<info>Inactive customers stats collection is completed.<info>");
                $canContinue = $this->calcUnqualCollect();
            }
            if ($canContinue) {
                $output->writeln("<info>Unqualified customers stats collection is completed.<info>");
                $canContinue = $this->calcUnqualProcess();
            }
            if ($canContinue) {
                $output->writeln("<info>Unqualified customers stats processing is completed.<info>");
                $this->conn->commit();
                $output->writeln("<info>All data is committed.<info>");
            } else {
                $output->writeln("<error>Something goes wrong. Rollback.<error>");
                $this->conn->rollBack();
            }

        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $trace = $e->getTraceAsString();
            $output->writeln("<error>$msg<error>\n$trace");
            $this->conn->rollBack();
        }
        $output->writeln('<info>Command is completed.<info>');

    }

}