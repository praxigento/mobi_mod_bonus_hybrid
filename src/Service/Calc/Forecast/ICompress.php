<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast;

interface ICompress
{
    /**
     * @param \Praxigento\BonusHybrid\Service\Calc\Forecast\Compress\Request $req
     * @return \Praxigento\BonusHybrid\Service\Calc\Forecast\Compress\Response
     */
    public function exec(\Praxigento\BonusHybrid\Service\Calc\Forecast\Compress\Request $req);
}