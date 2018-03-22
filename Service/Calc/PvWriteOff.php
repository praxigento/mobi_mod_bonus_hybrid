<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc;

use Praxigento\BonusBase\Repo\Entity\Data\Log\Opers as ELogOper;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\PvWriteOff\Data\Trans as DTrans;

class PvWriteOff
    implements IPvWriteOff
{
    /** @var \Praxigento\Accounting\Service\Operation */
    private $callOperation;
    /** @var \Praxigento\Core\Api\Helper\Date */
    private $hlpDate;
    /** @var  \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent */
    private $procPeriodGet;
    /** @var \Praxigento\BonusBase\Repo\Entity\Calculation */
    private $repoCalc;
    /** @var \Praxigento\BonusBase\Repo\Entity\Log\Opers */
    private $repoLogOper;
    /** @var  \Praxigento\Accounting\Repo\Dao\Type\Asset */
    private $repoTypeAsset;
    /** @var  \Praxigento\Accounting\Repo\Dao\Type\Operation */
    private $repoTypeOper;
    /** @var PvWriteOff\Query\GetData\Builder */
    private $sqbGetData;
    /** @var PvWriteOff\PrepareTrans */
    private $subPrepareTrans;
    /** @var PvWriteOff\SaveDownline */
    private $subSaveDownline;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Core\Api\Helper\Date $hlpDate,
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        \Praxigento\Accounting\Repo\Dao\Type\Asset $repoTypeAsset,
        \Praxigento\Accounting\Repo\Dao\Type\Operation $repoTypeOper,
        \Praxigento\BonusBase\Repo\Entity\Calculation $repoCalc,
        \Praxigento\BonusBase\Repo\Entity\Log\Opers $repoLogOper,
        \Praxigento\Accounting\Service\Operation $callOperation,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet,
        PvWriteOff\Query\GetData\Builder $sqbGetData,
        PvWriteOff\PrepareTrans $subPrepareTrans,
        PvWriteOff\SaveDownline $subSaveDownline
    )
    {
        $this->logger = $logger;
        $this->hlpDate = $hlpDate;
        $this->hlpPeriod = $hlpPeriod;
        $this->repoTypeAsset = $repoTypeAsset;
        $this->repoTypeOper = $repoTypeOper;
        $this->repoCalc = $repoCalc;
        $this->repoLogOper = $repoLogOper;
        $this->callOperation = $callOperation;
        $this->procPeriodGet = $procPeriodGet;
        $this->sqbGetData = $sqbGetData;
        $this->subPrepareTrans = $subPrepareTrans;
        $this->subSaveDownline = $subSaveDownline;
    }

    /**
     * Register new operation.
     *
     * @param \Praxigento\Accounting\Repo\Data\Transaction[] $trans
     * @param string $dsBegin
     * @param string $dsEnd
     * @return int operation ID
     * @throws \Exception
     */
    private function createOperation($trans, $dsBegin, $dsEnd)
    {
        $datePerformed = $this->hlpDate->getUtcNowForDb();
        $req = new \Praxigento\Accounting\Api\Service\Operation\Request();
        $req->setOperationTypeCode(Cfg::CODE_TYPE_OPER_PV_WRITE_OFF);
        $req->setDatePerformed($datePerformed);
        $req->setTransactions($trans);
        $note = "PV Write Off ($dsBegin-$dsEnd)";
        $req->setOperationNote($note);
        $resp = $this->callOperation->exec($req);
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
        /* compose transactions for the operation */
        $trans = $this->getTransactions($balances, $dsEnd);
        /* create 'PV Write Off' operation */
        $operId = $this->createOperation($trans, $dsBegin, $dsEnd);
        /* calculate PV/TV/OV and save plain downline */
        $this->subSaveDownline->exec($calcId, $dsEnd, $balances);
        /* register operation in log */
        $this->saveLog($operId, $calcId);
        /* mark this calculation complete */
        $this->repoCalc->markComplete($calcId);
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
        $ctx->set($this->procPeriodGet::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_BONUS_SIGN_UP_DEBIT);
        $ctx->set($this->procPeriodGet::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Period $periodData */
        $periodData = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_PERIOD_DATA);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $calcData */
        $calcData = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_CALC_DATA);
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
        $result = $this->subPrepareTrans->exec($turnover, $dateApplied);
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
        $assetTypeId = $this->repoTypeAsset->getIdByCode(Cfg::CODE_TYPE_ASSET_PV);
        $dateFrom = $this->hlpPeriod->getTimestampFrom($dsBegin);
        $dateTo = $this->hlpPeriod->getTimestampNextFrom($dsEnd);

        $query = $this->sqbGetData->build();
        $bind = [
            $this->sqbGetData::BND_ASSET_TYPE_ID => $assetTypeId,
            $this->sqbGetData::BND_DATE_FROM => $dateFrom,
            $this->sqbGetData::BND_DATE_TO => $dateTo
        ];

        $conn = $query->getConnection();
        $rs = $conn->fetchAll($query, $bind);
        $result = [];
        foreach ($rs as $one) {
            $item = new PvWriteOff\Data\Trans($one);
            $result[] = $item;
        }
        return $result;
    }

    /**
     * Collect PV transitions on accounts and get PV turnover for period.
     *
     * @param PvWriteOff\Data\Trans[] $transData
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
        $this->repoLogOper->create($log);
    }
}