<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Cli;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\Core\App\Service\IProcess as IProcess;

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
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\Courtesy */
    private $servBonusCourtesy;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\Infinity */
    private $servBonusInfinity;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\Override */
    private $servBonusOvrd;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\Personal */
    private $servBonusPers;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\SignUpDebit */
    private $servBonusSignup;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\Team */
    private $servBonusTeam;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Compress\Phase1 */
    private $servCompressPhase1;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Compress\Phase2 */
    private $servCompressPhase2;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Value\Ov */
    private $servOv;
    /** @var \Praxigento\BonusHybrid\Service\Calc\PvWriteOff */
    private $servPvWriteOff;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Value\Tv */
    private $servTv;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\BonusHybrid\Service\Calc\SignUpDebit $servBonusSignup,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Courtesy $servBonusCourtesy,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Infinity $servBonusInfinity,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Override $servBonusOvrd,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Personal $servBonusPers,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Team $servBonusTeam,
        \Praxigento\BonusHybrid\Service\Calc\Compress\Phase1 $servCompressPhase1,
        \Praxigento\BonusHybrid\Service\Calc\Compress\Phase2 $servCompressPhase2,
        \Praxigento\BonusHybrid\Service\Calc\PvWriteOff $servPvWriteOff,
        \Praxigento\BonusHybrid\Service\Calc\Value\Ov $servOv,
        \Praxigento\BonusHybrid\Service\Calc\Value\Tv $servTv
    )
    {
        parent::__construct(
            $manObj,
            'prxgt:bonus:calc',
            'Calculate hybrid bonus (run all calcs one-by-one).'
        );
        $this->resource = $resource;
        $this->conn = $this->resource->getConnection();
        $this->servBonusSignup = $servBonusSignup;
        $this->servBonusCourtesy = $servBonusCourtesy;
        $this->servBonusInfinity = $servBonusInfinity;
        $this->servBonusOvrd = $servBonusOvrd;
        $this->servBonusPers = $servBonusPers;
        $this->servBonusTeam = $servBonusTeam;
        $this->servCompressPhase1 = $servCompressPhase1;
        $this->servCompressPhase2 = $servCompressPhase2;
        $this->servOv = $servOv;
        $this->servPvWriteOff = $servPvWriteOff;
        $this->servTv = $servTv;
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
        $this->servBonusSignup->exec($ctx);
        $result = (bool)$ctx->get(IProcess::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcValueOv()
    {
        $ctx = new \Praxigento\Core\Data();
        $this->servOv->exec($ctx);
        $result = (bool)$ctx->get(IProcess::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcValueTv()
    {
        $ctx = new \Praxigento\Core\Data();
        $this->servTv->exec($ctx);
        $result = (bool)$ctx->get(IProcess::CTX_OUT_SUCCESS);
        return $result;
    }

    protected function execute(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    )
    {
        $output->writeln("<info>Start bonus calculation.<info>");
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