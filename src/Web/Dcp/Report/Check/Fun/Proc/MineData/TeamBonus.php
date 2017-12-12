<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData;
use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Data\Response\Body\Customer as DCustomer;
use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Data\Response\Body\Sections\TeamBonus as DTeamBonus;
use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Data\Response\Body\Sections\TeamBonus\Item as DItem;
use Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData\A\Fun\Rou\GetCalcs as RouGetCalcs;
use Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData\TeamBonus\Db\Query\GetItems as QBGetItems;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EBonDwnl;

/**
 * Action to build "Team Bonus" section of the DCP's "Check" report.
 */
class TeamBonus
{
    /** @var \Praxigento\Core\Tool\IPeriod */
    private $hlpPeriod;
    /** @var \Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData\TeamBonus\Db\Query\GetItems */
    private $qbGetItems;
    /** @var \Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData\A\Fun\Rou\GetCalcs */
    private $rouGetCalcs;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoBonDwn;

    public function __construct(
        \Praxigento\Core\Tool\IPeriod $hlpPeriod,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoBonDwn,
        QBGetItems $qbGetItems,
        RouGetCalcs $rouGetCalcs
    )
    {
        $this->hlpPeriod = $hlpPeriod;
        $this->repoBonDwn = $repoBonDwn;
        $this->qbGetItems = $qbGetItems;
        $this->rouGetCalcs = $rouGetCalcs;
    }

    public function exec($custId, $period): DTeamBonus
    {
        /* get input and prepare working data */
        $dsBegin = $this->hlpPeriod->getPeriodFirstDate($period);
        $dsEnd = $this->hlpPeriod->getPeriodLastDate($period);

        /* perform processing */
        $calcs = $this->rouGetCalcs->exec($dsBegin, $dsEnd);
        $calcPvWriteOff = $calcs[Cfg::CODE_TYPE_CALC_PV_WRITE_OFF];
        $calcDef = $calcs[Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF];
        $calcEu = $calcs[Cfg::CODE_TYPE_CALC_BONUS_TEAM_EU];

        $pv = $this->getPv($calcPvWriteOff, $custId);
        $items = $this->getItems($calcPvWriteOff, $calcDef, $calcEu, $custId);

        /* compose result */
        $result = new DTeamBonus();
        $result->setItems($items);
        $result->setTotalVolume($pv);
        /** TODO: calc value or remove attr */
        $result->setPercent(0);
        return $result;
    }

    /**
     * Get DB data and compose API data.
     *
     * @param $calcPvWriteOff
     * @param $calcDef
     * @param $calcEu
     * @param $custId
     * @return array
     */
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

    /**
     * Get PV (& RankID ???) for given calculation & customer.
     *
     * @param $calcId
     * @param $custId
     * @return float
     */
    private function getPv($calcId, $custId)
    {
        $byCalcId = EBonDwnl::ATTR_CALC_REF . '=' . (int)$calcId;
        $byCustId = EBonDwnl::ATTR_CUST_REF . '=' . (int)$custId;
        $where = "($byCalcId) AND ($byCustId)";
        $rs = $this->repoBonDwn->get($where);
        $row = reset($rs);
        $pv = $row->get(EBonDwnl::ATTR_PV);
//        $rankId = $row->get(EBonDwnl::ATTR_RANK_REF);
//        return [$pv, $rankId];
        return $pv;
    }
}