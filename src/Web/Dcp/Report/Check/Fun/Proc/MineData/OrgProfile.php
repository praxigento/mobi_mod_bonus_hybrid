<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData;

use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Response\Body\Sections\OrgProfile as DOrgProfile;
use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Response\Body\Sections\OrgProfile\Item as DItem;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Downline as RepoBonDwnl;
use Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData\A\Fun\Rou\GetCalcs as RouGetCalcs;
use Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData\OrgProfile\Db\Query\GetGenerations as QBGetGen;

/**
 * Action to build "Organization Profile" section of the DCP's "Check" report.
 */
class OrgProfile
{
    /** @var \Praxigento\Core\Tool\IPeriod */
    private $hlpPeriod;
    /** @var QBGetGen */
    private $qbGetGen;
    /** @var RepoBonDwnl */
    private $repoBonDwnl;
    /** @var RouGetCalcs */
    private $rouGetCalcs;

    public function __construct(
        \Praxigento\Core\Tool\IPeriod $hlpPeriod,
        RepoBonDwnl $repoBonDwnl,
        QBGetGen $qbGetGen,
        RouGetCalcs $rouGetCalcs
    )
    {
        $this->hlpPeriod = $hlpPeriod;
        $this->repoBonDwnl = $repoBonDwnl;
        $this->qbGetGen = $qbGetGen;
        $this->rouGetCalcs = $rouGetCalcs;
    }

    public function exec($custId, $period): DOrgProfile
    {
        /* get input and prepare working data */
        $dsBegin = $this->hlpPeriod->getPeriodFirstDate($period);
        $dsEnd = $this->hlpPeriod->getPeriodLastDate($period);

        /* perform processing */
        $calcs = $this->rouGetCalcs->exec($dsBegin, $dsEnd);
        $calcIdWriteOff = $calcs[Cfg::CODE_TYPE_CALC_PV_WRITE_OFF];
        $items = $this->getItems($calcIdWriteOff, $custId);

        /* compose result */
        $result = new DOrgProfile();
        $result->setItems($items);
        return $result;
    }

    /**
     * Get DB data and compose API data.
     *
     * @param int $calcIdPlain
     * @param int $custId
     * @return DItem[]
     */
    private function getItems($calcIdPlain, $custId)
    {
        /* get working data */
        list($depthCust, $pathKey) = $this->getPathKey($calcIdPlain, $custId);

        /* compose query to get plain tree data */
        $query = $this->qbGetGen->build();
        $conn = $query->getConnection();
        $bind = [
            QBGetGen::BND_CALC_ID => $calcIdPlain,
            QBGetGen::BND_PV => -1,
            QBGetGen::BND_PATH => $pathKey
        ];
        $rs = $conn->fetchAll($query, $bind);

        /** @var DItem[] $result */
        $result = [];

        /* process all tree (including PV=0) */
        foreach ($rs as $one) {
            /* get DB data */
            $depth = $one[QBGetGen::A_DEPTH];
            $count = $one[QBGetGen::A_COUNT];
            $volume = $one[QBGetGen::A_VOLUME];
            $qual = $one[QBGetGen::A_QUAL];

            /* composite values */
            $generation = $depth - $depthCust;

            /* compose API data */

            $item = new DItem();
            $item->setGeneration($generation);
            $item->setTotal($count);
            $item->setVolume($volume);
            $item->setQual($qual);
            $item->setActive(0);
            $item->setInactive(0);
            $item->setMgr(0);
            $item->setMgrAvg(0);

            $result[$generation] = $item;
        }

        /* process active items only (PV>0) */
        $bind = [
            QBGetGen::BND_CALC_ID => $calcIdPlain,
            QBGetGen::BND_PV => 0,
            QBGetGen::BND_PATH => $pathKey
        ];
        $rs = $conn->fetchAll($query, $bind);
        foreach ($rs as $one) {
            /* get DB data */
            $depth = $one[QBGetGen::A_DEPTH];
            $active = $one[QBGetGen::A_COUNT];

            /* composite values */
            $generation = $depth - $depthCust;
            $item = $result[$generation];
            $generation = $depth - $depthCust;
            $total = $item->getTotal();
            $inactive = $total - $active;

            /* compose API data */
            $item->setActive($active);
            $item->setInactive($inactive);

            $result[$generation] = $item;
        }

        return $result;
    }

    /**
     * Get depth & path key based on the customer data to the given period.
     *
     * @param int $calcId
     * @param int $custId
     * @return array depth & path key - [":123:234:345:$custId:%"]
     */
    private function getPathKey($calcId, $custId)
    {
        $entry = $this->repoBonDwnl->getByKeyCalcCust($calcId, $custId);
        $depth = $entry->getDepth();
        $path = $entry->getPath();
        $pathKey = $path . $custId . Cfg::DTPS . '%';
        $result = [$depth, $pathKey];
        return $result;
    }
}