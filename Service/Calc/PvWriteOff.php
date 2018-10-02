<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc;

use Praxigento\BonusBase\Repo\Data\Log\Opers as ELogOper;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A\Data\Trans as DTrans;

class PvWriteOff
    implements \Praxigento\Core\Api\App\Service\Process
{
    /** @var \Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A\PrepareTrans */
    private $aPrepareTrans;
    /** @var \Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A\SaveDownline */
    private $aSaveDownline;
    /** @var \Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A\Validate */
    private $aValidate;
    /** @var \Praxigento\BonusBase\Repo\Dao\Calculation */
    private $daoCalc;
    /** @var \Praxigento\BonusBase\Repo\Dao\Log\Opers */
    private $daoLogOper;
    /** @var  \Praxigento\Accounting\Repo\Dao\Type\Asset */
    private $daoTypeAsset;
    /** @var \Praxigento\Core\Api\Helper\Date */
    private $hlpDate;
    /** @var  \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A\Query\GetData\Builder */
    private $qbGetData;
    /** @var \Praxigento\Accounting\Service\Operation\Create */
    private $servOperation;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent */
    private $servPeriodGet;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Core\Api\Helper\Date $hlpDate,
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        \Praxigento\Accounting\Repo\Dao\Type\Asset $daoTypeAsset,
        \Praxigento\BonusBase\Repo\Dao\Calculation $daoCalc,
        \Praxigento\BonusBase\Repo\Dao\Log\Opers $daoLogOper,
        \Praxigento\Accounting\Service\Operation\Create $servOperation,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $servPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A\Query\GetData\Builder $qbGetData,
        \Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A\PrepareTrans $aPrepareTrans,
        \Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A\SaveDownline $aSaveDownline,
        \Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A\Validate $aValidate
    )
    {
        $this->logger = $logger;
        $this->hlpDate = $hlpDate;
        $this->hlpPeriod = $hlpPeriod;
        $this->daoTypeAsset = $daoTypeAsset;
        $this->daoCalc = $daoCalc;
        $this->daoLogOper = $daoLogOper;
        $this->servOperation = $servOperation;
        $this->servPeriodGet = $servPeriodGet;
        $this->qbGetData = $qbGetData;
        $this->aPrepareTrans = $aPrepareTrans;
        $this->aSaveDownline = $aSaveDownline;
        $this->aValidate = $aValidate;
    }

    /**
     * Register new operation.
     *
     * @param \Praxigento\Accounting\Repo\Data\Transaction[] $trans
     * @param string $dsBegin
     * @return int operation ID
     * @throws \Exception
     */
    private function createOperation($trans, $dsBegin)
    {
        $datePerformed = $this->hlpDate->getUtcNowForDb();
        $req = new \Praxigento\Accounting\Api\Service\Operation\Create\Request();
        $req->setOperationTypeCode(Cfg::CODE_TYPE_OPER_PV_WRITE_OFF);
        $req->setDatePerformed($datePerformed);
        $req->setTransactions($trans);
        $period = substr($dsBegin, 0, 6);
        $note = "PV Write Off ($period)";
        $req->setOperationNote($note);
        $resp = $this->servOperation->exec($req);
        $result = $resp->getOperationId();
        return $result;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        $this->logger->info("PV Write Off calculation is started.");
        /**
         * perform processing
         */
        $ctx->set(self::CTX_OUT_SUCCESS, false);
        /* get dependent calculation data */
        list($periodData, $calcData) = $this->getCalcData();
        $dsBegin = $periodData->getDstampBegin();
        $dsEnd = $periodData->getDstampEnd();
        $calcId = $calcData->getId();
        /* get accounting data for calculation (PV transitions) */
        $transitions = $this->getTransitions($dsBegin, $dsEnd);
        /* group PV transitions by account */
        $balances = $this->groupPvTrans($transitions);
        /* look up fo accounts with negative balance, compose list & throw exception */
        $this->aValidate->exec($balances);
        /* compose transactions for the operation */
        $trans = $this->getTransactions($balances, $dsEnd);
        /* create 'PV Write Off' operation */
        $operId = $this->createOperation($trans, $dsBegin, $dsEnd);
        /* calculate PV/TV/OV and save plain downline */
        $this->aSaveDownline->exec($calcId, $dsEnd, $balances);
        /* register operation in log */
        $this->saveLog($operId, $calcId);
        /* mark this calculation complete */
        $this->daoCalc->markComplete($calcId);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("PV Write Off calculation is completed.");
    }

    /**
     * Get data for dependent calculation.
     *
     * @return array [$periodData, $calcData]
     */
    private function getCalcData()
    {
        /* get period & calc data */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->servPeriodGet::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_BONUS_SIGN_UP_DEBIT);
        $ctx->set($this->servPeriodGet::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $this->servPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Data\Period $periodData */
        $periodData = $ctx->get($this->servPeriodGet::CTX_OUT_DEP_PERIOD_DATA);
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $calcData */
        $calcData = $ctx->get($this->servPeriodGet::CTX_OUT_DEP_CALC_DATA);
        $result = [$periodData, $calcData];
        return $result;
    }

    /**
     * Convert turnover data into transactions data to create operation.
     *
     * @param array $turnover [accId => pvTurnover]
     * @param string $dsEnd (YYYYMMDD)
     * @return \Praxigento\Accounting\Repo\Data\Transaction[]
     */
    private function getTransactions($turnover, $dsEnd)
    {
        $dateApplied = $this->hlpPeriod->getTimestampUpTo($dsEnd);
        $result = $this->aPrepareTrans->exec($turnover, $dateApplied);
        return $result;
    }

    /**
     * Get PV transitions data for period.
     *
     * @param string $dsBegin
     * @param string $dsEnd
     * @return  DTrans[]
     * @throws \Exception
     */
    private function getTransitions($dsBegin, $dsEnd)
    {
        $assetTypeId = $this->daoTypeAsset->getIdByCode(Cfg::CODE_TYPE_ASSET_PV);
        $dateFrom = $this->hlpPeriod->getTimestampFrom($dsBegin);
        $dateTo = $this->hlpPeriod->getTimestampNextFrom($dsEnd);

        $query = $this->qbGetData->build();
        $bind = [
            $this->qbGetData::BND_ASSET_TYPE_ID => $assetTypeId,
            $this->qbGetData::BND_DATE_FROM => $dateFrom,
            $this->qbGetData::BND_DATE_TO => $dateTo
        ];

        $conn = $query->getConnection();
        $rs = $conn->fetchAll($query, $bind);
        $result = [];
        foreach ($rs as $one) {
            $item = new \Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A\Data\Trans($one);
            $result[] = $item;
        }
        return $result;
    }

    /**
     * Collect PV transitions on accounts and get PV turnover for period.
     *
     * @param \Praxigento\BonusHybrid\Service\Calc\PvWriteOff\A\Data\Trans[] $transData
     * @return array [accId => pvTurnover]
     */
    private function groupPvTrans($transData)
    {
        $result = [];
        foreach ($transData as $one) {
            $debitAccId = $one->get(DTrans::A_ACC_ID_DEBIT);
            $creditAccId = $one->get(DTrans::A_ACC_ID_CREDIT);
            $value = $one->get(DTrans::A_AMOUNT);
            if (isset($result[$debitAccId])) {
                $result[$debitAccId] -= $value;
            } else {
                $result[$debitAccId] = -$value;
            }
            if (isset($result[$creditAccId])) {
                $result[$creditAccId] += $value;
            } else {
                $result[$creditAccId] = $value;
            }
        }
        return $result;
    }

    /**
     * Bind operations with calculation.
     *
     * @param int $operIdWriteOff
     * @param int $calcId
     * @throws \Exception
     */
    private function saveLog($operIdWriteOff, $calcId)
    {
        /* log PvWriteOff operation itself */
        $log = new ELogOper();
        $log->setCalcId($calcId);
        $log->setOperId($operIdWriteOff);
        $this->daoLogOper->create($log);
    }
}