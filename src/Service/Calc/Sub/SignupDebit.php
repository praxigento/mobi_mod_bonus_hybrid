<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Calc\Sub;

class SignupDebit
{
    /** @var \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders */
    protected $repoQueryGetOrders;

    public function __construct(
        \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders $repoQueryGetOrders
    ) {
        $this->repoQueryGetOrders = $repoQueryGetOrders;
    }

    public function do($opts)
    {
        $req = new \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders\Request();
        $req->dateFrom = '';
        $req->dateTo = '';
        $this->repoQueryGetOrders->exec($req);
    }
}