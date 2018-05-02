<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus;

use Praxigento\BonusBase\Repo\Data\Log\Opers as ELogOper;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Data\Bonus as DBonus;
use Praxigento\Downline\Repo\Data\Customer as ECustomer;

/**
 * Calculate Personal Bonus.
 */
class Personal
    implements \Praxigento\Core\Api\App\Service\Process
{
    /** @var \Praxigento\BonusBase\Helper\Calc */
    private $hlpCalc;
    /** @var \Praxigento\Downline\Api\Helper\Tree */
    private $hlpDwnlTree;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Helper\CreateOper */
    private $hlpOper;
    /** @var  \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var  \Praxigento\BonusHybrid\Api\Helper\Scheme */
    private $hlpScheme;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Helper\PrepareTrans */
    private $hlpTrans;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent */
    private $procPeriodGet;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $daoBonDwnl;
    /** @var \Praxigento\BonusBase\Repo\Dao\Calculation */
    private $daoCalc;
    /** @var \Praxigento\Downline\Repo\Dao\Customer */
    private $daoDwnl;
    /** @var \Praxigento\BonusBase\Repo\Dao\Level */
    private $daoLevel;
    /** @var \Praxigento\BonusBase\Repo\Dao\Log\Opers */
    private $daoLogOper;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        \Praxigento\BonusBase\Helper\Calc $hlpCalc,
        \Praxigento\BonusHybrid\Api\Helper\Scheme $hlpScheme,
        \Praxigento\Downline\Api\Helper\Tree $hlpDwnlTree,
        \Praxigento\Downline\Repo\Dao\Customer $daoDwnl,
        \Praxigento\BonusBase\Repo\Dao\Calculation $daoCalc,
        \Praxigento\BonusBase\Repo\Dao\Level $daoLevel,
        \Praxigento\BonusBase\Repo\Dao\Log\Opers $daoLogOper,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoBonDwnl,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Helper\PrepareTrans $hlpTrans,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Helper\CreateOper $hlpOper
    )
    {
        $this->logger = $logger;
        $this->hlpPeriod = $hlpPeriod;
        $this->hlpCalc = $hlpCalc;
        $this->hlpScheme = $hlpScheme;
        $this->hlpDwnlTree = $hlpDwnlTree;
        $this->daoDwnl = $daoDwnl;
        $this->daoCalc = $daoCalc;
        $this->daoLevel = $daoLevel;
        $this->daoLogOper = $daoLogOper;
        $this->daoBonDwnl = $daoBonDwnl;
        $this->procPeriodGet = $procPeriodGet;
        $this->hlpTrans = $hlpTrans;
        $this->hlpOper = $hlpOper;
    }

    /**
     * Walk through the compressed downline tree and calculate Personal bonus for DEFAULT scheme.
     *
     * @param \Praxigento\Downline\Repo\Data\Customer[] $dwnlCurrent
     * @param \Praxigento\BonusHybrid\Repo\Data\Downline[] $dwnlCompress
     * @param array $levels percents for bonus levels ([level=>percent])
     *
     * @return DBonus[]
     */
    private function calcBonus($dwnlCurrent, $dwnlCompress, $levels)
    {
        $result = [];
        $mapCustomer = $this->hlpDwnlTree->mapById($dwnlCurrent, ECustomer::A_CUSTOMER_ID);
        /** @var \Praxigento\BonusHybrid\Repo\Data\Downline $one */
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
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $compressCalc */
        /** @var \Praxigento\BonusBase\Repo\Data\Period $persPeriod */
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $persCalc */
        list($compressCalc, $persPeriod, $persCalc) = $this->getCalcData();
        $baseCalcId = $compressCalc->getId();
        $depCalcId = $persCalc->getId();
        /* load downlines (compressed for period & current) */
        $dwnlCompress = $this->daoBonDwnl->getByCalcId($baseCalcId);
        $dwnlCurrent = $this->daoDwnl->get();
        /* get levels to calculate Personal bonus */
        $levels = $this->daoLevel->getByCalcTypeCode(Cfg::CODE_TYPE_CALC_BONUS_PERSONAL);
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
        $this->daoCalc->markComplete($depCalcId);
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
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $compressCalc */
        $compressCalc = $ctx->get($this->procPeriodGet::CTX_OUT_BASE_CALC_DATA);
        /** @var \Praxigento\BonusBase\Repo\Data\Period $persPeriod */
        $persPeriod = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_PERIOD_DATA);
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $persCalc */
        $persCalc = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_CALC_DATA);
        $result = [$compressCalc, $persPeriod, $persCalc];
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
        $note = "Personal ($yyyymm)";
        $result = $this->hlpTrans->exec($bonus, $dateApplied, $note);
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
        $this->daoLogOper->create($entity);
    }

}