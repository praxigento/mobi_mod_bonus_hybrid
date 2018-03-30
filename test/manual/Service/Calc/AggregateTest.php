<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Test\Praxigento\BonusHybrid\Service\Calc;

use Praxigento\BonusHybrid\Service\Calc\Aggregate\Request as ARequest;
use Praxigento\BonusHybrid\Service\Calc\Aggregate\Response as AResponse;

include_once(__DIR__ . '/../../phpunit_bootstrap.php');

class AggregateTest
    extends \Praxigento\Core\Test\BaseCase\Manual
{


    public function test_exec()
    {
        $def = $this->manTrans->begin();
        /** @var  $serv \Praxigento\BonusHybrid\Service\Calc\Aggregate */
        $serv = $this->manObj->get(\Praxigento\BonusHybrid\Service\Calc\Aggregate::class);
        $req = new ARequest();
        $resp = $serv->exec($req);
        $this->assertTrue($resp instanceof AResponse);
        $this->manTrans->rollback($def);
    }

}