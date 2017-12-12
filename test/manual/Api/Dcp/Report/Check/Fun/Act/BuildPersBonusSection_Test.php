<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc;

use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Fun\Act\BuildPersBonusSection as Act;

include_once(__DIR__ . '/../../../../../../phpunit_bootstrap.php');

class BuildPersBonusSection_Test
    extends \Praxigento\Core\Test\BaseCase\Manual
{


    public function test_exec()
    {
        /** @var  $obj Act */
        $obj = $this->manObj->get(Act::class);
        $req = new Act\Data\Request();
        $req->setCustomerId(611);
        $req->setPeriod('201706');
        $resp = $obj->exec($req);
        $this->assertNotNull($resp->getSectionData());
    }

}