<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Tool\Def;


include_once(__DIR__ . '/../../phpunit_bootstrap.php');

class SchemeToTest extends Scheme
{
    public function getForcedPv($custId, $scheme, $pv)
    {
        return parent::getForcedPv($custId, $scheme, $pv);
    }

    public function getForcedSignupDebitCustIds()
    {
        $result = parent::getForcedSignupDebitCustIds();
        return $result;
    }


}

class Scheme_ManualTest
    extends \Praxigento\Core\Test\BaseCase\Mockery
{


    public function test_getForcedPv()
    {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\BonusHybrid\Tool\Def\SchemeToTest */
        $call = $obm->get(\Praxigento\BonusHybrid\Tool\Def\SchemeToTest::class);
        $res = $call->getForcedPv(12912, \Praxigento\BonusHybrid\Defaults::SCHEMA_EU, 23);
        $this->assertEquals(123, $res);
    }

    public function test_getForcedSignupDebitCustIds()
    {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\BonusHybrid\Tool\Def\SchemeToTest */
        $call = $obm->get(\Praxigento\BonusHybrid\Tool\Def\SchemeToTest::class);
        $res = $call->getForcedSignupDebitCustIds();
        $this->assertTrue(is_array($res));
    }

}