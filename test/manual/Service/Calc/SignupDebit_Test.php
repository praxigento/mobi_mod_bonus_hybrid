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
        /** @var  $proc \Praxigento\BonusHybrid\Service\Calc\ISignupDebit */
        $proc = $obm->get(\Praxigento\BonusHybrid\Service\Calc\ISignupDebit::class);
        $ctx = new \Praxigento\Core\Data();
        $proc->exec($ctx);
        $this->assertTrue($ctx->get($proc::CTX_OUT_SUCCESS));
    }

}