<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Service\Calc\Aggregate\A;

use Praxigento\Accounting\Api\Service\Operation\Create\Request as ARequest;
use Praxigento\Accounting\Repo\Data\Transaction as ETrans;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\Aggregate\A\Data\Total as DTotal;

class CreateOper
{
    /** @var \Praxigento\Accounting\Service\Operation\Create */
    private $callOper;
    /** @var \Praxigento\Accounting\Repo\Dao\Account */
    private $daoAcc;
    /** @var \Praxigento\Accounting\Repo\Dao\Type\Asset */
    private $daoAssetType;
    /** @var \Praxigento\Core\Api\Helper\Date */
    private $hlpDate;
    /** @var \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;

    public function __construct(
        \Praxigento\Accounting\Repo\Dao\Account $daoAcc,
        \Praxigento\Accounting\Repo\Dao\Type\Asset $daoAssetType,
        \Praxigento\Core\Api\Helper\Date $hlpDate,
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        \Praxigento\Accounting\Service\Operation\Create $callOper
    ) {
        $this->daoAcc = $daoAcc;
        $this->daoAssetType = $daoAssetType;
        $this->hlpDate = $hlpDate;
        $this->hlpPeriod = $hlpPeriod;
        $this->callOper = $callOper;
    }

    /**
     * @param DTotal[] $totals
     * @param string $periodEnd YYYYMMDD
     * @return int
     * @throws \Exception
     */
    public function exec($totals, $periodEnd)
    {
        /** define local working data */
        $dateApplied = $this->hlpPeriod->getTimestampUpTo($periodEnd);
        $periodMonth = $this->formatPeriod($periodEnd);
        $noteBonus = 'Bonus debit #' . $periodMonth;
        $noteWallet = 'Check #' . $periodMonth;
        /** perform processing */
        $typeIdBonus = $this->daoAssetType->getIdByCode(Cfg::CODE_TYPE_ASSET_BONUS);
        $typeIdWallet = $this->daoAssetType->getIdByCode(Cfg::CODE_TYPE_ASSET_WALLET);
        $accSysBonus = $this->getSysAccount($typeIdBonus);
        $accSysWallet = $this->getSysAccount($typeIdWallet);
        $trans = [];
        foreach ($totals as $one) {
            /** define local working data */
            $accCustBonus = $one->accountId;
            $custId = $one->customerId;
            $amount = $one->total;
            /* get WALLET account ID for customer */
            $walletData = $this->daoAcc->getByCustomerId($custId, $typeIdWallet);
            $accCustWallet = $walletData->getId();
            /** perform processing */
            /* create debit transaction for BONUS */
            $tran = new ETrans();
            $tran->setDebitAccId($accCustBonus);
            $tran->setCreditAccId($accSysBonus);
            $tran->setDateApplied($dateApplied);
            $tran->setValue($amount);
            $tran->setNote($noteBonus);
            $trans[] = $tran;
            /* create credit transaction for WALLET */
            $tran = new ETrans();
            $tran->setDebitAccId($accSysWallet);
            $tran->setCreditAccId($accCustWallet);
            $tran->setDateApplied($dateApplied);
            $tran->setValue($amount);
            $tran->setNote($noteWallet);
            $trans[] = $tran;
        }
        /* create operation*/
        $datePerformed = $this->hlpDate->getUtcNowForDb();
        $req = new ARequest();
        $req->setOperationTypeCode(Cfg::CODE_TYPE_OPER_BONUS_AGGREGATE);
        $req->setDatePerformed($datePerformed);
        $req->setTransactions($trans);
        $note = "Bonus aggregation #$periodMonth";
        $req->setOperationNote($note);
        $resp = $this->callOper->exec($req);
        $result = $resp->getOperationId();

        return $result;
    }

    /**
     * @param string $datestamp "YYYYMMDD"
     * @return string "YYYY/MM"
     */
    private function formatPeriod($datestamp)
    {
        $year = substr($datestamp, 0, 4);
        $month = substr($datestamp, 4, 2);
        $result = "$year/$month";
        return $result;
    }

    /**
     * @param int $assetTypeId
     * @return int
     * @throws \Exception
     */
    private function getSysAccount($assetTypeId)
    {
        $result = $this->daoAcc->getSystemAccountId($assetTypeId);
        return $result;
    }
}