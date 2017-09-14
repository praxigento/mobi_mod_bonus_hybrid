<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast\Plain;

use Praxigento\BonusHybrid\Config as Cfg;

include_once(__DIR__ . '/../../../../phpunit_bootstrap.php');

class CleanCalcData_Test
    extends \Praxigento\Core\Test\BaseCase\Manual
{

    public function test_exec()
    {
        $def = $this->manTrans->begin();
        /** @var  $sub \Praxigento\BonusHybrid\Service\Calc\Forecast\CleanCalcData */
        $sub = $this->manObj->get(\Praxigento\BonusHybrid\Service\Calc\Forecast\CleanCalcData::class);
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($sub::CTX_IN_CALC_TYPE_CODE, Cfg::CODE_TYPE_CALC_FORECAST_PLAIN);
        $sub->exec($ctx);
        $this->manTrans->rollback($def);
    }


}