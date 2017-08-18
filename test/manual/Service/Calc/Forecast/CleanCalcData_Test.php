<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast;

use Praxigento\BonusHybrid\Config as Cfg;

include_once(__DIR__ . '/../../../phpunit_bootstrap.php');

class CleanCalcData_Test
    extends \Praxigento\Core\Test\BaseCase\Manual
{

    public function test_exec()
    {
        $def = $this->manTrans->begin();
        /** @var  $obj \Praxigento\BonusHybrid\Service\Calc\Forecast\CleanCalcData */
        $obj = $this->manObj->get(\Praxigento\BonusHybrid\Service\Calc\Forecast\CleanCalcData::class);
        $ctx = new \Flancer32\Lib\Data();
        $ctx->set($obj::CTX_IN_CALC_TYPE_CODE, Cfg::CODE_TYPE_CALC_FORECAST_PLAIN);
        $obj->exec($ctx);
        $this->manTrans->rollback($def);
    }


}