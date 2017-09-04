<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus;

use Praxigento\BonusBase\Repo\Entity\Data\Log\Opers as ELogOper;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Defaults as Def;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EDwnlBon;
use Praxigento\Downline\Repo\Entity\Data\Customer as ECustomer;

/**
 * Calculate Personal Bonus (DEFAULT scheme).
 */
class Personal
    implements IPersonal
{

    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\Traits\TMap {
        mapById as protected;
    }

    /** @var \Praxigento\BonusBase\Repo\Entity\Calculation */
    private $repoCalc;
    /** @var \Praxigento\BonusBase\Repo\Entity\Log\Opers */
    private $repoLogOper;
    /** @var \Praxigento\Accounting\Service\IOperation */
    private $callOperation;
    /** @var \Praxigento\BonusBase\Helper\Calc */
    private $hlpCalc;
    /** @var \Praxigento\Core\Tool\IDate */
    private $hlpDate;
    /** @var  \Praxigento\Core\Tool\IPeriod */
    private $hlpPeriod;
    /** @var  \Praxigento\BonusHybrid\Tool\IScheme */
    private $hlpScheme;
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent */
    private $procPeriodGet;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\Bonus\Personal\PrepareTrans */
    private $procPrepareTrans;
    /** @var \Praxigento\BonusBase\Repo\Entity\Type\Calc */
    private $repoCalcType;
    /** @var \Praxigento\Downline\Repo\Entity\Customer */
    private $repoDwnl;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoDwnlBon;
    /** @var \Praxigento\BonusBase\Repo\Entity\Level */
    private $repoLevel;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\Core\Tool\IDate $hlpDate,
        \Praxigento\Core\Tool\IPeriod $hlpPeriod,
        \Praxigento\BonusBase\Helper\Calc $hlpCalc,
        \Praxigento\BonusHybrid\Tool\IScheme $hlpScheme,
        \Praxigento\Downline\Repo\Entity\Customer $repoDwnl,
        \Praxigento\BonusBase\Repo\Entity\Calculation $repoCalc,
        \Praxigento\BonusBase\Repo\Entity\Level $repoLevel,
        \Praxigento\BonusBase\Repo\Entity\Log\Opers $repoLogOper,
        \Praxigento\BonusBase\Repo\Entity\Type\Calc $repoCalcType,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoDwnlBon,
        \Praxigento\Accounting\Service\IOperation $callOperation,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Personal\PrepareTrans $procPrepareTrans
    )
    {
        $this->logger = $logger;
        $this->hlpDate = $hlpDate;
        $this->hlpPeriod = $hlpPeriod;
        $this->hlpCalc = $hlpCalc;
        $this->hlpScheme = $hlpScheme;
        $this->repoDwnl = $repoDwnl;
        $this->repoCalc = $repoCalc;
        $this->repoLevel = $repoLevel;
        $this->repoLogOper = $repoLogOper;
        $this->repoCalcType = $repoCalcType;
        $this->repoDwnlBon = $repoDwnlBon;
        $this->callOperation = $callOperation;
        $this->procPeriodGet = $procPeriodGet;
        $this->procPrepareTrans = $procPrepareTrans;
    }

    /**
     * Walk through the compressed downline tree and calculate Personal bonus for DEFAULT scheme.
     *
     * @param \Praxigento\Downline\Repo\Entity\Data\Customer[] $dwnlCurrent
     * @param \Praxigento\BonusHybrid\Repo\Entity\Data\Downline[] $dwnlCompress
     * @param array $levels percents for bonus levels ([level=>percent])
     *
     * @return array [custId => bonusAmount]
     */
    private function calcBonus($dwnlCurrent, $dwnlCompress, $levels)
    {
        $result = [];
        $mapCustomer = $this->mapById($dwnlCurrent, ECustomer::ATTR_CUSTOMER_ID);
        /** @var \Praxigento\BonusHybrid\Repo\Entity\Data\Downline $one */
        foreach ($dwnlCompress as $one) {
            $custId = $one->getCustomerRef();
            $pvValue = $one->getPv();
            $customer = $mapCustomer[$custId];
            $scheme = $this->hlpScheme->getSchemeByCustomer($customer);
            if ($scheme == Def::SCHEMA_DEFAULT) {
                $bonusValue = $this->hlpCalc->calcForLevelPercent($pvValue, $levels);
                if ($bonusValue > 0) {
                    $result[$custId] = $bonusValue;
                }
            }
        }
        return $result;
    }

    /**
     * Create operations for personal bonus.
     *
     * @param \Praxigento\Accounting\Repo\Entity\Data\Transaction[] $trans
     * @param \Praxigento\BonusBase\Repo\Entity\Data\Period $period
     * @return int
     */
    private function createOperation($trans, $period)
    {
        $dsBegin = $period->getDstampBegin();
        $dsEnd = $period->getDstampEnd();
        $datePerformed = $this->hlpDate->getUtcNowForDb();
        $req = new \Praxigento\Accounting\Service\Operation\Request\Add();
        $req->setOperationTypeCode(Cfg::CODE_TYPE_OPER_BONUS_PERSONAL);
        $req->setDatePerformed($datePerformed);
        $req->setTransactions($trans);
        $note = "Personal bonus ($dsBegin-$dsEnd)";
        $req->setOperationNote($note);
        $resp = $this->callOperation->add($req);
        $result = $resp->getOperationId();
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
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $baseCalc */
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Period $depPeriod */
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $depCalc */
        list($baseCalc, $depPeriod, $depCalc) = $this->getCalcData();
        $baseCalcId = $baseCalc->getId();
        $depCalcId = $depCalc->getId();
        /* load downlines (compressed for period & current) */
        $dwnlCompress = $this->getBonusDwnl($baseCalcId);
        $dwnlCurrent = $this->repoDwnl->get();
        /* get levels to calculate Personal bonus */
        $calcTypeId = $this->repoCalcType->getIdByCode(Cfg::CODE_TYPE_CALC_BONUS_PERSONAL_DEF);
        $levels = $this->repoLevel->getByCalcTypeId($calcTypeId);
        /* calculate bonus*/
        $bonus = $this->calcBonus($dwnlCurrent, $dwnlCompress, $levels);
        /* convert calculated bonus to transactions */
        $trans = $this->getTransactions($bonus, $depPeriod);
        /* register bonus operation */
        $operId = $this->createOperation($trans, $depPeriod);
        /* register operation in log */
        $this->saveLog($operId, $depCalcId);
        /* mark this calculation complete */
        $this->repoCalc->markComplete($depCalcId);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("Personal bonus is completed.");
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


    /**
     * @param array $bonus [custId => bonusValue]
     * @param \Praxigento\BonusBase\Repo\Entity\Data\Period $period
     * @return \Praxigento\Accounting\Repo\Entity\Data\Transaction[]
     */
    private function getTransactions($bonus, $period)
    {
        $dsEnd = $period->getDstampEnd();
        $dateApplied = $this->hlpPeriod->getTimestampTo($dsEnd);
        $result = $this->procPrepareTrans->exec($bonus, $dateApplied);
        return $result;
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
        $ctx->set($this->procPeriodGet::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_BONUS_PERSONAL_DEF);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $depCalcData */
        $baseCalcData = $ctx->get($this->procPeriodGet::CTX_OUT_BASE_CALC_DATA);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Period $depPeriodData */
        $depPeriodData = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_PERIOD_DATA);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $depCalcData */
        $depCalcData = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_CALC_DATA);
        $result = [$baseCalcData, $depPeriodData, $depCalcData];
        return $result;
    }

    /**
     * Get compressed downline for base calculation from Bonus module.
     *
     * @param int $calcId
     * @return \Praxigento\BonusHybrid\Repo\Entity\Data\Downline[]
     */
    private function getBonusDwnl($calcId)
    {
        $where = EDwnlBon::ATTR_CALC_REF . '=' . (int)$calcId;
        $result = $this->repoDwnlBon->get($where);
        return $result;
    }

}