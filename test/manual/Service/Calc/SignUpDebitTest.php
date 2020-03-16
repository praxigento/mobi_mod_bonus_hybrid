<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Test\Praxigento\BonusHybrid\Service\Calc;


include_once(__DIR__ . '/../../phpunit_bootstrap.php');

class SignUpDebitTest
    extends \Praxigento\Core\Test\BaseCase\Manual
{


    public function test_exec()
    {
        $obm = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var  $proc \Praxigento\BonusHybrid\Service\Calc\SignUp\Debit */
        $proc = $obm->get(\Praxigento\BonusHybrid\Service\Calc\SignUp\Debit::class);
        $ctx = new \Praxigento\Core\Data();
        $proc->exec($ctx);
        $this->assertTrue($ctx->get($proc::CTX_OUT_SUCCESS));
    }

}
