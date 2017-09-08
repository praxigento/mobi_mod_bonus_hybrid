<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus;

use Praxigento\BonusBase\Repo\Entity\Data\Log\Customers as ELogCust;
use Praxigento\BonusBase\Repo\Entity\Data\Log\Opers as ELogOper;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Defaults as Def;

/**
 * Calculate Override Bonus.
 */
class Override
    implements \Praxigento\BonusHybrid\Service\Calc\Bonus\IOverride
{
    /** @var \Praxigento\Accounting\Service\IOperation */
    private $callOperation;
    /** @var \Praxigento\Core\Tool\IDate */
    private $hlpDate;
    /** @var  \Praxigento\Core\Tool\IPeriod */
    private $hlpPeriod;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\Team\PrepareTrans */
    private $hlpTrans;
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent */
    private $procPeriodGet;
    /** @var \Praxigento\BonusBase\Repo\Entity\Calculation */
    private $repoCalc;
    /** @var \Praxigento\BonusBase\Repo\Entity\Log\Customers */
    private $repoLogCust;
    /** @var \Praxigento\BonusBase\Repo\Entity\Log\Opers */
    private $repoLogOper;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\Override\Calc */
    private $subCalc;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\Core\Tool\IDate $hlpDate,
        \Praxigento\Core\Tool\IPeriod $hlpPeriod,
        \Praxigento\BonusBase\Repo\Entity\Calculation $repoCalc,
        \Praxigento\BonusBase\Repo\Entity\Log\Customers $repoLogCust,
        \Praxigento\BonusBase\Repo\Entity\Log\Opers $repoLogOper,
        \Praxigento\Accounting\Service\IOperation $callOperation,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Team\PrepareTrans $hlpTrans,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Override\Calc $subCalc
    )
    {
        $this->logger = $logger;
        $this->hlpDate = $hlpDate;
        $this->hlpPeriod = $hlpPeriod;
        $this->repoCalc = $repoCalc;
        $this->repoLogCust = $repoLogCust;
        $this->repoLogOper = $repoLogOper;
        $this->callOperation = $callOperation;
        $this->procPeriodGet = $procPeriodGet;
        $this->hlpTrans = $hlpTrans;
        $this->subCalc = $subCalc;
    }


    /**
     * Create operations for bonus.
     *
     * @param \Praxigento\Accounting\Repo\Entity\Data\Transaction[] $trans
     * @param \Praxigento\BonusBase\Repo\Entity\Data\Period $period
     * @param string $scheme calculation scheme (DEFAULT or EU)
     * @return array [$operId, $transIds]
     */
    private function createOperation($trans, $period, $scheme)
    {
        $dsBegin = $period->getDstampBegin();
        $dsEnd = $period->getDstampEnd();
        $datePerformed = $this->hlpDate->getUtcNowForDb();
        $req = new \Praxigento\Accounting\Service\Operation\Request\Add();
        $req->setOperationTypeCode(Cfg::CODE_TYPE_OPER_BONUS_TEAM);
        $req->setDatePerformed($datePerformed);
        $req->setTransactions($trans);
        $note = "Override bonus ($dsBegin-$dsEnd)";
        $req->setOperationNote($note);
        /* add key to link newly created transaction IDs with donators */
        $req->setAsTransRef($this->hlpTrans::REF_DONATOR_ID);
        $resp = $this->callOperation->add($req);
        $operId = $resp->getOperationId();
        $transIds = $resp->getTransactionsIds();
        $result = [$operId, $transIds];
        return $result;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /* get working data from context */
        $scheme = $ctx->get(self::CTX_IN_SCHEME) ?? Def::SCHEMA_DEFAULT;
        /**
         * perform processing
         */
        $ctx->set(self::CTX_OUT_SUCCESS, false);
        $this->logger->info("Override bonus ('$scheme' scheme) is started.");
        /**
         * get dependent calculation data
         *
         * @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $compressCalc
         * @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $ovrdPeriod
         * @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $ovrdCalc
         */
        list($compressCalc, $ovrdPeriod, $ovrdCalc) = $this->getCalcData($scheme);
        $compressCalcId = $compressCalc->getId();
        $ovrdCalcId = $ovrdCalc->getId();
        /* calculate bonus */
        $bonus = $this->subCalc->exec($compressCalcId, $ovrdCalcId, $scheme);
        /* convert calculated bonus to transactions */
        $trans = $this->getTransactions($bonus, $ovrdPeriod);
        /* register bonus operation */
        list($operId, $transIds) = $this->createOperation($trans, $ovrdPeriod, $scheme);
        /* register transactions in log */
        $this->saveLogCustomers($transIds);
        /* register operation in log */
        $this->saveLogOper($operId, $ovrdCalcId);
        /* mark this calculation complete */
        $this->repoCalc->markComplete($ovrdCalcId);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("Override bonus ('$scheme' scheme) is completed.");
    }

    /**
     * Get period and calculation data for all related calculation types.
     *
     * @return array [$compressCalc, $ovrdPeriod, $ovrdCalc]
     */
    private function getCalcData($scheme)
    {
        if ($scheme == Def::SCHEMA_EU) {
            $baseTypeCode = Cfg::CODE_TYPE_CALC_COMPRESS_PHASE2_EU;
            $depTypeCode = Cfg::CODE_TYPE_CALC_BONUS_OVERRIDE_EU;
        } else {
            $baseTypeCode = Cfg::CODE_TYPE_CALC_COMPRESS_PHASE2_DEF;
            $depTypeCode = Cfg::CODE_TYPE_CALC_BONUS_OVERRIDE_DEF;
        }
        /* get period & calc data for Courtesy based on TV */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->procPeriodGet::CTX_IN_BASE_TYPE_CODE, $baseTypeCode);
        $ctx->set($this->procPeriodGet::CTX_IN_DEP_TYPE_CODE, $depTypeCode);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Period $ovrdPeriod */
        $ovrdPeriod = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_PERIOD_DATA);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $ovrdCalc */
        $ovrdCalc = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_CALC_DATA);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $compressCalc */
        $compressCalc = $ctx->get($this->procPeriodGet::CTX_OUT_BASE_CALC_DATA);
        /* composer result */
        $result = [$compressCalc, $ovrdPeriod, $ovrdCalc];
        return $result;
    }

    /**
     * @param array $bonus [custId => bonusValue]
     * @param \Praxigento\BonusBase\Repo\Entity\Data\Period $period
     * @return \Praxigento\Accounting\Repo\Entity\Data\Transaction[]
     */
    private function getTransactions($bonus, $period)
    {
        $dsEnd = $period->getDstampEnd();
        $dateApplied = $this->hlpPeriod->getTimestampTo($dsEnd);
        $result = $this->hlpTrans->exec($bonus, $dateApplied);
        return $result;
    }

    /**
     * Save customers log for Team bonus transactions (DEFAULT scheme).
     *
     * @param array $transIds [$transId => $custId]
     */
    private function saveLogCustomers($transIds)
    {
        foreach ($transIds as $transId => $custId) {
            $this->repoLogCust->create([
                ELogCust::ATTR_TRANS_ID => $transId,
                ELogCust::ATTR_CUSTOMER_ID => $custId

            ]);
        }
    }

    /**
     * Bind operation with calculation.
     *
     * @param int $operId
     * @param int $calcId
     */
    private function saveLogOper($operId, $calcId)
    {
        $entity = new ELogOper();
        $entity->setOperId($operId);
        $entity->setCalcId($calcId);
        $this->repoLogOper->create($entity);
    }

}