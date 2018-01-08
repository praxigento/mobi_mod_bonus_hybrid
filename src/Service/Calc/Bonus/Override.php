<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus;

use Praxigento\BonusBase\Repo\Entity\Data\Log\Customers as ELogCust;
use Praxigento\BonusBase\Repo\Entity\Data\Log\Opers as ELogOper;
use Praxigento\BonusHybrid\Config as Cfg;

/**
 * Calculate Override Bonus.
 */
class Override
    implements \Praxigento\BonusHybrid\Service\Calc\Bonus\IOverride
{
    /** @var \Praxigento\BonusHybrid\Service\Calc\A\Helper\CreateOper */
    private $hlpOper;
    /** @var  \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var \Praxigento\BonusHybrid\Service\Calc\A\Helper\PrepareTrans */
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
        \Psr\Log\LoggerInterface $logger,
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        \Praxigento\BonusBase\Repo\Entity\Calculation $repoCalc,
        \Praxigento\BonusBase\Repo\Entity\Log\Customers $repoLogCust,
        \Praxigento\BonusBase\Repo\Entity\Log\Opers $repoLogOper,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\A\Helper\PrepareTrans $hlpTrans,
        \Praxigento\BonusHybrid\Service\Calc\A\Helper\CreateOper $hlpOper,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Override\Calc $subCalc
    )
    {
        $this->logger = $logger;
        $this->hlpPeriod = $hlpPeriod;
        $this->repoCalc = $repoCalc;
        $this->repoLogCust = $repoLogCust;
        $this->repoLogOper = $repoLogOper;
        $this->procPeriodGet = $procPeriodGet;
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
         * @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $compressCalc
         * @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $ovrdPeriod
         * @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $ovrdCalc
         */
        list($compressCalc, $ovrdPeriod, $ovrdCalc) = $this->getCalcData($scheme);
        $compressCalcId = $compressCalc->getId();
        $ovrdCalcId = $ovrdCalc->getId();
        /* calculate bonus */
        $bonus = $this->subCalc->exec($compressCalcId, $scheme);
        /* convert calculated bonus to transactions */
        $trans = $this->getTransactions($bonus, $ovrdPeriod);
        /* register bonus operation */
        $operRes = $this->hlpOper->exec(Cfg::CODE_TYPE_OPER_BONUS_OVERRIDE, $trans, $ovrdPeriod);
        $operId = $operRes->getOperationId();
        $transIds = $operRes->getTransactionsIds();
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
        if ($scheme == Cfg::SCHEMA_EU) {
            $baseTypeCode = Cfg::CODE_TYPE_CALC_COMPRESS_PHASE2_EU;
            $depTypeCode = Cfg::CODE_TYPE_CALC_BONUS_OVERRIDE_EU;
        } else {
            $baseTypeCode = Cfg::CODE_TYPE_CALC_COMPRESS_PHASE2_DEF;
            $depTypeCode = Cfg::CODE_TYPE_CALC_BONUS_OVERRIDE_DEF;
        }
        /* get period & calc data for Override bonus based on Phase2 Compression */
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
        $dateApplied = $this->hlpPeriod->getTimestampUpTo($dsEnd);
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