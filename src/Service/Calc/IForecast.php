<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc;

interface IForecast
{
    /**
     * @param \Praxigento\BonusHybrid\Service\Calc\Forecast\Request $req
     * @return \Praxigento\BonusHybrid\Service\Calc\Forecast\Response
     */
    public function exec(\Praxigento\BonusHybrid\Service\Calc\Forecast\Request $req);
}