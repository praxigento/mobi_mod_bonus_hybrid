<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Inactive;

include_once(__DIR__ . '/../../../phpunit_bootstrap.php');

class Collect_Test
    extends \Praxigento\Core\Test\BaseCase\Manual
{

    public function test_exec()
    {
        $def = $this->manTrans->begin();
        /** @var  $proc \Praxigento\BonusHybrid\Service\Calc\Inactive\Collect */
        $proc = $this->manObj->get(\Praxigento\BonusHybrid\Service\Calc\Inactive\Collect::class);
        $ctx = new \Praxigento\Core\Data();
        $period = '20170430';
        $ctx->set(\Praxigento\BonusHybrid\Service\Calc\Inactive\Collect::CTX_IN_PERIOD_END, $period);
        $proc->exec($ctx);
        $this->manTrans->rollback($def);
        $this->assertTrue(true); // to prevent console warnings
    }


}