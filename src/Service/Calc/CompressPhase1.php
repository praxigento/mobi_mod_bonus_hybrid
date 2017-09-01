<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Query\Compress\Phase1\GetPv\Builder as QBldGetPv;
use Praxigento\BonusHybrid\Service\Calc\CompressPhase1\Calc as SubCalc;

class CompressPhase1
    implements \Praxigento\BonusHybrid\Service\Calc\ICompressPhase1
{

    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\Traits\TMap {
        mapValueById as protected;
    }

    /** @var \Praxigento\BonusHybrid\Service\IPeriod */
    protected $callPeriod;
    /** @var \Psr\Log\LoggerInterface */
    protected $logger;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent */
    private $procPeriodGet;
    /** @var \Praxigento\BonusHybrid\Repo\Query\Compress\Phase1\GetPv\Builder */
    protected $qbGetPv;
    /** @var \Praxigento\BonusHybrid\Repo\Query\MarkCalcComplete */
    protected $queryMarkComplete;
    /** @var \Praxigento\Downline\Repo\Entity\Customer */
    protected $repoDwnl;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Compression\Phase1\Transfer\Pv */
    protected $repoTransPv;
    /** @var \Praxigento\BonusHybrid\Service\Calc\CompressPhase1\Calc */
    protected $subCalc;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\Sub\Calc */
    protected $subCalcOrig;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\Sub\Db */
    protected $subDb;
    /** @var \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder */
    private $qbSnapOnDate;
    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\Downline\Repo\Entity\Customer $repoDwnl,
        \Praxigento\BonusHybrid\Repo\Entity\Compression\Phase1\Transfer\Pv $repoTransPv,
        \Praxigento\BonusHybrid\Repo\Query\Compress\Phase1\GetPv\Builder $qbGetPv,
        \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder $qbSnapOnDate,
        \Praxigento\BonusHybrid\Repo\Query\MarkCalcComplete $qMarkComplete,
        \Praxigento\BonusHybrid\Service\IPeriod $callPeriod,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\Sub\Db $subDb,
        \Praxigento\BonusHybrid\Service\Calc\Sub\Calc $subCalcOrig,
        \Praxigento\BonusHybrid\Service\Calc\CompressPhase1\Calc $subCalc
    )
    {
        $this->logger = $logger;
        $this->repoDwnl = $repoDwnl;
        $this->repoTransPv = $repoTransPv;
        $this->qbGetPv = $qbGetPv;
        $this->qbSnapOnDate = $qbSnapOnDate;
        $this->queryMarkComplete = $qMarkComplete;
        $this->callPeriod = $callPeriod;
        $this->procPeriodGet = $procPeriodGet;
        $this->subDb = $subDb;
        $this->subCalcOrig = $subCalcOrig;
        $this->subCalc = $subCalc;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /**
         * perform processing
         */
        $ctx->set(self::CTX_OUT_SUCCESS, false);
        $this->logger->info("Phase1 compression is started.");
        /* get dependent calculation data */
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $baseCalcData */
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Period $depPeriodData */
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $depCalcData */
        list($baseCalcData, $depPeriodData, $depCalcData) = $this->getCalcData();
        $depPeriodId = $depPeriodData->getId();
        $dsBegin = $depPeriodData->getDstampBegin();
        $dsEnd = $depPeriodData->getDstampEnd();
        $baseCalcId = $baseCalcData->getId();
        $depCalcId = $depCalcData->getId();
        $this->logger->info("Processing period #$depPeriodId ($dsBegin-$dsEnd)");
        /* load source data for calculation */
        $dwnlSnap = $this->getDownlineSnapshot($dsEnd);
        $dwnlCurrent = $this->repoDwnl->get();
        $dataPv = $this->getPv($baseCalcId);
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(SubCalc::CTX_DWNL_CUST, $dwnlCurrent);
        $ctx->set(SubCalc::CTX_DWNL_SNAP, $dwnlSnap);
        $ctx->set(SubCalc::CTX_PV, $dataPv);
        $ctx->set(SubCalc::CTX_CALC_ID, $depCalcId);
        $this->subCalc->exec($ctx);
        $updates = $ctx->get(SubCalc::CTX_COMPRESSED);
        $pvTransfers = $ctx->get(SubCalc::CTX_PV_TRANSFERS);

        /* save results into DB */
        $this->subDb->saveCompressedPtc($updates, $depCalcId);
        $this->savePvTransfers($pvTransfers);
        $this->queryMarkComplete->exec($depCalcId);
        $result->markSucceed();
        $result->setPeriodId($depPeriodId);
        $result->setCalcId($depCalcId);

        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("Phase1 compression is completed.");
    }

    /**
     * Get data for dependent calculation.
     *
     * @return array [$periodData, $calcData]
     */
    private function getCalcData()
    {
        /* get period & calc data */
        $ctxPeriod = new \Praxigento\Core\Data();
        $ctxPeriod->set($this->procPeriodGet::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $ctxPeriod->set($this->procPeriodGet::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1);
        $this->procPeriodGet->exec($ctxPeriod);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $depCalcData */
        $baseCalcData = $ctxPeriod->get($this->procPeriodGet::CTX_OUT_BASE_CALC_DATA);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Period $depPeriodData */
        $depPeriodData = $ctxPeriod->get($this->procPeriodGet::CTX_OUT_DEP_PERIOD_DATA);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $depCalcData */
        $depCalcData = $ctxPeriod->get($this->procPeriodGet::CTX_OUT_DEP_CALC_DATA);
        $result = [$baseCalcData, $depPeriodData, $depCalcData];
        return $result;
    }

    /**
     * Get Downline Tree snapshot on the $datestamp. Result is an array [$customerId => [...], ...]
     *
     * @param $dateOn 'YYYYMMDD'
     *
     * @return array|null
     */
    protected function getDownlineSnapshot($dateOn)
    {
        /* collect downline data to given date */
        $query = $this->qbSnapOnDate->getSelectQuery();
        $conn = $query->getConnection();
        $bind = [$this->qbSnapOnDate::BIND_ON_DATE => $dateOn];
        $result = $conn->fetchAll($query, $bind);
        return $result;
    }

    /**
     *Get PV that are debited inside 'PV Write Off' operation related for the $calcId.
     * @param int $calcId
     * @return array
     */
    protected function getPv($calcId)
    {
        $query = $this->qbGetPv->getSelectQuery();
        $conn = $query->getConnection();
        $bind = [QBldGetPv::BIND_CALC_ID => $calcId];
        $data = $conn->fetchAll($query, $bind);
        $result = $this->mapValueById($data, QBldGetPv::A_CUST_ID, QBldGetPv::A_PV);
        return $result;
    }

    /**
     * @param \Praxigento\BonusHybrid\Repo\Data\Entity\Compression\Phase1\Transfer\Pv[] $data
     */
    protected function savePvTransfers($data)
    {
        /** @var \Praxigento\BonusHybrid\Repo\Data\Entity\Compression\Phase1\Transfer\Pv $one */
        foreach ($data as $one) {
            $this->repoTransPv->create($one);
        }
    }
}