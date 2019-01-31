<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Cli;

use Praxigento\BonusHybrid\Service\Downgrade\Request as ARequest;

/**
 * Downgrade unqualified customers.
 */
class Downgrade
    extends \Praxigento\Core\App\Cli\Cmd\Base
{
    /** @var \Magento\Framework\DB\Adapter\AdapterInterface */
    private $conn;
    /** @var \Magento\Framework\App\ResourceConnection */
    private $resource;
    /** @var \Praxigento\BonusHybrid\Service\Downgrade */
    private $srvDowngrade;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\BonusHybrid\Service\Downgrade $servDowngrade
    ) {
        parent::__construct(
            $manObj,
            'prxgt:bonus:downgrade',
            'Downgrade unqualified customers.'
        );
        $this->resource = $resource;
        $this->conn = $this->resource->getConnection();
        $this->srvDowngrade = $servDowngrade;
    }

    protected function execute(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    ) {
        $output->writeln("<info>Command '" . $this->getName() . "'<info>");
        $this->conn->beginTransaction();
        try {
            $req = new ARequest();
            $this->srvDowngrade->exec($req);
            $this->conn->commit();
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $trace = $e->getTraceAsString();
            $output->writeln("<error>$msg<error>\n$trace");
            $this->conn->rollBack();
        }
        $output->writeln('<info>Command \'' . $this->getName() . '\' is completed.<info>');
    }

}