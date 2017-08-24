<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Calc\SignupDebit;

use Praxigento\Accounting\Data\Entity\Transaction as Trans;
use Praxigento\BonusBase\Data\Entity\Log\Customers as LogCust;
use Praxigento\BonusBase\Data\Entity\Log\Opers as LogOpers;
use Praxigento\BonusBase\Data\Entity\Log\Sales as LogSales;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Defaults as Def;
use Praxigento\BonusHybrid\Repo\Data\Entity\Registry\SignupDebit as RegSignup;
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

    /** @var \Praxigento\Accounting\Service\IAccount */
    protected $callAccount;
    /** @var \Praxigento\Accounting\Service\IOperation */
    protected $callOper;
    /** @var \Praxigento\BonusBase\Repo\Entity\Log\Customers */
    protected $repoLogCust;
    /** @var \Praxigento\BonusBase\Repo\Entity\Log\Opers */
    protected $repoLogOper;
    /** @var \Praxigento\BonusBase\Repo\Entity\Log\Sales */
    protected $repoLogSale;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Registry\SignupDebit */
    protected $repoRegSignupDebit;
    /** @var  \Praxigento\BonusHybrid\Tool\IScheme */
    protected $toolScheme;

    public function __construct(
        \Praxigento\BonusHybrid\Tool\IScheme $toolScheme,
        \Praxigento\BonusBase\Repo\Entity\Log\Customers $repoLogCust,
        \Praxigento\BonusBase\Repo\Entity\Log\Opers $repoLogOper,
        \Praxigento\BonusBase\Repo\Entity\Log\Sales $repoLogSale,
        \Praxigento\BonusHybrid\Repo\Entity\Registry\SignupDebit $repoRegSignupDebit,
        \Praxigento\Accounting\Service\IAccount $callAccount,
        \Praxigento\Accounting\Service\IOperation $callOper
    )
    {
        $this->toolScheme = $toolScheme;
        $this->repoLogCust = $repoLogCust;
        $this->repoLogOper = $repoLogOper;
        $this->repoRegSignupDebit = $repoRegSignupDebit;
        $this->repoLogSale = $repoLogSale;
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
        /* get representatives accounts */
        $accPvRepres = $this->getAccRepres(Cfg::CODE_TYPE_ASSET_PV);
        $accWalletRepres = $this->getAccRepres(Cfg::CODE_TYPE_ASSET_WALLET_ACTIVE);
        /* Create one operation for all transactions */
        $req = new \Praxigento\Accounting\Service\Operation\Request\Add();
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
            $scheme = $this->toolScheme->getSchemeByCustomer($one);
            if ($scheme == Def::SCHEMA_EU) {
                /* prepare data for transactions */
                $accPvCust = $this->getAccCust(Cfg::CODE_TYPE_ASSET_PV, $custId);
                $accWalletParent = $this->getAccCust(Cfg::CODE_TYPE_ASSET_WALLET_ACTIVE, $parentId);
                $accWalletGrand = $this->getAccCust(Cfg::CODE_TYPE_ASSET_WALLET_ACTIVE, $grandId);
                /* add PV transaction */
                $tranPvOff = [
                    Trans::ATTR_DEBIT_ACC_ID => $accPvCust,
                    Trans::ATTR_CREDIT_ACC_ID => $accPvRepres,
                    Trans::ATTR_DATE_APPLIED => $dateApplied,
                    Trans::ATTR_VALUE => Def::SIGNUP_DEBIT_PV,
                    $transRef => self::PREFIX_PV . $orderId
                ];
                $trans[] = $tranPvOff;
                /* add Wallet transaction for "father" */
                $tranWalletFatherOn = [
                    Trans::ATTR_DEBIT_ACC_ID => $accWalletRepres,
                    Trans::ATTR_CREDIT_ACC_ID => $accWalletParent,
                    Trans::ATTR_DATE_APPLIED => $dateApplied,
                    Trans::ATTR_VALUE => Def::SIGNUP_DEBIT_WALLET_FATHER,
                    $transRef => self::PREFIX_WALLET_FATHER . $orderId
                ];
                $trans[] = $tranWalletFatherOn;
                /* add Wallet transaction for "grand" */
                $tranWalletFatherOn = [
                    Trans::ATTR_DEBIT_ACC_ID => $accWalletRepres,
                    Trans::ATTR_CREDIT_ACC_ID => $accWalletGrand,
                    Trans::ATTR_DATE_APPLIED => $dateApplied,
                    Trans::ATTR_VALUE => Def::SIGNUP_DEBIT_WALLET_GRAND,
                    $transRef => self::PREFIX_WALLET_GRAND . $orderId
                ];
                $trans[] = $tranWalletFatherOn;
            }
        }
        $req->setTransactions($trans);
        $resp = $this->callOper->add($req);
        /* log transactions into Customer & Order logs */
        $ids = $resp->getTransactionsIds();
        $this->saveTransLogs($orders, $ids);
        /* log operation */
        $operId = $resp->getOperationId();
        $this->repoLogOper->create([
            LogOpers::ATTR_CALC_ID => $calcId,
            LogOpers::ATTR_OPER_ID => $operId
        ]);
        /* save customers into Sign Up Registry */
        foreach ($orders as $one) {
            $custId = $one[Query::A_CUST_ID];
            $orderId = $one[Query::A_ORDER_ID];
            $this->repoRegSignupDebit->create([
                RegSignup::ATTR_CALC_REF => $calcId,
                RegSignup::ATTR_CUST_REF => $custId,
                RegSignup::ATTR_SALE_REF => $orderId
            ]);

        }
    }

    /**
     * @param string $assetTypeCode
     * @param int $custId
     * @return int
     */
    protected function getAccCust($assetTypeCode, $custId)
    {
        $req = new \Praxigento\Accounting\Service\Account\Request\Get();
        $req->setAssetTypeCode($assetTypeCode);
        $req->setCustomerId($custId);
        $req->setCreateNewAccountIfMissed(true);
        $resp = $this->callAccount->get($req);
        $result = $resp->getId();
        return $result;
    }

    /**
     * Get representative account ID by asset type ID.
     *
     * @param string $assetTypeCode
     * @return int
     */
    protected function getAccRepres($assetTypeCode)
    {
        $req = new \Praxigento\Accounting\Service\Account\Request\GetRepresentative();
        $req->setAssetTypeCode($assetTypeCode);
        $resp = $this->callAccount->getRepresentative($req);
        $result = $resp->getId();
        return $result;
    }

    /**
     * Save transaction-customer & transaction-order relations to log.
     *
     * @param array $orders
     * @param array $transIds
     */
    protected function saveTransLogs($orders, $transIds)
    {
        foreach ($transIds as $tranId => $one) {
            $pref = substr($one, 0, 2);
            $orderId = str_replace($pref, '', $one);
            if ($pref == self::PREFIX_PV) {
                /* log PV off & order itself*/
                $custId = $orders[$orderId][Query::A_CUST_ID];
                $this->repoLogCust->create([
                    LogCust::ATTR_TRANS_ID => $tranId,
                    LogCust::ATTR_CUSTOMER_ID => $custId
                ]);
                $this->repoLogSale->create([
                    LogSales::ATTR_TRANS_ID => $tranId,
                    LogSales::ATTR_SALE_ORDER_ID => $orderId
                ]);
            } elseif ($pref == self::PREFIX_WALLET_FATHER) {
                /* log Wallet Father On */
                $custId = $orders[$orderId][Query::A_PARENT_ID];
                $this->repoLogCust->create([
                    LogCust::ATTR_TRANS_ID => $tranId,
                    LogCust::ATTR_CUSTOMER_ID => $custId
                ]);
            } else {
                /* log Wallet Grand On */
                $custId = $orders[$orderId][Query::A_PARENT_GRAND_ID];
                $this->repoLogCust->create([
                    LogCust::ATTR_TRANS_ID => $tranId,
                    LogCust::ATTR_CUSTOMER_ID => $custId
                ]);
            }
        }
    }
}