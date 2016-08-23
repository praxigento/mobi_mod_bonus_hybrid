<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib\Service\Period;

use Praxigento\BonusHybrid\Config as Cfg;


include_once(__DIR__ . '/../../phpunit_bootstrap.php');

class Call_ManualTest extends \Praxigento\Core\Test\BaseCase\Mockery {

    public function test_getForCompressedPersonalBonus() {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\IPeriod */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\IPeriod');
        $request = new Request\GetForCompressedPersonalBonus();
        $response = $call->getForCompressedPersonalBonus($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_getForPersonalBonus() {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\IPeriod */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\IPeriod');
        $request = new Request\GetForPersonalBonus();
        $response = $call->getForPersonalBonus($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_getForCompression() {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\IPeriod */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\IPeriod');
        $request = new Request\GetForCompression();
        $response = $call->getForCompression($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_getForWriteOff() {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\IPeriod */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\IPeriod');
        $request = new Request\GetForWriteOff();
        $response = $call->getForWriteOff($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_getForDependentCalc() {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\IPeriod */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\IPeriod');
        $request = new Request\GetForDependentCalc();
        $request->setDependentCalcTypeCode(Cfg::CODE_TYPE_CALC_VALUE_TV);
        $request->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $response = $call->getForDependentCalc($request);
        $this->assertTrue($response->isSucceed());
    }
}