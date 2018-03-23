<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Calc\SignupDebit;

use Praxigento\Accounting\Repo\Data\Transaction as Trans;
use Praxigento\BonusBase\Repo\Data\Log\Customers as LogCust;
use Praxigento\BonusBase\Repo\Data\Log\Opers as LogOpers;
use Praxigento\BonusBase\Repo\Data\Log\Sales as LogSales;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Registry\SignupDebit as RegSignup;
use Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders\Builder as Query;

/**
 * Debit 100 PV from customer & add 34.00 AMNT to parent's wallet.
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
    const PREFIX_WALLET_FATHER = 'wf';
    const PREFIX_WALLET_GRAND = 'wg';

    /** @var \Praxigento\Accounting\Api\Service\Account\Get */
    private $callAccount;
    /** @var \Praxigento\Accounting\Api\Service\Operation */
    private $callOper;
    /** @var  \Praxigento\BonusHybrid\Helper\IScheme */
    private $hlpScheme;
    /** @var \Praxigento\BonusBase\Repo\Dao\Log\Customers */
    private $daoLogCust;
    /** @var \Praxigento\BonusBase\Repo\Dao\Log\Opers */
    private $daoLogOper;
    /** @var \Praxigento\BonusBase\Repo\Dao\Log\Sales */
    private $daoLogSale;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Registry\SignupDebit */
    private $daoRegSignupDebit;

    public function __construct(
        \Praxigento\BonusHybrid\Helper\IScheme $hlpScheme,
        \Praxigento\BonusBase\Repo\Dao\Log\Customers $daoLogCust,
        \Praxigento\BonusBase\Repo\Dao\Log\Opers $daoLogOper,
        \Praxigento\BonusBase\Repo\Dao\Log\Sales $daoLogSale,
        \Praxigento\BonusHybrid\Repo\Dao\Registry\SignupDebit $daoRegSignupDebit,
        \Praxigento\Accounting\Api\Service\Account\Get $callAccount,
        \Praxigento\Accounting\Api\Service\Operation $callOper
    )
    {
        $this->hlpScheme = $hlpScheme;
        $this->daoLogCust = $daoLogCust;
        $this->daoLogOper = $daoLogOper;
        $this->daoRegSignupDebit = $daoRegSignupDebit;
        $this->daoLogSale = $daoLogSale;
        $this->callAccount = $callAccount;
        $this->callOper = $callOper;
    }

    /**
     * @param array $opts
     * @return array
     */
    public function exec($opts)
    {
        $orders = $opts[self::OPT_ORDERS];
        $dateApplied = $opts[self::OPT_DATE_APPLIED];
        $calcId = $opts[self::OPT_CALC_ID];
        /* get system accounts */
        $accPvSys = $this->getAccSys(Cfg::CODE_TYPE_ASSET_PV);
        $accWalletSys = $this->getAccSys(Cfg::CODE_TYPE_ASSET_WALLET);
        /* Create one operation for all transactions */
        $req = new \Praxigento\Accounting\Api\Service\Operation\Request();
        $req->setOperationTypeCode(Cfg::CODE_TYPE_OPER_BONUS_SIGNUP_DEBIT);
        $transRef = 'ref';
        $req->setAsTransRef($transRef);
        /* prepare transactions */
        $trans = [];
        foreach ($orders as $one) {
            $custId = $one[Query::A_CUST_ID];
            $parentId = $one[Query::A_PARENT_ID];
            $grandId = $one[Query::A_PARENT_GRAND_ID];
            $orderId = $one[Query::A_ORDER_ID];
            $scheme = $this->hlpScheme->getSchemeByCustomer($one);
            if ($scheme == Cfg::SCHEMA_EU) {
                /* prepare data for transactions */
                $accPvCust = $this->getAccCust(Cfg::CODE_TYPE_ASSET_PV, $custId);
                $accWalletParent = $this->getAccCust(Cfg::CODE_TYPE_ASSET_WALLET, $parentId);
                $accWalletGrand = $this->getAccCust(Cfg::CODE_TYPE_ASSET_WALLET, $grandId);
                /* add PV transaction */
                $tranPvOff = [
                    Trans::A_DEBIT_ACC_ID => $accPvCust,
                    Trans::A_CREDIT_ACC_ID => $accPvSys,
                    Trans::A_DATE_APPLIED => $dateApplied,
                    Trans::A_VALUE => Cfg::SIGNUP_DEBIT_PV,
                    $transRef => self::PREFIX_PV . $orderId
                ];
                $trans[] = $tranPvOff;
                /* add Wallet transaction for "father" */
                $tranWalletFatherOn = [
                    Trans::A_DEBIT_ACC_ID => $accWalletSys,
                    Trans::A_CREDIT_ACC_ID => $accWalletParent,
                    Trans::A_DATE_APPLIED => $dateApplied,
                    Trans::A_VALUE => Cfg::SIGNUP_DEBIT_WALLET_FATHER,
                    $transRef => self::PREFIX_WALLET_FATHER . $orderId
                ];
                $trans[] = $tranWalletFatherOn;
                /* add Wallet transaction for "grand" */
                $tranWalletFatherOn = [
                    Trans::A_DEBIT_ACC_ID => $accWalletSys,
                    Trans::A_CREDIT_ACC_ID => $accWalletGrand,
                    Trans::A_DATE_APPLIED => $dateApplied,
                    Trans::A_VALUE => Cfg::SIGNUP_DEBIT_WALLET_GRAND,
                    $transRef => self::PREFIX_WALLET_GRAND . $orderId
                ];
                $trans[] = $tranWalletFatherOn;
            }
        }
        $req->setTransactions($trans);
        $resp = $this->callOper->exec($req);
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
            $custId = $one[Query::A_CUST_ID];
            $orderId = $one[Query::A_ORDER_ID];
            $this->daoRegSignupDebit->create([
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
        $resp = $this->callAccount->exec($req);
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
        $resp = $this->callAccount->exec($req);
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
                $custId = $orders[$orderId][Query::A_CUST_ID];
                $this->daoLogCust->create([
                    LogCust::A_TRANS_ID => $tranId,
                    LogCust::A_CUSTOMER_ID => $custId
                ]);
                $this->daoLogSale->create([
                    LogSales::A_TRANS_ID => $tranId,
                    LogSales::A_SALE_ORDER_ID => $orderId
                ]);
            } elseif ($pref == self::PREFIX_WALLET_FATHER) {
                /* log Wallet Father On */
                $custId = $orders[$orderId][Query::A_PARENT_ID];
                $this->daoLogCust->create([
                    LogCust::A_TRANS_ID => $tranId,
                    LogCust::A_CUSTOMER_ID => $custId
                ]);
            } else {
                /* log Wallet Grand On */
                $custId = $orders[$orderId][Query::A_PARENT_GRAND_ID];
                $this->daoLogCust->create([
                    LogCust::A_TRANS_ID => $tranId,
                    LogCust::A_CUSTOMER_ID => $custId
                ]);
            }
        }
    }
}