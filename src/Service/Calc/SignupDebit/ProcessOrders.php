<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Calc\SignupDebit;

use Praxigento\BonusHybrid\Config as Cfg;

/**
 * Debit 100 PV from customer & add 34.00 AMNT to parent's wallet.
 */
class ProcessOrders
{

    const A_CUST_ID = \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders\Builder::A_CUST_ID;
    const A_PARENT_ID = \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders\Builder::A_PARENT_ID;
    const A_ORDER_ID = \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders\Builder::A_ORDER_ID;
    const A_PV = \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders\Builder::A_PV;

    /** @var \Praxigento\Accounting\Service\IOperation */
    protected $callOper;

    public function __construct(
        \Praxigento\Accounting\Service\IOperation $callOper
    ) {
        $this->callOper = $callOper;
    }

    /**
     * @param array $opts
     * @return array
     */
    public function do($opts)
    {
        /* Create one operation for all transations */
        $req = new \Praxigento\Accounting\Service\Operation\Request\Add();
        $req->setOperationTypeCode(Cfg::CODE_TYPE_CALC_BONUS_SIGNUP_DEBIT);
        // $req->setAsTransRef();
        /* prepare transactions */
        foreach ($opts as $one) {
            $custId = $one[self::A_CUST_ID];
            $parentId = $one[self::A_PARENT_ID];
            $orderId = $one[self::A_ORDER_ID];
            $pv = $one[self::A_PV];
//            $tran = [
//                Trans::ATTR_DEBIT_ACC_ID => $represAccId,
//                Trans::ATTR_CREDIT_ACC_ID => $accId,
//                Trans::ATTR_DATE_APPLIED => $dateApplied,
//                Trans::ATTR_VALUE => $value
//            ];
        }
        $resp = $this->callOper->add($req);
    }
}