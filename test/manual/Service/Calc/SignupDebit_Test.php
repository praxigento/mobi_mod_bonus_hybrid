<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Calc;


include_once(__DIR__ . '/../../phpunit_bootstrap.php');

class SignupDebit_ManualTest extends \Praxigento\Core\Test\BaseCase\Mockery
{


    public function test_exec()
    {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $call \Praxigento\BonusHybrid\Service\Calc\ISignupDebit */
        $call = $obm->get(\Praxigento\BonusHybrid\Service\Calc\ISignupDebit::class);
        $req = new \Praxigento\BonusHybrid\Service\Calc\SignupDebit\Request();
        $resp = $call->exec($req);
        $this->assertTrue($resp->isSucceed());
    }

}