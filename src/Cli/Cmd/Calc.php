<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Cli\Cmd;

/**
 * Calculate hybrid bonus.
 */
class Calc
    extends \Praxigento\Core\Cli\Cmd\Base
{
    /** @var \Praxigento\BonusHybrid\Service\Calc\ICompressPhase1 */
    protected $callBonusCompressPhase1;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\ISignupDebit */
    protected $callBonusSignup;
    /** @var \Praxigento\BonusHybrid\Service\ICalc */
    protected $callCalc;
    /** @var \Magento\Framework\DB\Adapter\AdapterInterface */
    protected $conn;
    /** @var \Magento\Framework\App\ResourceConnection */
    protected $resource;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\BonusHybrid\Service\ICalc $callCalc,
        \Praxigento\BonusHybrid\Service\Calc\ISignupDebit $callBonusSignup,
        \Praxigento\BonusHybrid\Service\Calc\ICompressPhase1 $callBonusCompressPhase1
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
        $this->callBonusCompressPhase1 = $callBonusCompressPhase1;
    }

    protected function calcBonusCourtesy()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\Request\BonusCourtesy();
        $req->setCourtesyBonusPercent(\Praxigento\BonusHybrid\Defaults::COURTESY_BONUS_PERCENT);
        $resp = $this->callCalc->bonusCourtesy($req);
        $result = $resp->isSucceed();
        return $result;
    }

    protected function calcBonusInfinityDef()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\Request\BonusInfinity();
        $req->setScheme(\Praxigento\BonusHybrid\Defaults::SCHEMA_DEFAULT);
        $resp = $this->callCalc->bonusInfinity($req);
        $result = $resp->isSucceed();
        return $result;
    }

    protected function calcBonusInfinityEu()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\Request\BonusInfinity();
        $req->setScheme(\Praxigento\BonusHybrid\Defaults::SCHEMA_EU);
        $resp = $this->callCalc->bonusInfinity($req);
        $result = $resp->isSucceed();
        return $result;
    }

    protected function calcBonusOverrideDef()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\Request\BonusOverride();
        $req->setScheme(\Praxigento\BonusHybrid\Defaults::SCHEMA_DEFAULT);
        $resp = $this->callCalc->bonusOverride($req);
        $result = $resp->isSucceed();
        return $result;
    }

    protected function calcBonusOverrideEu()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\Request\BonusOverride();
        $req->setScheme(\Praxigento\BonusHybrid\Defaults::SCHEMA_EU);
        $resp = $this->callCalc->bonusOverride($req);
        $result = $resp->isSucceed();
        return $result;
    }

    protected function calcBonusPersonalDef()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\Request\BonusPersonal();
        $req->setScheme(\Praxigento\BonusHybrid\Defaults::SCHEMA_DEFAULT);
        $resp = $this->callCalc->bonusPersonal($req);
        $result = $resp->isSucceed();
        return $result;
    }

    protected function calcBonusPersonalEu()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\Request\BonusPersonal();
        $req->setScheme(\Praxigento\BonusHybrid\Defaults::SCHEMA_EU);
        $resp = $this->callCalc->bonusPersonal($req);
        $result = $resp->isSucceed();
        return $result;
    }

    protected function calcBonusTeamDef()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\Request\BonusTeam();
        $req->setScheme(\Praxigento\BonusHybrid\Defaults::SCHEMA_DEFAULT);
        $req->setCourtesyBonusPercent(\Praxigento\BonusHybrid\Defaults::COURTESY_BONUS_PERCENT);
        $resp = $this->callCalc->bonusTeam($req);
        $result = $resp->isSucceed();
        return $result;
    }

    protected function calcBonusTeamEu()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\Request\BonusTeam();
        $req->setScheme(\Praxigento\BonusHybrid\Defaults::SCHEMA_EU);
        $req->setTeamBonusPercent(\Praxigento\BonusHybrid\Defaults::TEAM_BONUS_EU_PERCENT);
        $resp = $this->callCalc->bonusTeam($req);
        $result = $resp->isSucceed();
        return $result;
    }

    protected function calcCompressOiDef()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\Request\CompressOi();
        $req->setScheme(\Praxigento\BonusHybrid\Defaults::SCHEMA_DEFAULT);
        $resp = $this->callCalc->compressOi($req);
        $result = $resp->isSucceed();
        return $result;
    }

    protected function calcCompressOiEu()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\Request\CompressOi();
        $req->setScheme(\Praxigento\BonusHybrid\Defaults::SCHEMA_EU);
        $resp = $this->callCalc->compressOi($req);
        $result = $resp->isSucceed();
        return $result;
    }

    protected function calcCompressPhase1()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\CompressPhase1\Request();
        $resp = $this->callBonusCompressPhase1->exec($req);
        $result = $resp->isSucceed();
        return $result;
    }

    protected function calcPvWriteOff()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\Request\PvWriteOff();
        $resp = $this->callCalc->pvWriteOff($req);
        $result = $resp->isSucceed();
        return $result;
    }

    protected function calcSignupDebit()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\SignupDebit\Request();
        $resp = $this->callBonusSignup->exec($req);
        $result = $resp->isSucceed();
        return $result;
    }

    protected function calcValueOv()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\Request\ValueOv();
        $resp = $this->callCalc->valueOv($req);
        $result = $resp->isSucceed();
        return $result;
    }

    protected function calcValueTv()
    {
        $req = new \Praxigento\BonusHybrid\Service\Calc\Request\ValueTv();
        $resp = $this->callCalc->valueTv($req);
        $result = $resp->isSucceed();
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
                $canContinue = $this->calcBonusPersonalDef();
            }
            if ($canContinue) {
                $output->writeln("<info>Personal bonus (DEFAULT) is calculated.<info>");
//                $canContinue = $this->calcBonusPersonalEu();
            }
            if ($canContinue) {
                $output->writeln("<info>Personal bonus (EU) is skipped.<info>");
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