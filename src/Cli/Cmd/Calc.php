<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Cli\Cmd;

use Praxigento\BonusHybrid\Defaults as Def;

/**
 * Calculate hybrid bonus.
 */
class Calc
    extends \Praxigento\Core\Cli\Cmd\Base
{
    /** @var  \Praxigento\BonusHybrid\Service\Calc\ISignupDebit */
    private $callBonusSignup;
    /** @var \Praxigento\BonusHybrid\Service\ICalc */
    private $callCalc;
    /** @var \Magento\Framework\DB\Adapter\AdapterInterface */
    private $conn;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\ICourtesy */
    private $procBonusCourtesy;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\IPersonal */
    private $procBonusPers;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\ITeam */
    private $procBonusTeam;
    /** @var \Praxigento\BonusHybrid\Service\Calc\ICompressPhase1 */
    private $procCompressPhase1;
    /** @var \Praxigento\BonusHybrid\Service\Calc\IPvWriteOff */
    private $procPvWriteOff;
    /** @var \Praxigento\BonusHybrid\Service\Calc\IValueTv */
    private $procTv;
    /** @var \Magento\Framework\App\ResourceConnection */
    private $resource;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\BonusHybrid\Service\ICalc $callCalc,
        \Praxigento\BonusHybrid\Service\Calc\ISignupDebit $callBonusSignup,
        \Praxigento\BonusHybrid\Service\Calc\ICompressPhase1 $procCompressPhase1,
        \Praxigento\BonusHybrid\Service\Calc\IPvWriteOff $procPvWriteOff,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\ICourtesy $procBonusCourtesy,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\IPersonal $procBonusPers,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\ITeam $procBonusTeam,
        \Praxigento\BonusHybrid\Service\Calc\IValueTv $procTv
    ) {
        parent::__construct(
            $manObj,
            'prxgt:bonus:calc',
            'Calculate hybrid bonus (run all calcs one-by-one).'
        );
        $this->resource = $resource;
        $this->conn = $this->resource->getConnection();
        $this->callCalc = $callCalc;
        $this->callBonusSignup = $callBonusSignup;
        $this->procCompressPhase1 = $procCompressPhase1;
        $this->procPvWriteOff = $procPvWriteOff;
        $this->procBonusCourtesy = $procBonusCourtesy;
        $this->procBonusPers = $procBonusPers;
        $this->procBonusTeam = $procBonusTeam;
        $this->procTv = $procTv;
    }

    private function calcBonusCourtesy()
    {
        $ctx = new \Praxigento\Core\Data();
        $this->procBonusCourtesy->exec($ctx);
        $result = (bool)$ctx->get($this->procBonusCourtesy::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcBonusInfinityDef()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\Request\BonusInfinity();
        $req->setScheme(\Praxigento\BonusHybrid\Defaults::SCHEMA_DEFAULT);
        $resp = $this->callCalc->bonusInfinity($req);
        $result = $resp->isSucceed();
        return $result;
    }

    private function calcBonusInfinityEu()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\Request\BonusInfinity();
        $req->setScheme(\Praxigento\BonusHybrid\Defaults::SCHEMA_EU);
        $resp = $this->callCalc->bonusInfinity($req);
        $result = $resp->isSucceed();
        return $result;
    }

    private function calcBonusOverrideDef()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\Request\BonusOverride();
        $req->setScheme(\Praxigento\BonusHybrid\Defaults::SCHEMA_DEFAULT);
        $resp = $this->callCalc->bonusOverride($req);
        $result = $resp->isSucceed();
        return $result;
    }

    private function calcBonusOverrideEu()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\Request\BonusOverride();
        $req->setScheme(\Praxigento\BonusHybrid\Defaults::SCHEMA_EU);
        $resp = $this->callCalc->bonusOverride($req);
        $result = $resp->isSucceed();
        return $result;
    }

    private function calcBonusPersonal()
    {
        $ctx = new \Praxigento\Core\Data();
        $this->procBonusPers->exec($ctx);
        $result = (bool)$ctx->get($this->procBonusPers::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcBonusTeamDef()
    {
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->procBonusTeam::CTX_IN_SCHEME, Def::SCHEMA_DEFAULT);
        $this->procBonusTeam->exec($ctx);
        $result = (bool)$ctx->get($this->procBonusTeam::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcBonusTeamEu()
    {
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->procBonusTeam::CTX_IN_SCHEME, Def::SCHEMA_EU);
        $this->procBonusTeam->exec($ctx);
        $result = (bool)$ctx->get($this->procBonusTeam::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcCompressOiDef()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\Request\CompressOi();
        $req->setScheme(\Praxigento\BonusHybrid\Defaults::SCHEMA_DEFAULT);
        $resp = $this->callCalc->compressOi($req);
        $result = $resp->isSucceed();
        return $result;
    }

    private function calcCompressOiEu()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\Request\CompressOi();
        $req->setScheme(\Praxigento\BonusHybrid\Defaults::SCHEMA_EU);
        $resp = $this->callCalc->compressOi($req);
        $result = $resp->isSucceed();
        return $result;
    }

    private function calcCompressPhase1()
    {
        $ctx = new \Praxigento\Core\Data();
        $this->procCompressPhase1->exec($ctx);
        $result = (bool)$ctx->get($this->procCompressPhase1::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcPvWriteOff()
    {
        $ctx = new \Praxigento\Core\Data();
        $this->procPvWriteOff->exec($ctx);
        $result = (bool)$ctx->get($this->procPvWriteOff::CTX_OUT_SUCCESS);
        return $result;
    }

    private function calcSignupDebit()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\SignupDebit\Request();
        $resp = $this->callBonusSignup->exec($req);
        $result = $resp->isSucceed();
        return $result;
    }

    private function calcValueOv()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\Request\ValueOv();
        $resp = $this->callCalc->valueOv($req);
        $result = $resp->isSucceed();
        return $result;
    }

    private function calcValueTv()
    {
        $ctx = new \Praxigento\Core\Data();
        $this->procTv->exec($ctx);
        $result = (bool)$ctx->get($this->procTv::CTX_OUT_SUCCESS);
        return $result;
    }

    protected function execute(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    ) {
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
                $canContinue = $this->calcCompressPhase1();
            }
            if ($canContinue) {
                $output->writeln("<info>Phase I compression is completed.<info>");
                $canContinue = $this->calcBonusPersonal();
            }
            if ($canContinue) {
                $output->writeln("<info>Personal bonus (DEFAULT) is calculated.<info>");
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
                $canContinue = $this->calcCompressOiDef();
            }
            if ($canContinue) {
                $output->writeln("<info>Phase II compression (DEFAULT) is completed.<info>");
                $canContinue = $this->calcCompressOiEu();
            }
            if ($canContinue) {
                $output->writeln("<info>Phase II compression (EU) is completed.<info>");
                $canContinue = $this->calcBonusOverrideDef();
            }
            if ($canContinue) {
                $output->writeln("<info>Override bonus (DEFAULT) is calculated.<info>");
                $canContinue = $this->calcBonusOverrideEu();
            }
            if ($canContinue) {
                $output->writeln("<info>Override bonus (EU) is calculated.<info>");
                $canContinue = $this->calcBonusInfinityDef();
            }
            if ($canContinue) {
                $output->writeln("<info>Infinity bonus (DEFAULT) is calculated.<info>");
                $canContinue = $this->calcBonusInfinityEu();
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