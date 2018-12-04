<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Cli;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\Core\Api\App\Service\Process as IProcess;

/**
 * Calculate hybrid bonus.
 */
class Calc
    extends \Praxigento\Core\App\Cli\Cmd\Base
{
    /** @var \Magento\Framework\DB\Adapter\AdapterInterface */
    private $conn;
    /** @var \Magento\Framework\App\ResourceConnection */
    private $resource;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Aggregate */
    private $servAgg;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\Courtesy */
    private $servBonusCourtesy;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\Infinity */
    private $servBonusInfinity;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\Override */
    private $servBonusOvrd;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\Personal */
    private $servBonusPers;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\Team */
    private $servBonusTeam;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Compress\Phase1 */
    private $servCompressPhase1;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Compress\Phase2 */
    private $servCompressPhase2;
    /** @var \Praxigento\BonusHybrid\Service\Calc\PvWriteOff */
    private $servPvWriteOff;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\SignUpDebit */
    private $servSignup;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Value\Ov */
    private $servValueOv;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Value\Tv */
    private $servValueTv;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\BonusHybrid\Service\Calc\Aggregate $servAgg,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Courtesy $servBonusCourtesy,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Infinity $servBonusInfinity,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Override $servBonusOvrd,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Personal $servBonusPers,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Team $servBonusTeam,
        \Praxigento\BonusHybrid\Service\Calc\Compress\Phase1 $servCompressPhase1,
        \Praxigento\BonusHybrid\Service\Calc\Compress\Phase2 $servCompressPhase2,
        \Praxigento\BonusHybrid\Service\Calc\PvWriteOff $servPvWriteOff,
        \Praxigento\BonusHybrid\Service\Calc\SignUpDebit $servSignup,
        \Praxigento\BonusHybrid\Service\Calc\Value\Ov $servValueOv,
        \Praxigento\BonusHybrid\Service\Calc\Value\Tv $servValueTv
    ) {
        parent::__construct(
            $manObj,
            'prxgt:bonus:calc',
            'Calculate hybrid bonus (run all calcs one-by-one).'
        );
        $this->resource = $resource;
        $this->conn = $this->resource->getConnection();
        $this->servAgg = $servAgg;
        $this->servBonusCourtesy = $servBonusCourtesy;
        $this->servBonusInfinity = $servBonusInfinity;
        $this->servBonusOvrd = $servBonusOvrd;
        $this->servBonusPers = $servBonusPers;
        $this->servBonusTeam = $servBonusTeam;
        $this->servCompressPhase1 = $servCompressPhase1;
        $this->servCompressPhase2 = $servCompressPhase2;
        $this->servPvWriteOff = $servPvWriteOff;
        $this->servSignup = $servSignup;
        $this->servValueOv = $servValueOv;
        $this->servValueTv = $servValueTv;
    }

    private function calcAggregate()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\Aggregate\Request();
        $resp = $this->servAgg->exec($req);
        $err = $resp->getErrorCode();
        $result = $err == \Praxigento\Core\Api\App\Service\Response::ERR_NO_ERROR;
        return $result;
    }

    private function calcBonusCourtesy()
    {
        $ctx = new \Praxigento\Core\Data();
        $this->servBonusCourtesy->exec($ctx);
        $result = (bool)$ctx->get(IProcess::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcBonusInfinity($schema)
    {
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->servBonusInfinity::CTX_IN_SCHEME, $schema);
        $this->servBonusInfinity->exec($ctx);
        $result = (bool)$ctx->get(IProcess::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcBonusOverride($schema)
    {
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->servBonusOvrd::CTX_IN_SCHEME, $schema);
        $this->servBonusOvrd->exec($ctx);
        $result = (bool)$ctx->get(IProcess::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcBonusPersonal()
    {
        $ctx = new \Praxigento\Core\Data();
        $this->servBonusPers->exec($ctx);
        $result = (bool)$ctx->get(IProcess::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcBonusTeamDef()
    {
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->servBonusTeam::CTX_IN_SCHEME, Cfg::SCHEMA_DEFAULT);
        $this->servBonusTeam->exec($ctx);
        $result = (bool)$ctx->get(IProcess::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcBonusTeamEu()
    {
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->servBonusTeam::CTX_IN_SCHEME, Cfg::SCHEMA_EU);
        $this->servBonusTeam->exec($ctx);
        $result = (bool)$ctx->get(IProcess::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcCompressPhase1()
    {
        $ctx = new \Praxigento\Core\Data();
        $this->servCompressPhase1->exec($ctx);
        $result = (bool)$ctx->get(IProcess::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcCompressPhase2($schema)
    {
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->servCompressPhase2::CTX_IN_SCHEME, $schema);
        $this->servCompressPhase2->exec($ctx);
        $result = (bool)$ctx->get(IProcess::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcPvWriteOff()
    {
        $ctx = new \Praxigento\Core\Data();
        $this->servPvWriteOff->exec($ctx);
        $result = (bool)$ctx->get(IProcess::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcSignUpDebit()
    {
        $ctx = new \Praxigento\Core\Data();
        $this->servSignup->exec($ctx);
        $result = (bool)$ctx->get(IProcess::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcValueOv()
    {
        $ctx = new \Praxigento\Core\Data();
        $this->servValueOv->exec($ctx);
        $result = (bool)$ctx->get(IProcess::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcValueTv()
    {
        $ctx = new \Praxigento\Core\Data();
        $this->servValueTv->exec($ctx);
        $result = (bool)$ctx->get(IProcess::CTX_OUT_SUCCESS);
        return $result;
    }

    protected function execute(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    ) {
        $output->writeln("<info>Command '" . $this->getName() . "'<info>");
        $this->conn->beginTransaction();
        try {
            $canContinue = $this->calcSignUpDebit();
            if ($canContinue) {
                $output->writeln("<info>'Sign Up Volume Debit' calculation is completed.<info>");
                $canContinue = $this->calcPvWriteOff();
            }
            if ($canContinue) {
                $output->writeln("<info>'PV Write Off' calculation is completed.<info>");
                $canContinue = $this->calcCompressPhase1();
            }
            if ($canContinue) {
                $output->writeln("<info>'Phase I' compression is completed.<info>");
                $canContinue = $this->calcBonusPersonal();
            }
            if ($canContinue) {
                $output->writeln("<info>Personal bonus is calculated.<info>");
                $canContinue = $this->calcValueTv();
            }
            if ($canContinue) {
                $output->writeln("<info>TV are calculated.<info>");
                $canContinue = $this->calcBonusTeamDef();
            }
            if ($canContinue) {
                $output->writeln("<info>Team bonus (DEFAULT) is calculated.<info>");
                $canContinue = $this->calcBonusTeamEu();
            }
            if ($canContinue) {
                $output->writeln("<info>Team bonus (EU) is calculated.<info>");
                $canContinue = $this->calcBonusCourtesy();
            }
            if ($canContinue) {
                $output->writeln("<info>Courtesy bonus is calculated.<info>");
                $canContinue = $this->calcValueOv();
            }
            if ($canContinue) {
                $output->writeln("<info>OV are calculated.<info>");
                $canContinue = $this->calcCompressPhase2(Cfg::SCHEMA_DEFAULT);
            }
            if ($canContinue) {
                $output->writeln("<info>Phase II compression (DEFAULT) is completed.<info>");
                $canContinue = $this->calcCompressPhase2(Cfg::SCHEMA_EU);
            }
            if ($canContinue) {
                $output->writeln("<info>Phase II compression (EU) is completed.<info>");
                $canContinue = $this->calcBonusOverride(Cfg::SCHEMA_DEFAULT);
            }
            if ($canContinue) {
                $output->writeln("<info>Override bonus (DEFAULT) is calculated.<info>");
                $canContinue = $this->calcBonusOverride(Cfg::SCHEMA_EU);
            }
            if ($canContinue) {
                $output->writeln("<info>Override bonus (EU) is calculated.<info>");
                $canContinue = $this->calcBonusInfinity(Cfg::SCHEMA_DEFAULT);
            }
            if ($canContinue) {
                $output->writeln("<info>Infinity bonus (DEFAULT) is calculated.<info>");
                $canContinue = $this->calcBonusInfinity(Cfg::SCHEMA_EU);
            }
            if ($canContinue) {
                $output->writeln("<info>Infinity bonus (EU) is calculated.<info>");
                $canContinue = $this->calcAggregate();
            }
            if ($canContinue) {
                $output->writeln("<info>Bonus aggregation is calculated.<info>");
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