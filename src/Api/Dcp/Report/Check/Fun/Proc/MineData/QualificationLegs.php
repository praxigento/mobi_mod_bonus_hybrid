<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc\MineData;

use Praxigento\BonusBase\Repo\Query\Period\Calcs\Get\Builder as QBGetPeriodCalcs;
use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Sections\QualificationLegs as DQualLegs;
use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc\MineData\A\Fun\Rou\GetCalcs as RouGetCalcs;
use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc\MineData\QualificationLegs\Db\Query\GetItems as QBGetItems;
use Praxigento\BonusHybrid\Config as Cfg;

/**
 * Action to build "QualificationLegs" section of the DCP's "Check" report.
 */
class QualificationLegs
{
    /** @var \Praxigento\Core\Tool\IPeriod */
    private $hlpPeriod;
    /** @var \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc\MineData\QualificationLegs\Db\Query\GetItems */
    private $qbGetItems;
    /** @var \Praxigento\BonusBase\Repo\Query\Period\Calcs\Get\Builder */
    private $qbGetPeriodCalcs;
    /** @var \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc\MineData\A\Fun\Rou\GetCalcs */
    private $rouGetCalcs;

    public function __construct(
        \Praxigento\Core\Tool\IPeriod $hlpPeriod,
        QBGetItems $qbGetItems,
        RouGetCalcs $rouGetCalcs
    )
    {
        $this->hlpPeriod = $hlpPeriod;
        $this->qbGetItems = $qbGetItems;
        $this->rouGetCalcs = $rouGetCalcs;
    }

    public function exec($custId, $period): DQualLegs
    {
        /* get input and prepare working data */
        $dsBegin = $this->hlpPeriod->getPeriodFirstDate($period);
        $dsEnd = $this->hlpPeriod->getPeriodLastDate($period);

        /* perform processing */
        $calcs = $this->rouGetCalcs->exec($dsBegin, $dsEnd);
        $calcPvWriteOff = $calcs[Cfg::CODE_TYPE_CALC_PV_WRITE_OFF];
        $calcDef = $calcs[Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF];
        $calcEu = $calcs[Cfg::CODE_TYPE_CALC_BONUS_TEAM_EU];

        $result = new DQualLegs();
        return $result;
    }

    /**
     * Get calculations IDs by calc type code for given period bounds.
     *
     * @param $dsBegin
     * @param $dsEnd
     * @return array [$calcTypeCode => $calcId]
     */
    private function getCalcs($dsBegin, $dsEnd)
    {
        $query = $this->qbGetPeriodCalcs->build();
        $bind = [
            QBGetPeriodCalcs::BND_DATE_BEGIN => $dsBegin,
            QBGetPeriodCalcs::BND_DATE_END => $dsEnd,
            QBGetPeriodCalcs::BND_STATE => Cfg::CALC_STATE_COMPLETE,
        ];

        $conn = $query->getConnection();
        $rs = $conn->fetchAll($query, $bind);

        $result = [];
        foreach ($rs as $one) {
            $calcType = $one[QBGetPeriodCalcs::A_CALC_TYPE_CODE];
            $calcId = $one[QBGetPeriodCalcs::A_CALC_ID];
            $result[$calcType] = $calcId;
        }
        return $result;
    }

    private function getItems($calcPvWriteOff, $calcDef, $calcEu, $custId)
    {
        $query = $this->qbGetItems->build();
        $conn = $query->getConnection();
        $bind = [
            QBGetItems::BND_CALC_ID_PV_WRITE_OFF => $calcPvWriteOff,
            QBGetItems::BND_CALC_ID_TEAM_DEF => $calcDef,
            QBGetItems::BND_CALC_ID_TEAM_EU => $calcEu,
            QBGetItems::BND_CUST_ID => $custId
        ];
        $rs = $conn->fetchAll($query, $bind);

        $result = [];
        foreach ($rs as $one) {
            /* get DB data */
            $custId = $one[QBGetItems::A_CUST_ID];
            $depth = $one[QBGetItems::A_DEPTH];
            $mlmId = $one[QBGetItems::A_MLM_ID];
            $nameFirst = $one[QBGetItems::A_NAME_FIRST];
            $nameLast = $one[QBGetItems::A_NAME_LAST];
            $pv = $one[QBGetItems::A_PV];
            $amount = $one[QBGetItems::A_AMOUNT];

            /* composite values */
            $name = "$nameFirst $nameLast";

            /* compose API data */
            $customer = new DCustomer();
            $customer->setId($custId);
            $customer->setMlmId($mlmId);
            $customer->setName($name);
            $customer->setLevel($depth);
            $item = new DItem();
            $item->setCustomer($customer);
            $item->setAmount($amount);
            $item->setVolume($pv);

            $result[] = $item;
        }
        return $result;
    }

}