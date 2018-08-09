<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\SignUpDebit\A;

use Praxigento\Accounting\Repo\Data\Transaction as Trans;
use Praxigento\BonusBase\Repo\Data\Log\Customers as LogCust;
use Praxigento\BonusBase\Repo\Data\Log\Opers as LogOpers;
use Praxigento\BonusBase\Repo\Data\Log\Sales as LogSales;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Registry\SignUpDebit as RegSignup;
use Praxigento\BonusHybrid\Service\Calc\SignUpDebit\A\Repo\Query\GetOrders as QGetOrders;

/**
 * Debit 100 PV from customer & add 34.00 AMNT to parent's bonus.
 */
class ProcessOrders
{
    const OPT_CALC_ID = 'calc_id';
    const OPT_DATE_APPLIED = 'date_applied';
    const OPT_ORDERS = 'orders';

    /**
     * Prefixes to map transactions to orders to log relations on operation post.
     * Should be 2 chars length.
     */
    const PREFIX_PV = 'pv';
    const PREFIX_BONUS_FATHER = 'wf';
    const PREFIX_BONUS_GRAND = 'wg';

    /** @var \Praxigento\BonusBase\Repo\Dao\Log\Customers */
    private $daoLogCust;
    /** @var \Praxigento\BonusBase\Repo\Dao\Log\Opers */
    private $daoLogOper;
    /** @var \Praxigento\BonusBase\Repo\Dao\Log\Sales */
    private $daoLogSale;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Registry\SignUpDebit */
    private $daoRegSignUpDebit;
    /** @var  \Praxigento\BonusHybrid\Api\Helper\Scheme */
    private $hlpScheme;
    /** @var \Praxigento\Accounting\Api\Service\Account\Get */
    private $servAccount;
    /** @var \Praxigento\Accounting\Api\Service\Operation */
    private $servOper;

    public function __construct(
        \Praxigento\BonusHybrid\Api\Helper\Scheme $hlpScheme,
        \Praxigento\BonusBase\Repo\Dao\Log\Customers $daoLogCust,
        \Praxigento\BonusBase\Repo\Dao\Log\Opers $daoLogOper,
        \Praxigento\BonusBase\Repo\Dao\Log\Sales $daoLogSale,
        \Praxigento\BonusHybrid\Repo\Dao\Registry\SignUpDebit $daoRegSignUpDebit,
        \Praxigento\Accounting\Api\Service\Account\Get $servAccount,
        \Praxigento\Accounting\Api\Service\Operation $servOper
    ) {
        $this->hlpScheme = $hlpScheme;
        $this->daoLogCust = $daoLogCust;
        $this->daoLogOper = $daoLogOper;
        $this->daoRegSignUpDebit = $daoRegSignUpDebit;
        $this->daoLogSale = $daoLogSale;
        $this->servAccount = $servAccount;
        $this->servOper = $servOper;
    }

