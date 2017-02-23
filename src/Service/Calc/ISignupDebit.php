<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Calc;

interface ISignupDebit
{
    /**
     * @param \Praxigento\BonusHybrid\Service\Calc\SignupDebit\Request $req
     * @return \Praxigento\BonusHybrid\Service\Calc\SignupDebit\Response
     */
    public function exec(\Praxigento\BonusHybrid\Service\Calc\SignupDebit\Request $req);
}