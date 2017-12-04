<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Cli\Cmd;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\Core\Service\IProcess as PBase;

/**
 * Calculate hybrid bonus.
 */
class Calc
    extends \Praxigento\Core\Cli\Cmd\Base
{
    /** @var  \Praxigento\BonusHybrid\Service\Calc\ISignupDebit */
    private $callBonusSignup;
    /** @var \Magento\Framework\DB\Adapter\AdapterInterface */
    private $conn;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\ICourtesy */
    private $procBonusCourtesy;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\IInfinity */
    private $procBonusInfinity;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\IOverride */
    private $procBonusOvrd;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\IPersonal */
    private $procBonusPers;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\ITeam */
    private $procBonusTeam;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Compress\IPhase1 */
    private $procCompressPhase1;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Compress\IPhase2 */
    private $procCompressPhase2;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Inactive\Collect */
    private $procInactCollect;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Value\IOv */
    private $procOv;
    /** @var \Praxigento\BonusHybrid\Service\Calc\IPvWriteOff */
    private $procPvWriteOff;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Value\ITv */
    private $procTv;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Unqualified\Collect */
    private $procUnqualCollect;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Unqualified\Process */
    private $procUnqualProcess;
    /** @var \Magento\Framework\App\ResourceConnection */
    private $resource;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\BonusHybrid\Service\Calc\ISignupDebit $callBonusSignup,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\ICourtesy $procBonusCourtesy,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\IInfinity $procBonusInfinity,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\IOverride $procBonusOvrd,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\IPersonal $procBonusPers,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\ITeam $procBonusTeam,
        \Praxigento\BonusHybrid\Service\Calc\Compress\IPhase1 $procCompressPhase1,
        \Praxigento\BonusHybrid\Service\Calc\Compress\IPhase2 $procCompressPhase2,
        \Praxigento\BonusHybrid\Service\Calc\Inactive\Collect $procInactCollect,
        \Praxigento\BonusHybrid\Service\Calc\IPvWriteOff $procPvWriteOff,
        \Praxigento\BonusHybrid\Service\Calc\Unqualified\Collect $procUnqualCollect,
        \Praxigento\BonusHybrid\Service\Calc\Unqualified\Process $procUnqualProcess,
        \Praxigento\BonusHybrid\Service\Calc\Value\IOv $procOv,
        \Praxigento\BonusHybrid\Service\Calc\Value\ITv $procTv
    )
    {
        parent::__construct(
            $manObj,
            'prxgt:bonus:calc',
            'Calculate hybrid bonus (run all calcs one-by-one).'
        );
        $this->resource = $resource;
        $this->conn = $this->resource->getConnection();
        $this->callBonusSignup = $callBonusSignup;
        $this->procBonusCourtesy = $procBonusCourtesy;
        $this->procBonusInfinity = $procBonusInfinity;
        $this->procBonusOvrd = $procBonusOvrd;
        $this->procBonusPers = $procBonusPers;
        $this->procBonusTeam = $procBonusTeam;
        $this->procCompressPhase1 = $procCompressPhase1;
        $this->procCompressPhase2 = $procCompressPhase2;
        $this->procInactCollect = $procInactCollect;
        $this->procOv = $procOv;
        $this->procPvWriteOff = $procPvWriteOff;
        $this->procTv = $procTv;
        $this->procUnqualCollect = $procUnqualCollect;
        $this->procUnqualProcess = $procUnqualProcess;
    }

    private function calcBonusCourtesy()
    {
        $ctx = new \Praxigento\Core\Data();
        $this->procBonusCourtesy->exec($ctx);
        $result = (bool)$ctx->get(PBase::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcBonusInfinity($schema)
    {
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->procBonusInfinity::CTX_IN_SCHEME, $schema);
        $this->procBonusInfinity->exec($ctx);
        $result = (bool)$ctx->get(PBase::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcBonusOverride($schema)
    {
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->procBonusOvrd::CTX_IN_SCHEME, $schema);
        $this->procBonusOvrd->exec($ctx);
        $result = (bool)$ctx->get(PBase::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcBonusPersonal()
    {
        $ctx = new \Praxigento\Core\Data();
        $this->procBonusPers->exec($ctx);
        $result = (bool)$ctx->get(PBase::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcBonusTeamDef()
    {
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->procBonusTeam::CTX_IN_SCHEME, Cfg::SCHEMA_DEFAULT);
        $this->procBonusTeam->exec($ctx);
        $result = (bool)$ctx->get(PBase::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcBonusTeamEu()
    {
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->procBonusTeam::CTX_IN_SCHEME, Cfg::SCHEMA_EU);
        $this->procBonusTeam->exec($ctx);
        $result = (bool)$ctx->get(PBase::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcCompressPhase1()
    {
        $ctx = new \Praxigento\Core\Data();
        $this->procCompressPhase1->exec($ctx);
        $result = (bool)$ctx->get(PBase::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcCompressPhase2($schema)
    {
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->procCompressPhase2::CTX_IN_SCHEME, $schema);
        $this->procCompressPhase2->exec($ctx);
        $result = (bool)$ctx->get(PBase::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcInactCollect()
    {
        $ctx = new \Praxigento\Core\Data();
        $this->procInactCollect->exec($ctx);
        $result = (bool)$ctx->get(PBase::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcPvWriteOff()
    {
        $ctx = new \Praxigento\Core\Data();
        $this->procPvWriteOff->exec($ctx);
        $result = (bool)$ctx->get(PBase::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcSignupDebit()
    {
        $ctx = new \Praxigento\Core\Data();
        $this->callBonusSignup->exec($ctx);
        $result = (bool)$ctx->get(PBase::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcUnqualCollect()
    {
        $ctx = new \Praxigento\Core\Data();
        $this->procUnqualCollect->exec($ctx);
        $result = (bool)$ctx->get(PBase::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcUnqualProcess()
    {
        $ctx = new \Praxigento\Core\Data();
        $this->procUnqualProcess->exec($ctx);
        $result = (bool)$ctx->get(PBase::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcValueOv()
    {
        $ctx = new \Praxigento\Core\Data();
        $this->procOv->exec($ctx);
        $result = (bool)$ctx->get(PBase::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcValueTv()
    {
        $ctx = new \Praxigento\Core\Data();
        $this->procTv->exec($ctx);
        $result = (bool)$ctx->get(PBase::CTX_OUT_SUCCESS);
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
            $canContinue = $this->calcSignupDebit();
            if ($canContinue) {
                $output->writeln("<info>'Sign Up Volume Debit' calculation is completed.<info>");
                $canContinue = $this->calcPvWriteOff();
            }
            if ($canContinue) {
                $output->writeln("<info>'PV Write Off' calculation is completed.<info>");
                $canContinue = $this->calcInactCollect();
            }
            if ($canContinue) {
                $output->writeln("<info>'Inactive Stats Collection' calculation is completed.<info>");
                $canContinue = $this->calcCompressPhase1();
            }
            if ($canContinue) {
                $output->writeln("<info>'Phase I' compression is completed.<info>");
                $canContinue = $this->calcUnqualCollect();
            }
            if ($canContinue) {
                $output->writeln("<info>'Unqualified Stats Collection' calculation is completed.<info>");
                $canContinue = $this->calcUnqualProcess();
            }
            if ($canContinue) {
                $output->writeln("<info>'Unqualified Process' calculation is completed.<info>");
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