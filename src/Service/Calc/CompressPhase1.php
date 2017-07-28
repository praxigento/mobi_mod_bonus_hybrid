<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Query\Compress\Phase1\GetPv\Builder as QBldGetPv;
use Praxigento\BonusHybrid\Service\Calc\CompressPhase1\Calc as SubCalc;

class CompressPhase1
    extends \Praxigento\Core\Service\Base\Call
    implements \Praxigento\BonusHybrid\Service\Calc\ICompressPhase1
{

    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\Traits\TMap {
        mapValueById as protected;
    }

    /** @var  \Praxigento\Downline\Service\ISnap */
    protected $callDownlineSnap;
    /** @var \Praxigento\BonusHybrid\Service\IPeriod */
    protected $callPeriod;
    /** @var  \Praxigento\Core\Transaction\Database\IManager */
    protected $manTrans;
    /** @var \Praxigento\BonusHybrid\Repo\Query\Compress\Phase1\GetPv\Builder */
    protected $qbldGetPv;
    /** @var \Praxigento\BonusHybrid\Repo\Query\MarkCalcComplete */
    protected $queryMarkComplete;
    /** @var \Praxigento\Downline\Repo\Entity\Def\Customer */
    protected $repoDwnlCustomer;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Compression\Phase1\Transfer\Pv */
    protected $repoTransPv;
    /** @var \Praxigento\BonusHybrid\Service\Calc\CompressPhase1\Calc */
    protected $subCalc;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\Sub\Calc */
    protected $subCalcOrig;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\Sub\Db */
    protected $subDb;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\Core\Transaction\Database\IManager $manTrans,
        \Praxigento\BonusHybrid\Repo\Query\Compress\Phase1\GetPv\Builder $qbldGetPv,
        \Praxigento\BonusHybrid\Repo\Query\MarkCalcComplete $queryMarkComplete,
        \Praxigento\Downline\Repo\Entity\Def\Customer $repoDwnlCustomer,
        \Praxigento\BonusHybrid\Repo\Entity\Compression\Phase1\Transfer\Pv $repoTransPv,
        \Praxigento\Downline\Service\ISnap $callDownlineSnap,
        \Praxigento\BonusHybrid\Service\IPeriod $callPeriod,
        \Praxigento\BonusHybrid\Service\Calc\Sub\Db $subDb,
        \Praxigento\BonusHybrid\Service\Calc\Sub\Calc $subCalcOrig,
        \Praxigento\BonusHybrid\Service\Calc\CompressPhase1\Calc $subCalc
    ) {
        parent::__construct($logger, $manObj);
        $this->manTrans = $manTrans;
        $this->qbldGetPv = $qbldGetPv;
        $this->queryMarkComplete = $queryMarkComplete;
        $this->repoDwnlCustomer = $repoDwnlCustomer;
        $this->repoTransPv = $repoTransPv;
        $this->callDownlineSnap = $callDownlineSnap;
        $this->callPeriod = $callPeriod;
        $this->subDb = $subDb;
        $this->subCalcOrig = $subCalcOrig;
        $this->subCalc = $subCalc;
    }

    public function exec(\Praxigento\BonusHybrid\Service\Calc\CompressPhase1\Request $req)
    {
        $result = new \Praxigento\BonusHybrid\Service\Calc\CompressPhase1\Response();
        $this->logger->info("Phase1 compression is started.");

        $reqGetPeriod = new \Praxigento\BonusHybrid\Service\Period\Request\GetForDependentCalc();
        $reqGetPeriod->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $reqGetPeriod->setDependentCalcTypeCode(Cfg::CODE_TYPE_CALC_COMPRESS_FOR_PTC);
        $respGetPeriod = $this->callPeriod->getForDependentCalc($reqGetPeriod);
        if ($respGetPeriod->isSucceed()) {
            $def = $this->manTrans->begin();
            try {
                /* working vars */
                $thisPeriodData = $respGetPeriod->getDependentPeriodData();
                $thisPeriodId = $thisPeriodData->getId();
                $thisDsBegin = $thisPeriodData->getDstampBegin();
                $thisDsEnd = $thisPeriodData->getDstampEnd();
                $thisCalcData = $respGetPeriod->getDependentCalcData();
                $thisCalcId = $thisCalcData->getId();
                $baseCalcData = $respGetPeriod->getBaseCalcData();
                $baseCalcIdId = $baseCalcData->getId();
                /* calculation itself */
                $this->logger->info("Processing period #$thisPeriodId ($thisDsBegin-$thisDsEnd)");
                $dataDwnlSnap = $this->getDownlineSnapshot($thisDsEnd);
                /* TODO: use as object not as array */
                $dataDwnlCust = $this->repoDwnlCustomer->get();
                $dataPv = $this->getPv($baseCalcIdId);
                $ctx = new \Flancer32\Lib\Data();
                $ctx->set(SubCalc::CTX_DWNL_CUST, $dataDwnlCust);
                $ctx->set(SubCalc::CTX_DWNL_SNAP, $dataDwnlSnap);
                $ctx->set(SubCalc::CTX_PV, $dataPv);
                $ctx->set(SubCalc::CTX_CALC_ID, $thisCalcId);
                $this->subCalc->exec($ctx);
                $updates = $ctx->get(SubCalc::CTX_COMPRESSED);
                $pvTransfers = $ctx->get(SubCalc::CTX_PV_TRANSFERS);

                /* save results into DB */
                $this->subDb->saveCompressedPtc($updates, $thisCalcId);
                $this->savePvTransfers($pvTransfers);
                $this->queryMarkComplete->exec($thisCalcId);
                $this->manTrans->commit($def);
                $result->markSucceed();
                $result->setPeriodId($thisPeriodId);
                $result->setCalcId($thisCalcId);
            } finally {
                $this->manTrans->end($def);
            }
        }

        $this->logMemoryUsage();
        $this->logger->info("Phase1 compression is completed.");
        return $result;
    }

    /**
     * Get Downline Tree snapshot on the $datestamp. Result is an array [$customerId => [...], ...]
     *
     * @param $datestamp 'YYYYMMDD'
     *
     * @return array|null
     */
    protected function getDownlineSnapshot($datestamp)
    {
        $req = new \Praxigento\Downline\Service\Snap\Request\GetStateOnDate();
        $req->setDatestamp($datestamp);
        $resp = $this->callDownlineSnap->getStateOnDate($req);
        $result = $resp->get();
        return $result;
    }

    /**
     *Get PV that are debited inside 'PV Write Off' operation related for the $calcId.
     * @param int $calcId
     * @return array
     */
    protected function getPv($calcId)
    {
        $query = $this->qbldGetPv->getSelectQuery();
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