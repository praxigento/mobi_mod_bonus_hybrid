<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib\Service\Period;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\Core\Lib\Context;

include_once(__DIR__ . '/../../phpunit_bootstrap.php');

class Call_ManualTest extends \Praxigento\Core\Lib\Test\BaseTestCase {

    public function test_getForCompressedPersonalBonus() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\IPeriod */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\IPeriod');
        $request = new Request\GetForCompressedPersonalBonus();
        $response = $call->getForCompressedPersonalBonus($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_getForPersonalBonus() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\IPeriod */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\IPeriod');
        $request = new Request\GetForPersonalBonus();
        $response = $call->getForPersonalBonus($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_getForCompression() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\IPeriod */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\IPeriod');
        $request = new Request\GetForCompression();
        $response = $call->getForCompression($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_getForWriteOff() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\IPeriod */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\IPeriod');
        $request = new Request\GetForWriteOff();
        $response = $call->getForWriteOff($request);
        $this->assertTrue($response->isSucceed());
    }

    public function test_getForDependentCalc() {
        $obm = Context::instance()->getObjectManager();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\IPeriod */
        $call = $obm->get('Praxigento\Bonus\Hybrid\Lib\Service\IPeriod');
        $request = new Request\GetForDependentCalc();
        $request->setDependentCalcTypeCode(Cfg::CODE_TYPE_CALC_VALUE_TV);
        $request->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $response = $call->getForDependentCalc($request);
        $this->assertTrue($response->isSucceed());
    }
}