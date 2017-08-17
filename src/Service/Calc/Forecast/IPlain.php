<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast;

interface IPlain
{
    /**
     * @param \Praxigento\BonusHybrid\Service\Calc\Forecast\Plain\Request $req
     * @return \Praxigento\BonusHybrid\Service\Calc\Forecast\Plain\Response
     */
    public function exec(\Praxigento\BonusHybrid\Service\Calc\Forecast\Plain\Request $req);
}