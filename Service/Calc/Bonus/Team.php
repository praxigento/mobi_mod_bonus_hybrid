<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus;

use Praxigento\BonusBase\Repo\Data\Log\Customers as ELogCust;
use Praxigento\BonusBase\Repo\Data\Log\Opers as ELogOper;
use Praxigento\BonusHybrid\Config as Cfg;

/**
 * Calculate Team Bonus.
 */
class Team
    implements \Praxigento\Core\Api\App\Service\Process
{
    /** Calculation scheme (DEFAULT or EU) */
    const CTX_IN_SCHEME = 'in.scheme';

    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Helper\CreateOper */
    private $hlpOper;
    /** @var  \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Helper\PrepareTrans */
    private $hlpTrans;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent */
    private $procPeriodGet;
    /** @var \Praxigento\BonusBase\Repo\Dao\Calculation */
    private $daoCalc;
    /** @var \Praxigento\BonusBase\Repo\Dao\Log\Customers */
    private $daoLogCust;
    /** @var \Praxigento\BonusBase\Repo\Dao\Log\Opers */
    private $daoLogOper;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\Team\CalcDef */
    private $subCalcDef;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\Team\CalcEu */
    private $subCalcEu;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        \Praxigento\BonusBase\Repo\Dao\Calculation $daoCalc,
        \Praxigento\BonusBase\Repo\Dao\Log\Customers $daoLogCust,
        \Praxigento\BonusBase\Repo\Dao\Log\Opers $daoLogOper,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Helper\PrepareTrans $hlpTrans,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Helper\CreateOper $hlpOper,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Team\CalcDef $subCalcDef,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Team\CalcEu $subCalcEu
    )
    {
        $this->logger = $logger;
        $this->hlpPeriod = $hlpPeriod;
        $this->daoCalc = $daoCalc;
        $this->daoLogCust = $daoLogCust;
        $this->daoLogOper = $daoLogOper;
        $this->procPeriodGet = $procPeriodGet;
        $this->hlpTrans = $hlpTrans;
        $this->hlpOper = $hlpOper;
        $this->subCalcDef = $subCalcDef;
        $this->subCalcEu = $subCalcEu;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /* get working data from context */
        $scheme = $ctx->get(self::CTX_IN_SCHEME) ?? Cfg::SCHEMA_DEFAULT;
        /**
         * perform processing
         */
        $ctx->set(self::CTX_OUT_SUCCESS, false);
        $this->logger->info("Team bonus ('$scheme' scheme) is started.");
        /**
         * get dependent calculation data
         *
         * @var \Praxigento\BonusBase\Repo\Data\Calculation $compressCalc
         * @var \Praxigento\BonusBase\Repo\Data\Calculation $teamPeriod
         * @var \Praxigento\BonusBase\Repo\Data\Calculation $teamCalc
         */
        list($compressCalc, $teamPeriod, $teamCalc) = $this->getCalcData($scheme);
        $compressCalcId = $compressCalc->getId();
        $teamCalcId = $teamCalc->getId();
        /* calculate bonus according to given SCHEME */
        if ($scheme == Cfg::SCHEMA_EU) {
            $bonus = $this->subCalcEu->exec($compressCalcId);
        } else {
            $bonus = $this->subCalcDef->exec($compressCalcId);
        }
        /* convert calculated bonus to transactions */
        $trans = $this->getTransactions($bonus, $teamPeriod);
        /* register bonus operation */
        $operRes = $this->hlpOper->exec(Cfg::CODE_TYPE_OPER_BONUS_TEAM, $trans, $teamPeriod);
        $operId = $operRes->getOperationId();
        $transIds = $operRes->getTransactionsIds();
        /* register transactions in log */
        $this->saveLogCustomers($transIds);
        /* register operation in log */
        $this->saveLogOper($operId, $teamCalcId);
        /* mark this calculation complete */
        $this->daoCalc->markComplete($teamCalcId);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("Team bonus ('$scheme' scheme) is completed.");
    }

    /**
     * Get period and calculation data for all related calculation types.
     *
     * @param string $scheme see \Praxigento\BonusHybrid\Config::SCHEMA_XXX
     * @return array [$compressCalc, $teamPeriod, $teamCalc]
     */
    private function getCalcData($scheme)
    {
        $calcTypeCode = ($scheme == Cfg::SCHEMA_EU)
            ? Cfg::CODE_TYPE_CALC_BONUS_TEAM_EU
            : Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF;
        /* get period & calc data for Team bonus & TV Volumes Calculation */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->procPeriodGet::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_VALUE_TV);
        $ctx->set($this->procPeriodGet::CTX_IN_DEP_TYPE_CODE, $calcTypeCode);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Data\Period $teamPeriod */
        $teamPeriod = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_PERIOD_DATA);
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $teamCalc */
        $teamCalc = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_CALC_DATA);
        /* get period and calc data for compression calc (basic for TV volumes) */
        $ctx->set($this->procPeriodGet::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1);
        $ctx->set($this->procPeriodGet::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_VALUE_TV);
        $ctx->set($this->procPeriodGet::CTX_IN_DEP_IGNORE_COMPLETE, true);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $compressCalc */
        $compressCalc = $ctx->get($this->procPeriodGet::CTX_OUT_BASE_CALC_DATA);
        /* compose result */
        $result = [$compressCalc, $teamPeriod, $teamCalc];
        return $result;
    }

    /**
     * @param array $bonus [custId => bonusValue]
     * @param \Praxigento\BonusBase\Repo\Data\Period $period
     * @return \Praxigento\Accounting\Repo\Data\Transaction[]
     */
    private function getTransactions($bonus, $period)
    {
        $dsEnd = $period->getDstampEnd();
        $dateApplied = $this->hlpPeriod->getTimestampUpTo($dsEnd);
        $yyyymm = substr($dsEnd, 0, 6);
        $note = "Team ($yyyymm)";
        $result = $this->hlpTrans->exec($bonus, $dateApplied, $note);
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
            $this->daoLogCust->create([
                ELogCust::A_TRANS_ID => $transId,
                ELogCust::A_CUSTOMER_ID => $custId

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
        $this->daoLogOper->create($entity);
    }
}