<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Query\Compress\Phase1\GetPv\Builder as QBldGetPv;

class CompressPhase1
    extends \Praxigento\Core\Service\Base\Call
    implements \Praxigento\BonusHybrid\Service\Calc\ICompressPhase1
{

    /** @var  \Praxigento\Downline\Service\ISnap */
    protected $callDownlineSnap;
    /** @var \Praxigento\BonusHybrid\Service\IPeriod */
    protected $callPeriod;
    /** @var  \Praxigento\Core\Transaction\Database\IManager */
    protected $manTrans;
    protected $qbldGetPv;
    /** @var \Praxigento\Downline\Repo\Entity\ICustomer */
    protected $repoDwnlCustomer;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\Sub\Calc */
    protected $subCalc;
    /** @var  \Praxigento\BonusHybrid\Service\Calc\Sub\Db */
    protected $subDb;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\Core\Transaction\Database\IManager $manTrans,
        \Praxigento\BonusHybrid\Repo\Query\Compress\Phase1\GetPv\Builder $qbldGetPv,
        \Praxigento\Downline\Repo\Entity\ICustomer $repoDwnlCustomer,
        \Praxigento\Downline\Service\ISnap $callDownlineSnap,
        \Praxigento\BonusHybrid\Service\IPeriod $callPeriod,
        \Praxigento\BonusHybrid\Service\Calc\Sub\Db $subDb,
        \Praxigento\BonusHybrid\Service\Calc\Sub\Calc $subCalc
    ) {
        parent::__construct($logger, $manObj);
        $this->manTrans = $manTrans;
        $this->qbldGetPv = $qbldGetPv;
        $this->repoDwnlCustomer = $repoDwnlCustomer;
        $this->callDownlineSnap = $callDownlineSnap;
        $this->callPeriod = $callPeriod;
        $this->subDb = $subDb;
        $this->subCalc = $subCalc;
    }

    public function exec(\Praxigento\BonusHybrid\Service\Calc\CompressPhase1\Request $req)
    {
        $result = new \Praxigento\BonusHybrid\Service\Calc\CompressPhase1\Response();
        $this->_logger->info("Phase1 compression is started.");

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
                $this->_logger->info("Processing period #$thisPeriodId ($thisDsBegin-$thisDsEnd)");
                $downlineSnap = $this->getDownlineSnapshot($thisDsEnd);
                $customersData = $this->repoDwnlCustomer->get();
                $transData = $this->getPv($baseCalcIdId);
                $updates = $this->subCalc->compressPtc($downlineSnap, $customersData, $transData);
                $this->subDb->saveCompressedPtc($updates, $thisCalcId);
                $this->subDb->markCalcComplete($thisCalcId);
                $this->manTrans->commit($def);
                $result->markSucceed();
                $result->setPeriodId($thisPeriodId);
                $result->setCalcId($thisCalcId);
            } finally {
                $this->manTrans->end($def);
            }
        }

        $this->_logMemoryUsage();
        $this->_logger->info("Phase1 compression is completed.");
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
        $result = $conn->fetchAll($query, $bind);
        return $result;
    }
}