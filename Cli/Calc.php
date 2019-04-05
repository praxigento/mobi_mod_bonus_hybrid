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
            'prxgt:bonus:calc',
            'Calculate hybrid bonus (run all calcs one-by-one).'
        );
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

    protected function process(\Symfony\Component\Console\Input\InputInterface $input)
    {
        $canContinue = $this->calcSignUpDebit();
        if ($canContinue) {
            $this->logInfo("'Sign Up Volume Debit' calculation is completed.");
            $canContinue = $this->calcPvWriteOff();
        }
        if ($canContinue) {
            $this->logInfo("'PV Write Off' calculation is completed.");
            $canContinue = $this->calcCompressPhase1();
        }
        if ($canContinue) {
            $this->logInfo("'Phase I' compression is completed.");
            $canContinue = $this->calcBonusPersonal();
        }
        if ($canContinue) {
            $this->logInfo("Personal bonus is calculated.");
            $canContinue = $this->calcValueTv();
        }
        if ($canContinue) {
            $this->logInfo("TV are calculated.");
            $canContinue = $this->calcBonusTeamDef();
        }
        if ($canContinue) {
            $this->logInfo("Team bonus (DEFAULT) is calculated.");
            $canContinue = $this->calcBonusTeamEu();
        }
        if ($canContinue) {
            $this->logInfo("Team bonus (EU) is calculated.");
            $canContinue = $this->calcBonusCourtesy();
        }
        if ($canContinue) {
            $this->logInfo("Courtesy bonus is calculated.");
            $canContinue = $this->calcValueOv();
        }
        if ($canContinue) {
            $this->logInfo("OV are calculated.");
            $canContinue = $this->calcCompressPhase2(Cfg::SCHEMA_DEFAULT);
        }
        if ($canContinue) {
            $this->logInfo("Phase II compression (DEFAULT) is completed.");
            $canContinue = $this->calcCompressPhase2(Cfg::SCHEMA_EU);
        }
        if ($canContinue) {
            $this->logInfo("Phase II compression (EU) is completed.");
            $canContinue = $this->calcBonusOverride(Cfg::SCHEMA_DEFAULT);
        }
        if ($canContinue) {
            $this->logInfo("Override bonus (DEFAULT) is calculated.");
            $canContinue = $this->calcBonusOverride(Cfg::SCHEMA_EU);
        }
        if ($canContinue) {
            $this->logInfo("Override bonus (EU) is calculated.");
            $canContinue = $this->calcBonusInfinity(Cfg::SCHEMA_DEFAULT);
        }
        if ($canContinue) {
            $this->logInfo("Infinity bonus (DEFAULT) is calculated.");
            $canContinue = $this->calcBonusInfinity(Cfg::SCHEMA_EU);
        }
        if ($canContinue) {
            $this->logInfo("Infinity bonus (EU) is calculated.");
            $canContinue = $this->calcAggregate();
        }
        if ($canContinue) {
            $this->logInfo("Bonus aggregation is calculated.");
        } else {
            throw new \Exception("Something goes wrong in bonus calculation. Rollback.");
        }
    }

}