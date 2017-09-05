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
 * Calculate Team Bonus.
 */
class Team
    implements ITeam
{
    /** @var \Praxigento\Accounting\Service\IOperation */
    private $callOperation;
    /** @var \Praxigento\Core\Tool\IDate */
    private $hlpDate;
    /** @var  \Praxigento\Core\Tool\IPeriod */
    private $hlpPeriod;
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
    /** @var Team\Calc\DefScheme */
    private $subCalcDef;
    /** @var Team\Calc\EuScheme */
    private $subCalcEu;
    /** @var Team\PrepareTrans */
    private $subPrepareTrans;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\Core\Tool\IDate $hlpDate,
        \Praxigento\Core\Tool\IPeriod $hlpPeriod,
        \Praxigento\BonusBase\Repo\Entity\Calculation $repoCalc,
        \Praxigento\BonusBase\Repo\Entity\Log\Customers $repoLogCust,
        \Praxigento\BonusBase\Repo\Entity\Log\Opers $repoLogOper,
        \Praxigento\Accounting\Service\IOperation $callOperation,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet,
        Team\Calc\DefScheme $subCalcDef,
        Team\Calc\EuScheme $subCalcEu,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Team\PrepareTrans $subPrepareTrans
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
        $this->subCalcDef = $subCalcDef;
        $this->subCalcEu = $subCalcEu;
        $this->subPrepareTrans = $subPrepareTrans;
    }

    /**
     * Create operations for personal bonus.
     *
     * @param \Praxigento\Accounting\Repo\Entity\Data\Transaction[] $trans
     * @param \Praxigento\BonusBase\Repo\Entity\Data\Period $period
     * @return array [$operId, $transIds]
     */
    private function createOperation($trans, $period)
    {
        $dsBegin = $period->getDstampBegin();
        $dsEnd = $period->getDstampEnd();
        $datePerformed = $this->hlpDate->getUtcNowForDb();
        $req = new \Praxigento\Accounting\Service\Operation\Request\Add();
        $req->setOperationTypeCode(Cfg::CODE_TYPE_OPER_BONUS_TEAM);
        $req->setDatePerformed($datePerformed);
        $req->setTransactions($trans);
        $note = "Team bonus ($dsBegin-$dsEnd)";
        $req->setOperationNote($note);
        /* add key to link newly created transaction IDs with donators */
        $req->setAsTransRef($this->subPrepareTrans::REF_DONATOR_ID);
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
        $this->logger->info("Team bonus is started.");
        /**
         * get dependent calculation data
         *
         * @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $compressCalc
         * @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $teamPeriod
         * @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $teamCalc
         */
        list($compressCalc, $teamPeriod, $teamCalc) = $this->getCalcData($scheme);
        $compressCalcId = $compressCalc->getId();
        $teamCalcId = $teamCalc->getId();
        /* calculate bonus according to given SCHEME */
        if ($scheme == Def::SCHEMA_EU) {
            $bonus = $this->subCalcEu->exec($compressCalcId);
        } else {
            $bonus = $this->subCalcDef->exec($compressCalcId);
        }
        /* convert calculated bonus to transactions */
        $trans = $this->getTransactions($bonus, $teamPeriod);
        /* register bonus operation */
        list($operId, $transIds) = $this->createOperation($trans, $teamPeriod);
        /* register transactions in log */
        $this->saveLogCustomers($transIds);
        /* register operation in log */
        $this->saveLogOper($operId, $teamCalcId);
        /* mark this calculation complete */
        $this->repoCalc->markComplete($teamCalcId);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("Team bonus is completed.");
    }

    /**
     * Get period and calculation data for all related calculation types.
     *
     * @return array [$compressCalc, $teamPeriod, $teamCalc]
     */
    private function getCalcData($scheme)
    {
        $calcTypeCode = ($scheme == Def::SCHEMA_EU) ?
            Cfg::CODE_TYPE_CALC_BONUS_TEAM_EU : Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF;
        /* get period & calc data for team bonus & TV volumes calculations */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->procPeriodGet::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_VALUE_TV);
        $ctx->set($this->procPeriodGet::CTX_IN_DEP_TYPE_CODE, $calcTypeCode);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Period $teamPeriod */
        $teamPeriod = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_PERIOD_DATA);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $teamCalc */
        $teamCalc = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_CALC_DATA);
        /* get period and calc data for compression calc (basic for TV volumes) */
        $ctx->set($this->procPeriodGet::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1);
        $ctx->set($this->procPeriodGet::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_VALUE_TV);
        $ctx->set($this->procPeriodGet::CTX_IN_DEP_IGNORE_COMPLETE, true);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $compressCalc */
        $compressCalc = $ctx->get($this->procPeriodGet::CTX_OUT_BASE_CALC_DATA);
        /* compose result */
        $result = [$compressCalc, $teamPeriod, $teamCalc];
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
        $result = $this->subPrepareTrans->exec($bonus, $dateApplied);
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