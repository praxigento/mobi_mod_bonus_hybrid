<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2020
 */

namespace Praxigento\BonusHybrid\Service\Calc\SignUp\Credit\A;

use Praxigento\Accounting\Repo\Data\Transaction as Trans;
use Praxigento\BonusBase\Repo\Data\Log\Customers as LogCust;
use Praxigento\BonusBase\Repo\Data\Log\Opers as LogOpers;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\SignUp\Credit\A\Repo\Query\GetCustomers as QGetCust;
use Praxigento\Downline\Repo\Data\Customer as EDwnlCust;

/**
 * Add 34.00 of bonus to parent and 18.00 to grand.
 */
class ProcessCredits
{
    private const PREFIX_BONUS_FATHER = 'bf';
    private const PREFIX_BONUS_GRAND = 'bg';

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
    /** @var \Praxigento\Downline\Api\Helper\Tree */
    private $hlpTree;
    /** @var \Praxigento\Accounting\Api\Service\Account\Get */
    private $servAccount;
    /** @var \Praxigento\Accounting\Api\Service\Operation\Create */
    private $servOper;

    public function __construct(
        \Praxigento\Downline\Api\Helper\Tree $hlpTree,
        \Praxigento\BonusHybrid\Api\Helper\Scheme $hlpScheme,
        \Praxigento\BonusBase\Repo\Dao\Log\Customers $daoLogCust,
        \Praxigento\BonusBase\Repo\Dao\Log\Opers $daoLogOper,
        \Praxigento\BonusBase\Repo\Dao\Log\Sales $daoLogSale,
        \Praxigento\BonusHybrid\Repo\Dao\Registry\SignUpDebit $daoRegSignUpDebit,
        \Praxigento\Accounting\Api\Service\Account\Get $servAccount,
        \Praxigento\Accounting\Api\Service\Operation\Create $servOper
    ) {
        $this->hlpTree = $hlpTree;
        $this->hlpScheme = $hlpScheme;
        $this->daoLogCust = $daoLogCust;
        $this->daoLogOper = $daoLogOper;
        $this->daoRegSignUpDebit = $daoRegSignUpDebit;
        $this->daoLogSale = $daoLogSale;
        $this->servAccount = $servAccount;
        $this->servOper = $servOper;
    }

    /**
     * @param array $items
     * @param string $dateApplied
     * @param int $calcId
     * @throws \Exception
     */
    public function exec($items, $dateApplied, $calcId)
    {
        /* get system accounts */
        $accSys = $this->getAccSys(Cfg::CODE_TYPE_ASSET_BONUS);
        /* Create one operation for all transactions */
        $req = new \Praxigento\Accounting\Api\Service\Operation\Create\Request();
        $req->setOperationTypeCode(Cfg::CODE_TYPE_OPER_BONUS_SIGNUP_CREDIT);
        $period = substr($dateApplied, 0, 7);
        $period = str_replace('-', '', $period);
        $note = "Sign Up Credit ($period)";
        $req->setOperationNote($note);
        $transRef = 'ref';
        $req->setAsTransRef($transRef);
        /* prepare transactions */
        $trans = [];
        foreach ($items as $one) {
            $custCountry = $one[QGetCust::A_CUST_COUNTRY];
            $scheme = $this->hlpScheme->getSchemeByCustomer([EDwnlCust::A_COUNTRY_CODE => $custCountry]);
            /** Sign Up bonus is applied for EU customers only */
            if ($scheme == Cfg::SCHEMA_EU) {
                /* prepare data for transactions */
                $saleId = $one[QGetCust::A_SALE_ID];
                $saleIncId = $one[QGetCust::A_SALE_INC_ID];
                $custMlmId = $one[QGetCust::A_CUST_MLM_ID];
                $note = "Sign Up Credit for order #$saleIncId cust. #$custMlmId";
                $path = $one[QGetCust::A_PATH];
                $parents = $this->hlpTree->getParentsFromPathReversed($path);
                /* add BONUS transaction for "father" */
                if (isset($parents[0])) {
                    $parentId = $parents[0];
                    $accParent = $this->getAccCust(Cfg::CODE_TYPE_ASSET_BONUS, $parentId);
                    $tran = [
                        Trans::A_DEBIT_ACC_ID => $accSys,
                        Trans::A_CREDIT_ACC_ID => $accParent,
                        Trans::A_DATE_APPLIED => $dateApplied,
                        Trans::A_VALUE => Cfg::SIGNUP_DEBIT_BONUS_FATHER,
                        Trans::A_NOTE => $note . ' (level 1)',
                        $transRef => self::PREFIX_BONUS_FATHER . $saleId
                    ];
                    $trans[] = $tran;
                }
                /* add BONUS transaction for "grand" */
                if (isset($parents[1])) {
                    $grandId = $parents[1];
                    $accGrand = $this->getAccCust(Cfg::CODE_TYPE_ASSET_BONUS, $grandId);
                    $tran = [
                        Trans::A_DEBIT_ACC_ID => $accSys,
                        Trans::A_CREDIT_ACC_ID => $accGrand,
                        Trans::A_DATE_APPLIED => $dateApplied,
                        Trans::A_VALUE => Cfg::SIGNUP_DEBIT_BONUS_GRAND,
                        Trans::A_NOTE => $note . ' (level 2)',
                        $transRef => self::PREFIX_BONUS_GRAND . $saleId
                    ];
                    $trans[] = $tran;
                }
            }
        }
        $req->setTransactions($trans);
        $resp = $this->servOper->exec($req);
        /* log transactions into Customer & Order logs */
        $ids = $resp->getTransactionsIds();
        $this->saveTransLogs($items, $ids);
        /* log operation */
        $operId = $resp->getOperationId();
        $this->daoLogOper->create([
            LogOpers::A_CALC_ID => $calcId,
            LogOpers::A_OPER_ID => $operId
        ]);
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
     * @param array $items
     * @param array $transIds
     */
    private function saveTransLogs($items, $transIds)
    {
        /* re-map items with saleId key */
        $bySaleId = [];
        foreach ($items as $one) {
            $saleId = $one[QGetCust::A_SALE_ID];
            $bySaleId[$saleId] = $one;
        }
        $exist = $this->daoLogCust->get(LogCust::A_CUSTOMER_ID."=116");
        /* process transactions and resolve references to customer & it's parents */
        foreach ($transIds as $tranId => $ref) {
            $pref = substr($ref, 0, 2);
            $orderId = str_replace($pref, '', $ref);
            $path = $bySaleId[$orderId][QGetCust::A_PATH];
            $parents = $this->hlpTree->getParentsFromPathReversed($path);
            if ($pref == self::PREFIX_BONUS_FATHER) {
                /* log BONUS Father On */
                $parentId = $parents[0];
                $this->daoLogCust->create([
                    LogCust::A_TRANS_ID => $tranId,
                    LogCust::A_CUSTOMER_ID => $parentId
                ]);
            } elseif ($pref == self::PREFIX_BONUS_GRAND) {
                /* log BONUS Grand On */
                $grandId = $parents[1];
                $this->daoLogCust->create([
                    LogCust::A_TRANS_ID => $tranId,
                    LogCust::A_CUSTOMER_ID => $grandId
                ]);
            }
        }
    }
}
