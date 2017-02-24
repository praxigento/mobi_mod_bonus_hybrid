<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Calc\SignupDebit;

use Praxigento\Accounting\Data\Entity\Transaction as Trans;
use Praxigento\BonusHybrid\Config as Cfg;

/**
 * Debit 100 PV from customer & add 34.00 AMNT to parent's wallet.
 */
class ProcessOrders
{
    const A_CUST_ID = \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders\Builder::A_CUST_ID;
    const A_ORDER_ID = \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders\Builder::A_ORDER_ID;
    const A_PARENT_ID = \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders\Builder::A_PARENT_ID;
    const A_PV = \Praxigento\BonusHybrid\Repo\Query\SignupDebit\GetOrders\Builder::A_PV;
    const OPT_CALC_ID = 'calc_id';
    const OPT_DATE_APPLIED = 'date_applied';
    const OPT_ORDERS = 'orders';
    /**
     * Prefixes to map transactions to orders to log relations on operation post.
     * Should be 2 chars length.
     */
    const PREFIX_PV = 'pv';
    const PREFIX_WALLET = 'wa';

    /** @var \Praxigento\Accounting\Service\IAccount */
    protected $callAccount;
    /** @var \Praxigento\Accounting\Service\IOperation */
    protected $callOper;
    /** @var \Praxigento\BonusBase\Repo\Entity\Log\ICustomers */
    protected $repoLogCust;
    /** @var \Praxigento\BonusBase\Repo\Entity\Log\IOpers */
    protected $repoLogOper;
    /** @var \Praxigento\BonusBase\Repo\Entity\Log\ISales */
    protected $repoLogSale;

    public function __construct(
        \Praxigento\BonusBase\Repo\Entity\Log\ICustomers $repoLogCust,
        \Praxigento\BonusBase\Repo\Entity\Log\IOpers $repoLogOper,
        \Praxigento\BonusBase\Repo\Entity\Log\ISales $repoLogSale,
        \Praxigento\Accounting\Service\IAccount $callAccount,
        \Praxigento\Accounting\Service\IOperation $callOper
    ) {
        $this->repoLogCust = $repoLogCust;
        $this->repoLogOper = $repoLogOper;
        $this->repoLogSale = $repoLogSale;
        $this->callAccount = $callAccount;
        $this->callOper = $callOper;
    }

    /**
     * @param array $opts
     * @return array
     */
    public function do($opts)
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
            $custId = $one[self::A_CUST_ID];
            $parentId = $one[self::A_PARENT_ID];
            $orderId = $one[self::A_ORDER_ID];
            /* prepare data for transactions */
            $accPvCust = $this->getAccCust(Cfg::CODE_TYPE_ASSET_PV, $custId);
            $accWalletParent = $this->getAccCust(Cfg::CODE_TYPE_ASSET_WALLET_ACTIVE, $parentId);
            /* add PV transaction */
            $tranPvOff = [
                Trans::ATTR_DEBIT_ACC_ID => $accPvCust,
                Trans::ATTR_CREDIT_ACC_ID => $accPvRepres,
                Trans::ATTR_DATE_APPLIED => $dateApplied,
                Trans::ATTR_VALUE => \Praxigento\BonusHybrid\Defaults::SIGNUP_DEBIT_PV,
                $transRef => self::PREFIX_PV . $orderId
            ];
            $trans[] = $tranPvOff;
            /* add Wallet transaction */
            $tranWalletOn = [
                Trans::ATTR_DEBIT_ACC_ID => $accWalletRepres,
                Trans::ATTR_CREDIT_ACC_ID => $accWalletParent,
                Trans::ATTR_DATE_APPLIED => $dateApplied,
                Trans::ATTR_VALUE => \Praxigento\BonusHybrid\Defaults::SIGNUP_DEBIT_WALLET,
                $transRef => self::PREFIX_WALLET . $orderId
            ];
            $trans[] = $tranWalletOn;
        }
        $req->setTransactions($trans);
        $resp = $this->callOper->add($req);
        /* log transactions into Customer & Order logs */
        $ids = $resp->getTransactionsIds();
        $this->saveTransLogs($orders, $ids);
        /* log operation */
        $operId = $resp->getOperationId();
        $this->repoLogOper->create([
            \Praxigento\BonusBase\Data\Entity\Log\Opers::ATTR_CALC_ID => $calcId,
            \Praxigento\BonusBase\Data\Entity\Log\Opers::ATTR_OPER_ID => $operId
        ]);
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
                $custId = $orders[$orderId][\Praxigento\BonusHybrid\Service\Calc\SignupDebit\GetOrders::A_CUST_ID];
                $this->repoLogCust->create([
                    \Praxigento\BonusBase\Data\Entity\Log\Customers::ATTR_TRANS_ID => $tranId,
                    \Praxigento\BonusBase\Data\Entity\Log\Customers::ATTR_CUSTOMER_ID => $custId
                ]);
                $this->repoLogSale->create([
                    \Praxigento\BonusBase\Data\Entity\Log\Sales::ATTR_TRANS_ID => $tranId,
                    \Praxigento\BonusBase\Data\Entity\Log\Sales::ATTR_SALE_ORDER_ID => $orderId
                ]);
            } else {
                /* log Wallet On */
                $custId = $orders[$orderId][\Praxigento\BonusHybrid\Service\Calc\SignupDebit\GetOrders::A_PARENT_ID];
                $this->repoLogCust->create([
                    \Praxigento\BonusBase\Data\Entity\Log\Customers::ATTR_TRANS_ID => $tranId,
                    \Praxigento\BonusBase\Data\Entity\Log\Customers::ATTR_CUSTOMER_ID => $custId
                ]);
            }
        }
    }
}