<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Test\Praxigento\BonusHybrid\Service\Calc\Forecast;

include_once(__DIR__ . '/../../../phpunit_bootstrap.php');

class CompressTest
    extends \Praxigento\Core\Test\BaseCase\Manual
{

    public function test_exec()
    {
        $def = $this->manTrans->begin();
        /** @var  $proc \Praxigento\BonusHybrid\Service\Calc\Forecast\Compress */
        $proc = $this->manObj->get(\Praxigento\BonusHybrid\Service\Calc\Forecast\ICompress::class);
        $ctx = new \Praxigento\Core\Data();
        $proc->exec($ctx);
        $this->manTrans->commit($def);
    }


}