<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib\Service\Calc;

use Praxigento\Bonus\Hybrid\Lib\Defaults as Def;
use Praxigento\Core\Lib\Context;

include_once(__DIR__ . '/../../phpunit_bootstrap.php');

class Call_ManualTest extends \Praxigento\Core\Lib\Test\BaseTestCase {

    const COURTESY_BONUS_PERCENT = 0.05;
    const TEAM_BONUS_PERCENT_EU = 0.05;

    public function test_bonusCourtesy() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\ICalc */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\ICalc');
        $request = new Request\BonusCourtesy();
        $request->setCourtesyBonusPercent(self::COURTESY_BONUS_PERCENT);
        $response = $call->bonusCourtesy($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_bonusInfinity_Def() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\ICalc */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\ICalc');
        $request = new Request\BonusInfinity();
        $request->setScheme(Def::SCHEMA_DEFAULT);
        $response = $call->bonusInfinity($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_bonusInfinity_Eu() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\ICalc */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\ICalc');
        $request = new Request\BonusInfinity();
        $request->setScheme(Def::SCHEMA_EU);
        $response = $call->bonusInfinity($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_bonusOverride_Def() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\ICalc */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\ICalc');
        $request = new Request\BonusOverride();
        $request->setScheme(Def::SCHEMA_DEFAULT);
        $response = $call->bonusOverride($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_bonusOverride_Eu() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\ICalc */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\ICalc');
        $request = new Request\BonusOverride();
        $request->setScheme(Def::SCHEMA_EU);
        $response = $call->bonusOverride($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_bonusPersonal_Def() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\ICalc */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\ICalc');
        $request = new Request\BonusPersonal();
        $request->setScheme(Def::SCHEMA_DEFAULT);
        $response = $call->bonusPersonal($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_bonusPersonal_Eu() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\ICalc */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\ICalc');
        $request = new Request\BonusPersonal();
        $request->setScheme(Def::SCHEMA_EU);
        $response = $call->bonusPersonal($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_bonusTeam_Def() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\ICalc */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\ICalc');
        $request = new Request\BonusTeam();
        $request->setScheme(Def::SCHEMA_DEFAULT);
        $request->setCourtesyBonusPercent(self::COURTESY_BONUS_PERCENT);
        $response = $call->bonusTeam($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_bonusTeam_Eu() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\ICalc */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\ICalc');
        $request = new Request\BonusTeam();
        $request->setScheme(Def::SCHEMA_EU);
        $request->setTeamBonusPercent(self::TEAM_BONUS_PERCENT_EU);
        $response = $call->bonusTeam($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_compressOi_Def() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\ICalc */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\ICalc');
        $request = new Request\CompressOi();
        $request->setScheme(Def::SCHEMA_DEFAULT);
        $response = $call->compressOi($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_compressOi_Eu() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\ICalc */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\ICalc');
        $request = new Request\CompressOi();
        $request->setScheme(Def::SCHEMA_EU);
        $response = $call->compressOi($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_compressPtc() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\ICalc */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\ICalc');
        $request = new Request\CompressPtc();
        $response = $call->compressPtc($request);
        $this->assertTrue($response->isSucceed());
    }


    public function test_pvWriteOff() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\ICalc */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\ICalc');
        $request = new Request\PvWriteOff();
        $response = $call->pvWriteOff($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_valueOv() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\ICalc */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\ICalc');
        $request = new Request\ValueOv();
        $response = $call->valueOv($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_valueTv() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\ICalc */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\ICalc');
        $request = new Request\ValueTv();
        $response = $call->valueTv($request);
        $this->assertTrue($response->isSucceed());
    }
}