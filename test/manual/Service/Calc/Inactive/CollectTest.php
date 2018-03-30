<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Test\Praxigento\BonusHybrid\Service\Calc\Inactive;

include_once(__DIR__ . '/../../../phpunit_bootstrap.php');

class CollectTest
    extends \Praxigento\Core\Test\BaseCase\Manual
{

    public function test_exec()
    {
        $def = $this->manTrans->begin();
        /** @var  $proc \Praxigento\BonusHybrid\Service\Calc\Inactive\Collect */
        $proc = $this->manObj->get(\Praxigento\BonusHybrid\Service\Calc\Inactive\Collect::class);
        $ctx = new \Praxigento\Core\Data();
        $proc->exec($ctx);
        $this->manTrans->rollback($def);
        $this->assertTrue(true); // to prevent console warnings
    }


}