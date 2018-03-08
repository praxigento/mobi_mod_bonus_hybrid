<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus;

use Praxigento\BonusBase\Repo\Entity\Data\Log\Opers as ELogOper;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\A\Data\Bonus as DBonus;
use Praxigento\Downline\Repo\Entity\Data\Customer as ECustomer;

/**
 * Calculate Personal Bonus.
 */
class Personal
    implements \Praxigento\BonusHybrid\Service\Calc\Bonus\IPersonal
{
    /** @var \Praxigento\BonusBase\Helper\Calc */
    private $hlpCalc;
    /** @var \Praxigento\Downline\Helper\Tree */
    private $hlpDwnlTree;
    /** @var \Praxigento\BonusHybrid\Service\Calc\A\Helper\CreateOper */
    private $hlpOper;
    /** @var  \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var  \Praxigento\BonusHybrid\Helper\IScheme */
    private $hlpScheme;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\A\Helper\PrepareTrans */
    private $hlpTrans;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent */
    private $procPeriodGet;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoBonDwnl;
    /** @var \Praxigento\BonusBase\Repo\Entity\Calculation */
    private $repoCalc;
    /** @var \Praxigento\Downline\Repo\Entity\Customer */
    private $repoDwnl;
    /** @var \Praxigento\BonusBase\Repo\Entity\Level */
    private $repoLevel;
    /** @var \Praxigento\BonusBase\Repo\Entity\Log\Opers */
    private $repoLogOper;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        \Praxigento\BonusBase\Helper\Calc $hlpCalc,
        \Praxigento\BonusHybrid\Helper\IScheme $hlpScheme,
        \Praxigento\Downline\Helper\Tree $hlpDwnlTree,
        \Praxigento\Downline\Repo\Entity\Customer $repoDwnl,
        \Praxigento\BonusBase\Repo\Entity\Calculation $repoCalc,
        \Praxigento\BonusBase\Repo\Entity\Level $repoLevel,
        \Praxigento\BonusBase\Repo\Entity\Log\Opers $repoLogOper,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoBonDwnl,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\A\Helper\PrepareTrans $hlpTrans,
        \Praxigento\BonusHybrid\Service\Calc\A\Helper\CreateOper $hlpOper
    )
    {
        $this->logger = $logger;
        $this->hlpPeriod = $hlpPeriod;
        $this->hlpCalc = $hlpCalc;
        $this->hlpScheme = $hlpScheme;
        $this->hlpDwnlTree = $hlpDwnlTree;
        $this->repoDwnl = $repoDwnl;
        $this->repoCalc = $repoCalc;
        $this->repoLevel = $repoLevel;
        $this->repoLogOper = $repoLogOper;
        $this->repoBonDwnl = $repoBonDwnl;
        $this->procPeriodGet = $procPeriodGet;
        $this->hlpTrans = $hlpTrans;
        $this->hlpOper = $hlpOper;
    }

    /**
     * Walk through the compressed downline tree and calculate Personal bonus for DEFAULT scheme.
     *
     * @param \Praxigento\Downline\Repo\Entity\Data\Customer[] $dwnlCurrent
     * @param \Praxigento\BonusHybrid\Repo\Entity\Data\Downline[] $dwnlCompress
     * @param array $levels percents for bonus levels ([level=>percent])
     *
     * @return DBonus[]
     */
    private function calcBonus($dwnlCurrent, $dwnlCompress, $levels)
    {
        $result = [];
        $mapCustomer = $this->hlpDwnlTree->mapById($dwnlCurrent, ECustomer::ATTR_CUSTOMER_ID);
        /** @var \Praxigento\BonusHybrid\Repo\Entity\Data\Downline $one */
        foreach ($dwnlCompress as $one) {
            $custId = $one->getCustomerRef();
            $pvValue = $one->getPv();
            $customer = $mapCustomer[$custId];
            $scheme = $this->hlpScheme->getSchemeByCustomer($customer);
            if ($scheme == Cfg::SCHEMA_DEFAULT) {
                $bonusValue = $this->hlpCalc->calcForLevelPercent($pvValue, $levels);
                if ($bonusValue > 0) {
                    $entry = new DBonus();
                    $entry->setCustomerRef($custId);
                    $entry->setValue($bonusValue);
                    $result[] = $entry;
                }
            }
        }
        return $result;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /**
         * perform processing
         */
        $ctx->set(self::CTX_OUT_SUCCESS, false);
        $this->logger->info("Personal bonus is started.");
        /* get dependent calculation data */
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $compressCalc */
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Period $persPeriod */
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $persCalc */
        list($compressCalc, $persPeriod, $persCalc) = $this->getCalcData();
        $baseCalcId = $compressCalc->getId();
        $depCalcId = $persCalc->getId();
        /* load downlines (compressed for period & current) */
        $dwnlCompress = $this->repoBonDwnl->getByCalcId($baseCalcId);
        $dwnlCurrent = $this->repoDwnl->get();
        /* get levels to calculate Personal bonus */
        $levels = $this->repoLevel->getByCalcTypeCode(Cfg::CODE_TYPE_CALC_BONUS_PERSONAL);
        /* calculate bonus*/
        $bonus = $this->calcBonus($dwnlCurrent, $dwnlCompress, $levels);
        /* convert calculated bonus to transactions */
        $trans = $this->getTransactions($bonus, $persPeriod);
        /* register bonus operation */
        $operRes = $this->hlpOper->exec(Cfg::CODE_TYPE_OPER_BONUS_PERSONAL, $trans, $persPeriod);
        $operId = $operRes->getOperationId();
        /* register operation in log */
        $this->saveLog($operId, $depCalcId);
        /* mark this calculation complete */
        $this->repoCalc->markComplete($depCalcId);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("Personal bonus is completed.");
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
        $ctx->set($this->procPeriodGet::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1);
        $ctx->set($this->procPeriodGet::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_BONUS_PERSONAL);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $compressCalc */
        $compressCalc = $ctx->get($this->procPeriodGet::CTX_OUT_BASE_CALC_DATA);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Period $persPeriod */
        $persPeriod = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_PERIOD_DATA);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $persCalc */
        $persCalc = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_CALC_DATA);
        $result = [$compressCalc, $persPeriod, $persCalc];
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
     * Bind operation with calculation.
     *
     * @param int $operId
     * @param int $calcId
     */
    private function saveLog($operId, $calcId)
    {
        $entity = new ELogOper();
        $entity->setOperId($operId);
        $entity->setCalcId($calcId);
        $this->repoLogOper->create($entity);
    }

}