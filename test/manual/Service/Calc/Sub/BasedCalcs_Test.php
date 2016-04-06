<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib\Service\Calc\Sub;

use Praxigento\BonusHybrid\Config as Cfg;


include_once(__DIR__ . '/../../../phpunit_bootstrap.php');

class BasedCalcs_ManualTest extends \Praxigento\Core\Lib\Test\BaseMockeryCase {

    public function test_getCompressionBasedPeriodData() {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub\BasedCalcs */
        $call = $obm->get('\Praxigento\Bonus\Hybrid\Lib\Service\Period\Sub\BasedCalcs');
        $resp = new \Praxigento\Bonus\Hybrid\Lib\Service\Period\Response\BasedOnCompression();
        $data = $call->getCompressionBasedPeriodData($resp, Cfg::CODE_TYPE_CALC_BONUS_PERSONAL_COMPRESSED);
        $this->assertNotNull($data);
    }

}