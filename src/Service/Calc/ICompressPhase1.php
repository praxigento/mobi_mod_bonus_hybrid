<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc;

/**
 * Phase I compression calculator.
 */
interface ICompressPhase1
{
    /**
     * @param \Praxigento\BonusHybrid\Service\Calc\CompressPhase1\Request $req
     * @return \Praxigento\BonusHybrid\Service\Calc\CompressPhase1\Response
     */
    public function exec(\Praxigento\BonusHybrid\Service\Calc\CompressPhase1\Request $req);
}