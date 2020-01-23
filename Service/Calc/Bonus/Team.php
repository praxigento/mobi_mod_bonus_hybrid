<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus;

use Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Request as AGetPeriodRequest;
use Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Response as AGetPeriodResponse;
use Praxigento\BonusBase\Repo\Data\Log\Customers as ELogCust;
use Praxigento\BonusBase\Repo\Data\Log\Opers as ELogOper;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Data\Bonus as DBonus;

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
    /** @var \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent */
    private $servPeriodGet;
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
        \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent $servPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Helper\PrepareTrans $hlpTrans,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Helper\CreateOper $hlpOper,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Team\CalcDef $subCalcDef,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Team\CalcEu $subCalcEu
    ) {
        $this->logger = $logger;
        $this->hlpPeriod = $hlpPeriod;
        $this->daoCalc = $daoCalc;
        $this->daoLogCust = $daoLogCust;
        $this->daoLogOper = $daoLogOper;
        $this->servPeriodGet = $servPeriodGet;
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
     * @throws \Exception
     */
    private function getCalcData($scheme)
    {
        $calcTypeCode = ($scheme == Cfg::SCHEMA_EU)
            ? Cfg::CODE_TYPE_CALC_BONUS_TEAM_EU
            : Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF;
        /**
         * Get period & calc data for Team bonus & TV Volumes Calculation.
         */
        $req = new AGetPeriodRequest();
        $req->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_VALUE_TV);
        $req->setDepCalcTypeCode($calcTypeCode);
        /** @var AGetPeriodResponse $resp */
        $resp = $this->servPeriodGet->exec($req);
        /** @var \Praxigento\BonusBase\Repo\Data\Period $teamPeriod */
        $teamPeriod = $resp->getDepPeriodData();
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $teamCalc */
        $teamCalc = $resp->getDepCalcData();
        /**
         * Get period and calc data for compression calc (basic for TV volumes).
         */
        $req = new AGetPeriodRequest();
        $req->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1);
        $req->setDepCalcTypeCode(Cfg::CODE_TYPE_CALC_VALUE_TV);
        $req->setDepIgnoreComplete(true);
        /** @var AGetPeriodResponse $resp */
        $resp = $this->servPeriodGet->exec($req);
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $compressCalc */
        $compressCalc = $resp->getBaseCalcData();
        /**
         * Compose result.
         */
        $result = [$compressCalc, $teamPeriod, $teamCalc];
        return $result;
    }

    /**
     * @param DBonus[] $bonus
     * @param \Praxigento\BonusBase\Repo\Data\Period $period
     * @return \Praxigento\Accounting\Repo\Data\Transaction[]
     * @throws \Exception
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