<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Cli;

use Praxigento\Core\Api\App\Service\Process as IProcess;
use Praxigento\Core\Data as AData;

/**
 * Process unqualified customers.
 */
class Unqual
    extends \Praxigento\Core\App\Cli\Cmd\Base
{
    /** @var \Magento\Framework\DB\Adapter\AdapterInterface */
    private $conn;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Unqualified\Collect */
    private $procCollect;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Unqualified\Process */
    private $procProcess;
    /** @var \Magento\Framework\App\ResourceConnection */
    private $resource;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\BonusHybrid\Service\Calc\Unqualified\Collect $servCollect,
        \Praxigento\BonusHybrid\Service\Calc\Unqualified\Process $servProcess
    )
    {
        parent::__construct(
            $manObj,
            'prxgt:unqual:process',
            'Process unqualified customers.'
        );
        $this->resource = $resource;
        $this->conn = $this->resource->getConnection();
        $this->procCollect = $servCollect;
        $this->procProcess = $servProcess;
    }

    private function calcUnqualCollect()
    {
        $ctx = new AData();
        $this->procCollect->exec($ctx);
        $result = (bool)$ctx->get(IProcess::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcUnqualProcess()
    {
        $ctx = new AData();
        $this->procProcess->exec($ctx);
        $result = (bool)$ctx->get(IProcess::CTX_OUT_SUCCESS);
        return $result;
    }

    protected function execute(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    )
    {
        $output->writeln("<info>Command '" . $this->getName() . "'<info>");
        $this->conn->beginTransaction();
        try {
            $canContinue = $this->calcUnqualCollect();
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
        $output->writeln('<info>Command \'' . $this->getName() . '\' is completed.<info>');
    }

}