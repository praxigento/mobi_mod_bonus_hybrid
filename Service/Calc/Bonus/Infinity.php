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

/**
 * Calculate Infinity Bonus.
 */
class Infinity
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
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\Infinity\Calc */
    private $subCalc;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        \Praxigento\BonusBase\Repo\Dao\Calculation $daoCalc,
        \Praxigento\BonusBase\Repo\Dao\Log\Customers $daoLogCust,
        \Praxigento\BonusBase\Repo\Dao\Log\Opers $daoLogOper,
        \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent $servPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Helper\PrepareTrans $hlpTrans,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Helper\CreateOper $hlpOper,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Infinity\Calc $subCalc
    ) {
        $this->logger = $logger;
        $this->hlpPeriod = $hlpPeriod;
        $this->daoCalc = $daoCalc;
        $this->daoLogCust = $daoLogCust;
        $this->daoLogOper = $daoLogOper;
        $this->servPeriodGet = $servPeriodGet;
        $this->hlpTrans = $hlpTrans;
        $this->hlpOper = $hlpOper;
        $this->subCalc = $subCalc;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /* get working data from context */
        $scheme = $ctx->get(self::CTX_IN_SCHEME) ?? Cfg::SCHEMA_DEFAULT;
        /**
         * perform processing
         */
        $ctx->set(self::CTX_OUT_SUCCESS, false);
        $this->logger->info("Override bonus ('$scheme' scheme) is started.");
        /**
         * get dependent calculation data
         *
         * @var \Praxigento\BonusBase\Repo\Data\Calculation $compressCalc
         * @var \Praxigento\BonusBase\Repo\Data\Calculation $infPeriod
         * @var \Praxigento\BonusBase\Repo\Data\Calculation $infCalc
         */
        list($compressCalc, $infPeriod, $infCalc) = $this->getCalcData($scheme);
        $compressCalcId = $compressCalc->getId();
        $infCalcId = $infCalc->getId();
        /* calculate bonus */
        $bonus = $this->subCalc->exec($compressCalcId, $infCalcId, $scheme);
        /* convert calculated bonus to transactions */
        $trans = $this->getTransactions($bonus, $infPeriod);
        /* register bonus operation */
        $operRes = $this->hlpOper->exec(Cfg::CODE_TYPE_OPER_BONUS_INFINITY, $trans, $infPeriod);
        $operId = $operRes->getOperationId();
        $transIds = $operRes->getTransactionsIds();
        /* register transactions in log */
        $this->saveLogCustomers($transIds);
        /* register operation in log */
        $this->saveLogOper($operId, $infCalcId);
        /* mark this calculation complete */
        $this->daoCalc->markComplete($infCalcId);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("Override bonus ('$scheme' scheme) is completed.");
    }

    /**
     * Get period and calculation data for all related calculation types.
     *
     * @return array [$compressCalc, $infPeriod, $infCalc]
     * @throws \Exception
     */
    private function getCalcData($scheme)
    {
        if ($scheme == Cfg::SCHEMA_EU) {
            $baseTypeCode = Cfg::CODE_TYPE_CALC_COMPRESS_PHASE2_EU;
            $depTypeCode = Cfg::CODE_TYPE_CALC_BONUS_INFINITY_EU;
        } else {
            $baseTypeCode = Cfg::CODE_TYPE_CALC_COMPRESS_PHASE2_DEF;
            $depTypeCode = Cfg::CODE_TYPE_CALC_BONUS_INFINITY_DEF;
        }
        /**
         * Get period & calc data for Infinity bonus based on Phase2 Compression.
         */
        $req = new AGetPeriodRequest();
        $req->setBaseCalcTypeCode($baseTypeCode);
        $req->setDepCalcTypeCode($depTypeCode);
        /** @var AGetPeriodResponse $resp */
        $resp = $this->servPeriodGet->exec($req);
        /** @var \Praxigento\BonusBase\Repo\Data\Period $infPeriod */
        $infPeriod = $resp->getDepPeriodData();
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $infCalc */
        $infCalc = $resp->getDepCalcData();
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $compressCalc */
        $compressCalc = $resp->getBaseCalcData();
        /**
         * Compose result.
         */
        $result = [$compressCalc, $infPeriod, $infCalc];
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
        $note = "Infinity ($yyyymm)";
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