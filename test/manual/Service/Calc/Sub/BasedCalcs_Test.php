<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Calc\Sub;

use Praxigento\BonusHybrid\Config as Cfg;


include_once(__DIR__ . '/../../../phpunit_bootstrap.php');

class BasedCalcs_ManualTest extends \Praxigento\Core\Test\BaseCase\Mockery {

    public function test_getCompressionBasedPeriodData() {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\BonusHybrid\Service\Period\Sub\BasedCalcs */
        $call = $obm->get('\Praxigento\BonusHybrid\Service\Period\Sub\BasedCalcs');
        $resp = new \Praxigento\BonusHybrid\Service\Period\Response\BasedOnCompression();
        $data = $call->getCompressionBasedPeriodData($resp, Cfg::CODE_TYPE_CALC_BONUS_PERSONAL_COMPRESSED);
        $this->assertNotNull($data);
    }

}