<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Repo\Query\SignupDebit;

class GetOrders
{
    public function exec(\Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders\Request $req)
    {
        $dateFrom = $req->dateFrom;
        $dateTo = $req->dateTo;
    }
}