    /**
     * @param array $orders
     * @param string $dateApplied
     * @param int $calcId
     * @throws \Exception
     */
    public function exec($orders, $dateApplied, $calcId)
    {
        /* get system accounts */
        $accPvSys = $this->getAccSys(Cfg::CODE_TYPE_ASSET_PV);
        $accBonusSys = $this->getAccSys(Cfg::CODE_TYPE_ASSET_BONUS);
        /* Create one operation for all transactions */
        $req = new \Praxigento\Accounting\Api\Service\Operation\Request();
        $req->setOperationTypeCode(Cfg::CODE_TYPE_OPER_BONUS_SIGNUP_DEBIT);
        $period = substr($dateApplied, 0, 7);
        $period = str_replace('-', '', $period);
        $note = "Sign Up Debit ($period)";
        $req->setOperationNote($note);
        $transRef = 'ref';
        $req->setAsTransRef($transRef);
        /* prepare transactions */
        $trans = [];
        foreach ($orders as $one) {
            $custId = $one[QGetOrders::A_CUST_ID];
            $parentId = $one[QGetOrders::A_PARENT_ID];
            $grandId = $one[QGetOrders::A_PARENT_GRAND_ID];
            $orderId = $one[QGetOrders::A_SALE_ID];
            $scheme = $this->hlpScheme->getSchemeByCustomer($one);
            /** Sign Up Debit bonus is applied for EU customers only */
            if ($scheme == Cfg::SCHEMA_EU) {
                /* prepare data for transactions */
                $accPvCust = $this->getAccCust(Cfg::CODE_TYPE_ASSET_PV, $custId);
                $accBonusParent = $this->getAccCust(Cfg::CODE_TYPE_ASSET_BONUS, $parentId);
                $accBonusGrand = $this->getAccCust(Cfg::CODE_TYPE_ASSET_BONUS, $grandId);
                $note = 'Sign Up Debit bonus for order #' . $orderId;
                /* add PV transaction */
                $tranPvOff = [
                    Trans::A_DEBIT_ACC_ID => $accPvCust,
                    Trans::A_CREDIT_ACC_ID => $accPvSys,
                    Trans::A_DATE_APPLIED => $dateApplied,
                    Trans::A_VALUE => Cfg::SIGNUP_DEBIT_PV,
                    Trans::A_NOTE => $note,
                    $transRef => self::PREFIX_PV . $orderId
                ];
                $trans[] = $tranPvOff;
                /* add BONUS transaction for "father" */
                $tranBonusFatherOn = [
                    Trans::A_DEBIT_ACC_ID => $accBonusSys,
                    Trans::A_CREDIT_ACC_ID => $accBonusParent,
                    Trans::A_DATE_APPLIED => $dateApplied,
                    Trans::A_VALUE => Cfg::SIGNUP_DEBIT_BONUS_FATHER,
                    Trans::A_NOTE => $note . ' (level 1)',
                    $transRef => self::PREFIX_BONUS_FATHER . $orderId
                ];
                $trans[] = $tranBonusFatherOn;
                /* add BONUS transaction for "grand" */
                $tranBonusFatherOn = [
                    Trans::A_DEBIT_ACC_ID => $accBonusSys,
                    Trans::A_CREDIT_ACC_ID => $accBonusGrand,
                    Trans::A_DATE_APPLIED => $dateApplied,
                    Trans::A_VALUE => Cfg::SIGNUP_DEBIT_BONUS_GRAND,
                    Trans::A_NOTE => $note . ' (level 2)',
                    $transRef => self::PREFIX_BONUS_GRAND . $orderId
                ];
                $trans[] = $tranBonusFatherOn;
            }
        }
        $req->setTransactions($trans);
        $resp = $this->servOper->exec($req);
        /* log transactions into Customer & Order logs */
        $ids = $resp->getTransactionsIds();
        $this->saveTransLogs($orders, $ids);
        /* log operation */
        $operId = $resp->getOperationId();
        $this->daoLogOper->create([
            LogOpers::A_CALC_ID => $calcId,
            LogOpers::A_OPER_ID => $operId
        ]);
        /* save customers into Sign Up Registry */
        foreach ($orders as $one) {
            $custId = $one[QGetOrders::A_CUST_ID];
            $orderId = $one[QGetOrders::A_SALE_ID];
            $this->daoRegSignUpDebit->create([
                RegSignup::A_CALC_REF => $calcId,
                RegSignup::A_CUST_REF => $custId,
                RegSignup::A_SALE_REF => $orderId
            ]);

        }
    }

    /**
     * @param string $assetTypeCode
     * @param int $custId
     * @return int
     */
    private function getAccCust($assetTypeCode, $custId)
    {
        $req = new \Praxigento\Accounting\Api\Service\Account\Get\Request();
        $req->setAssetTypeCode($assetTypeCode);
        $req->setCustomerId($custId);
        $resp = $this->servAccount->exec($req);
        $result = $resp->getId();
        return $result;
    }

    /**
     * Get system account ID by asset type ID.
     *
     * @param string $assetTypeCode
     * @return int
     */
    private function getAccSys($assetTypeCode)
    {
        $req = new \Praxigento\Accounting\Api\Service\Account\Get\Request();
        $req->setIsSystem(TRUE);
        $req->setAssetTypeCode($assetTypeCode);
        $resp = $this->servAccount->exec($req);
        $result = $resp->getId();
        return $result;
    }

    /**
     * Save transaction-customer & transaction-order relations to log.
     *
     * @param array $orders
     * @param array $transIds
     */
    private function saveTransLogs($orders, $transIds)
    {
        foreach ($transIds as $tranId => $one) {
            $pref = substr($one, 0, 2);
            $orderId = str_replace($pref, '', $one);
            if ($pref == self::PREFIX_PV) {
                /* log PV off & order itself*/
                $custId = $orders[$orderId][QGetOrders::A_CUST_ID];
                $this->daoLogCust->create([
                    LogCust::A_TRANS_ID => $tranId,
                    LogCust::A_CUSTOMER_ID => $custId
                ]);
                $this->daoLogSale->create([
                    LogSales::A_TRANS_ID => $tranId,
                    LogSales::A_SALE_ORDER_ID => $orderId
                ]);
            } elseif ($pref == self::PREFIX_BONUS_FATHER) {
                /* log BONUS Father On */
                $custId = $orders[$orderId][QGetOrders::A_PARENT_ID];
                $this->daoLogCust->create([
                    LogCust::A_TRANS_ID => $tranId,
                    LogCust::A_CUSTOMER_ID => $custId
                ]);
            } else {
                /* log BONUS Grand On */
                $custId = $orders[$orderId][QGetOrders::A_PARENT_GRAND_ID];
                $this->daoLogCust->create([
                    LogCust::A_TRANS_ID => $tranId,
                    LogCust::A_CUSTOMER_ID => $custId
                ]);
            }
        }
    }
}