<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Compress;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;
use Praxigento\BonusHybrid\Repo\Query\Compress\Phase1\GetPv\Builder as QBldGetPv;
use Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase1 as PPhase1;
use Praxigento\Downline\Repo\Query\Snap\OnDate\Builder as QBSnap;

class Phase1
    implements \Praxigento\BonusHybrid\Service\Calc\Compress\IPhase1
{
    /** @var \Praxigento\Downline\Helper\Tree */
    private $hlpDwnlTree;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent */
    private $procPeriodGet;
    /** @var \Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase1 */
    private $procPhase1;
    /** @var \Praxigento\BonusHybrid\Repo\Query\Compress\Phase1\GetPv\Builder */
    private $qbGetPv;
    /** @var \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder */
    private $qbSnapOnDate;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $repoBonDwnl;
    /** @var \Praxigento\BonusBase\Repo\Dao\Calculation */
    private $repoCalc;
    /** @var \Praxigento\BonusBase\Repo\Dao\Rank */
    private $repoRank;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Compression\Phase1\Transfer\Pv */
    private $repoTransPv;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Downline\Helper\Tree $hlpDwnlTree,
        \Praxigento\BonusBase\Repo\Dao\Calculation $repoCalc,
        \Praxigento\BonusBase\Repo\Dao\Rank $repoRank,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $repoBonDwnl,
        \Praxigento\BonusHybrid\Repo\Dao\Compression\Phase1\Transfer\Pv $repoTransPv,
        \Praxigento\BonusHybrid\Repo\Query\Compress\Phase1\GetPv\Builder $qbGetPv,
        \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder $qbSnapOnDate,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase1 $procPhase1
    )
    {
        $this->logger = $logger;
        $this->hlpDwnlTree = $hlpDwnlTree;
        $this->repoCalc = $repoCalc;
        $this->repoRank = $repoRank;
        $this->repoBonDwnl = $repoBonDwnl;
        $this->repoTransPv = $repoTransPv;
        $this->qbGetPv = $qbGetPv;
        $this->qbSnapOnDate = $qbSnapOnDate;
        $this->procPeriodGet = $procPeriodGet;
        $this->procPhase1 = $procPhase1;
    }

    /**
     * Wrapper for compression sub-process.
     *
     * @param array $dwnlSnap see \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder
     * @param array $pv [custId => pv]
     * @param int $calcId
     * @return array [$updates, $pvTransfers]
     */
    private function compress($dwnlSnap, $pv, $calcId)
    {
        $in = new \Praxigento\Core\Data();
        $in->set(PPhase1::IN_DWNL_PLAIN, $dwnlSnap);
        $in->set(PPhase1::IN_PV, $pv);
        $in->set(PPhase1::IN_CALC_ID, $calcId);
        $in->set(PPhase1::IN_KEY_CALC_ID, EBonDwnl::A_CALC_REF);
        $in->set(PPhase1::IN_KEY_CUST_ID, QBSnap::A_CUST_ID);
        $in->set(PPhase1::IN_KEY_PARENT_ID, QBSnap::A_PARENT_ID);
        $in->set(PPhase1::IN_KEY_DEPTH, QBSnap::A_DEPTH);
        $in->set(PPhase1::IN_KEY_PATH, QBSnap::A_PATH);
        $in->set(PPhase1::IN_KEY_PV, EBonDwnl::A_PV);
        $out = $this->procPhase1->exec($in);
        $updates = $out->get(PPhase1::OUT_COMPRESSED);
        $pvTransfers = $out->get(PPhase1::OUT_PV_TRANSFERS);
        $result = [$updates, $pvTransfers];
        return $result;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /**
         * perform processing
         */
        $ctx->set(self::CTX_OUT_SUCCESS, false);
        $this->logger->info("Phase1 compression is started.");
        /* get dependent calculation data */
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $writeOffCalc */
        /** @var \Praxigento\BonusBase\Repo\Data\Period $compressPeriod */
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $compressCalc */
        list($writeOffCalc, $compressPeriod, $compressCalc) = $this->getCalcData();
        $compressPeriodId = $compressPeriod->getId();
        $dsBegin = $compressPeriod->getDstampBegin();
        $dsEnd = $compressPeriod->getDstampEnd();
        $writeOffCalcId = $writeOffCalc->getId();
        $compressCalcId = $compressCalc->getId();
        $this->logger->info("Phase1 compression period #$compressPeriodId ($dsBegin-$dsEnd)");
        /* load source data for calculation */
        /* TODO: move source data collection into sub-class */
        $dwnlSnap = $this->getDownlineSnapshot($dsEnd);
        $dataPv = $this->getPv($writeOffCalcId);
        /** @var \Praxigento\Downline\Repo\Data\Snap[] $updates */
        /** @var \Praxigento\BonusHybrid\Repo\Data\Compression\Phase1\Transfer\Pv[] $pvTransfers */
        list($updates, $pvTransfers) = $this->compress($dwnlSnap, $dataPv, $compressCalcId);
        /* save compressed downline & PV transfers into DB */
        $this->saveBonusDownline($updates, $compressCalcId);
        $this->savePvTransfers($pvTransfers);
        /* mark this calculation complete */
        $this->repoCalc->markComplete($compressCalcId);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("Phase1 compression is completed.");
    }

    /**
     * Get data for calculations/periods.
     *
     * @return array $writeOffCalc, $compressPeriod, $compressCalc
     */
    private function getCalcData()
    {
        /* get data for compression & PV write off calculations */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->procPeriodGet::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $ctx->set($this->procPeriodGet::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $compressCalc */
        $writeOffCalc = $ctx->get($this->procPeriodGet::CTX_OUT_BASE_CALC_DATA);
        /** @var \Praxigento\BonusBase\Repo\Data\Period $compressPeriod */
        $compressPeriod = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_PERIOD_DATA);
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $depCalcData */
        $compressCalc = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_CALC_DATA);
        $result = [$writeOffCalc, $compressPeriod, $compressCalc];
        return $result;
    }

    /**
     * Get ID for default rank.
     *
     * @return int
     */
    private function getDefaultRankId()
    {
        $result = $this->repoRank->getIdByCode(Cfg::RANK_DISTRIBUTOR);
        return $result;
    }

    /**
     * Get Downline Tree snapshot on the $datestamp. Result is an array [$customerId => [...], ...]
     *
     * @param $dateOn 'YYYYMMDD'
     *
     * @return array|null
     */
    private function getDownlineSnapshot($dateOn)
    {
        /* collect downline data to given date */
        $query = $this->qbSnapOnDate->build();
        $conn = $query->getConnection();
        $bind = [$this->qbSnapOnDate::BND_ON_DATE => $dateOn];
        $result = $conn->fetchAll($query, $bind);
        return $result;
    }

    /**
     * Get PV that are debited inside 'PV Write Off' operation related for the $calcId.
     * @param int $calcId
     * @return array
     */
    private function getPv($calcId)
    {
        $query = $this->qbGetPv->build();
        $conn = $query->getConnection();
        $bind = [QBldGetPv::BIND_CALC_ID => $calcId];
        $data = $conn->fetchAll($query, $bind);
        $result = $this->hlpDwnlTree->mapValueById($data, QBldGetPv::A_CUST_ID, QBldGetPv::A_PV);
        return $result;
    }

    /**
     * @param array $snap snap data with PV (see
     *     \Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase1::populateCompressedSnapWithPv)
     * @param int $calcId
     */
    private function saveBonusDownline($snap, $calcId)
    {
        $rankId = $this->getDefaultRankId();

        foreach ($snap as $one) {
            /* get working vars */
            $custId = $one[QBSnap::A_CUST_ID];
            $depth = $one[QBSnap::A_DEPTH];
            $parentId = $one[QBSnap::A_PARENT_ID];
            $path = $one[QBSnap::A_PATH];
            $pv = $one[EBonDwnl::A_PV];
            /* compose new entity to save */
            $entity = new EBonDwnl();
            $entity->setCalculationRef($calcId);
            $entity->setCustomerRef($custId);
            $entity->setDepth($depth);
            $entity->setOv(0);
            $entity->setParentRef($parentId);
            $entity->setPath($path);
            $entity->setPv($pv);
            $entity->setRankRef($rankId);
            $entity->setTv(0);
            $entity->setUnqMonths(0);
            $this->repoBonDwnl->create($entity);
        }
    }

    /**
     * @param \Praxigento\BonusHybrid\Repo\Data\Compression\Phase1\Transfer\Pv[] $data
     */
    private function savePvTransfers($data)
    {
        /** @var \Praxigento\BonusHybrid\Repo\Data\Compression\Phase1\Transfer\Pv $one */
        foreach ($data as $one) {
            $this->repoTransPv->create($one);
        }
    }
}