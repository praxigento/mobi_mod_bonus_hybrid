<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Calc;

use Praxigento\BonusHybrid\Defaults as Def;


include_once(__DIR__ . '/../../phpunit_bootstrap.php');

class Call_ManualTest extends \Praxigento\Core\Test\BaseCase\Mockery {

    const COURTESY_BONUS_PERCENT = 0.05;
    const TEAM_BONUS_PERCENT_EU = 0.05;

    public function test_bonusCourtesy() {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\BonusHybrid\Service\ICalc */
        $call = $obm->get('Praxigento\BonusHybrid\Service\ICalc');
        $request = new Request\BonusCourtesy();
        $request->setCourtesyBonusPercent(self::COURTESY_BONUS_PERCENT);
        $response = $call->bonusCourtesy($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_bonusInfinity_Def() {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\BonusHybrid\Service\ICalc */
        $call = $obm->get('Praxigento\BonusHybrid\Service\ICalc');
        $request = new Request\BonusInfinity();
        $request->setScheme(Def::SCHEMA_DEFAULT);
        $response = $call->bonusInfinity($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_bonusInfinity_Eu() {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\BonusHybrid\Service\ICalc */
        $call = $obm->get('Praxigento\BonusHybrid\Service\ICalc');
        $request = new Request\BonusInfinity();
        $request->setScheme(Def::SCHEMA_EU);
        $response = $call->bonusInfinity($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_bonusOverride_Def() {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\BonusHybrid\Service\ICalc */
        $call = $obm->get('Praxigento\BonusHybrid\Service\ICalc');
        $request = new Request\BonusOverride();
        $request->setScheme(Def::SCHEMA_DEFAULT);
        $response = $call->bonusOverride($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_bonusOverride_Eu() {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\BonusHybrid\Service\ICalc */
        $call = $obm->get('Praxigento\BonusHybrid\Service\ICalc');
        $request = new Request\BonusOverride();
        $request->setScheme(Def::SCHEMA_EU);
        $response = $call->bonusOverride($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_bonusPersonal_Def() {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\BonusHybrid\Service\ICalc */
        $call = $obm->get('Praxigento\BonusHybrid\Service\ICalc');
        $request = new Request\BonusPersonal();
        $request->setScheme(Def::SCHEMA_DEFAULT);
        $response = $call->bonusPersonal($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_bonusPersonal_Eu() {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\BonusHybrid\Service\ICalc */
        $call = $obm->get('Praxigento\BonusHybrid\Service\ICalc');
        $request = new Request\BonusPersonal();
        $request->setScheme(Def::SCHEMA_EU);
        $response = $call->bonusPersonal($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_bonusTeam_Def() {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\BonusHybrid\Service\ICalc */
        $call = $obm->get('Praxigento\BonusHybrid\Service\ICalc');
        $request = new Request\BonusTeam();
        $request->setScheme(Def::SCHEMA_DEFAULT);
        $request->setCourtesyBonusPercent(self::COURTESY_BONUS_PERCENT);
        $response = $call->bonusTeam($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_bonusTeam_Eu() {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\BonusHybrid\Service\ICalc */
        $call = $obm->get('Praxigento\BonusHybrid\Service\ICalc');
        $request = new Request\BonusTeam();
        $request->setScheme(Def::SCHEMA_EU);
        $request->setTeamBonusPercent(self::TEAM_BONUS_PERCENT_EU);
        $response = $call->bonusTeam($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_compressOi_Def() {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\BonusHybrid\Service\ICalc */
        $call = $obm->get('Praxigento\BonusHybrid\Service\ICalc');
        $request = new Request\CompressOi();
        $request->setScheme(Def::SCHEMA_DEFAULT);
        $response = $call->compressOi($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_compressOi_Eu() {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\BonusHybrid\Service\ICalc */
        $call = $obm->get('Praxigento\BonusHybrid\Service\ICalc');
        $request = new Request\CompressOi();
        $request->setScheme(Def::SCHEMA_EU);
        $response = $call->compressOi($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_compressPtc() {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\BonusHybrid\Service\ICalc */
        $call = $obm->get('Praxigento\BonusHybrid\Service\ICalc');
        $request = new Request\CompressPtc();
        $response = $call->compressPtc($request);
        $this->assertTrue($response->isSucceed());
    }


    public function test_pvWriteOff() {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\BonusHybrid\Service\ICalc */
        $call = $obm->get('Praxigento\BonusHybrid\Service\ICalc');
        $request = new Request\PvWriteOff();
        $response = $call->pvWriteOff($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_valueOv() {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\BonusHybrid\Service\ICalc */
        $call = $obm->get('Praxigento\BonusHybrid\Service\ICalc');
        $request = new Request\ValueOv();
        $response = $call->valueOv($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_valueTv() {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\BonusHybrid\Service\ICalc */
        $call = $obm->get('Praxigento\BonusHybrid\Service\ICalc');
        $request = new Request\ValueTv();
        $response = $call->valueTv($request);
        $this->assertTrue($response->isSucceed());
    }
}