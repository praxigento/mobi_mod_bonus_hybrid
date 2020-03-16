<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\SignUp\Debit\A;

use Praxigento\Accounting\Repo\Data\Transaction as Trans;
use Praxigento\BonusBase\Repo\Data\Log\Customers as LogCust;
use Praxigento\BonusBase\Repo\Data\Log\Opers as LogOpers;
use Praxigento\BonusBase\Repo\Data\Log\Sales as LogSales;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Registry\SignUpDebit as RegSignup;
use Praxigento\BonusHybrid\Service\Calc\SignUp\Debit\A\Repo\Query\GetOrders as QGetOrders;

/**
 * Debit 100 PV from customer's volume.
 */
class ProcessDebits
{
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
    /** @var \Praxigento\Accounting\Api\Service\Operation\Create */
    private $servOper;

    public function __construct(
        \Praxigento\BonusHybrid\Api\Helper\Scheme $hlpScheme,
        \Praxigento\BonusBase\Repo\Dao\Log\Customers $daoLogCust,
        \Praxigento\BonusBase\Repo\Dao\Log\Opers $daoLogOper,
        \Praxigento\BonusBase\Repo\Dao\Log\Sales $daoLogSale,
        \Praxigento\BonusHybrid\Repo\Dao\Registry\SignUpDebit $daoRegSignUpDebit,
        \Praxigento\Accounting\Api\Service\Account\Get $servAccount,
        \Praxigento\Accounting\Api\Service\Operation\Create $servOper
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
        /* Create one operation for all transactions */
        $req = new \Praxigento\Accounting\Api\Service\Operation\Create\Request();
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
            $saleId = $one[QGetOrders::A_SALE_ID];
            $saleIncId = $one[QGetOrders::A_SALE_INC_ID];
            $scheme = $this->hlpScheme->getSchemeByCustomer($one);
            /** Sign Up Debit bonus is applied for EU customers only */
            if ($scheme == Cfg::SCHEMA_EU) {
                /* prepare data for transactions */
                $accPvCust = $this->getAccCust(Cfg::CODE_TYPE_ASSET_PV, $custId);
                $note = "Sign Up PV Debit for order #$saleIncId";
                /* add PV transaction */
                $tranPvOff = [
                    Trans::A_DEBIT_ACC_ID => $accPvCust,
                    Trans::A_CREDIT_ACC_ID => $accPvSys,
                    Trans::A_DATE_APPLIED => $dateApplied,
                    Trans::A_VALUE => Cfg::SIGNUP_DEBIT_PV,
                    Trans::A_NOTE => $note,
                    $transRef => $saleId
                ];
                $trans[] = $tranPvOff;
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
            $saleId = $one[QGetOrders::A_SALE_ID];
            $this->daoRegSignUpDebit->create([
                RegSignup::A_CALC_REF => $calcId,
                RegSignup::A_CUST_REF => $custId,
                RegSignup::A_SALE_REF => $saleId
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
        $req->setIsSystem(true);
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
        /* re-map orders from with saleId key */
        $bySaleId = [];
        foreach ($orders as $custId => $order) {
            $saleId = $order[QGetOrders::A_SALE_ID];
            $bySaleId[$saleId] = $order;
        }
        foreach ($transIds as $tranId => $saleId) {
            /* log PV debits & order itself*/
            $custId = $bySaleId[$saleId][QGetOrders::A_CUST_ID];
            $this->daoLogCust->create([
                LogCust::A_TRANS_ID => $tranId,
                LogCust::A_CUSTOMER_ID => $custId
            ]);
            $this->daoLogSale->create([
                LogSales::A_TRANS_ID => $tranId,
                LogSales::A_SALE_ORDER_ID => $saleId
            ]);
        }
    }
}
