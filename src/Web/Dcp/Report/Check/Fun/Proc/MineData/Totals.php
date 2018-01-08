<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData;

use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Response\Body\Sections\Totals as DTotals;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData\A\Fun\Rou\GetCalcs as RouGetCalcs;
use Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData\A\Fun\Rou\IsSchemeEu as RouIsSchemeEu;
use Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData\Totals\Db\Query\GetAmount as QBGetAmnt;
use Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData\Totals\Db\Query\GetSum as QBGetSum;

/**
 * Action to build "Totals" section of the DCP's "Check" report.
 */
class Totals
{
    /** @var \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var \Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData\Totals\Db\Query\GetAmount */
    private $qbGetAmnt;
    /** @var \Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData\Totals\Db\Query\GetSum */
    private $qbGetSum;
    /** @var \Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData\A\Fun\Rou\GetCalcs */
    private $rouGetCalcs;
    /** @var \Praxigento\BonusHybrid\Web\Dcp\Report\Check\Fun\Proc\MineData\A\Fun\Rou\IsSchemeEu */
    private $rouIsSchemeEu;

    public function __construct(
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        QBGetAmnt $qbGetAmnt,
        QBGetSum $qbGetSum,
        RouGetCalcs $rouGetCalcs,
        RouIsSchemeEu $rouIsSchemeEu
    )
    {
        $this->hlpPeriod = $hlpPeriod;
        $this->qbGetAmnt = $qbGetAmnt;
        $this->qbGetSum = $qbGetSum;
        $this->rouGetCalcs = $rouGetCalcs;
        $this->rouIsSchemeEu = $rouIsSchemeEu;
    }

    public function exec($custId, $period): DTotals
    {
        /* get input and prepare working data */
        $dsBegin = $this->hlpPeriod->getPeriodFirstDate($period);
        $dsEnd = $this->hlpPeriod->getPeriodLastDate($period);

        /* perform processing */
        $calcs = $this->rouGetCalcs->exec($dsBegin, $dsEnd);
        $isSchemeEu = $this->rouIsSchemeEu->exec($custId);
        $idBonPers = $calcs[Cfg::CODE_TYPE_CALC_BONUS_PERSONAL];
        $idBonCourt = $calcs[Cfg::CODE_TYPE_CALC_BONUS_COURTESY];
        if ($isSchemeEu) {
            $idBonTeam = $calcs[Cfg::CODE_TYPE_CALC_BONUS_TEAM_EU];
            $idBonOver = $calcs[Cfg::CODE_TYPE_CALC_BONUS_OVERRIDE_EU];
            $idBonInf = $calcs[Cfg::CODE_TYPE_CALC_BONUS_INFINITY_EU];
        } else {
            $idBonTeam = $calcs[Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF];
            $idBonOver = $calcs[Cfg::CODE_TYPE_CALC_BONUS_OVERRIDE_DEF];
            $idBonInf = $calcs[Cfg::CODE_TYPE_CALC_BONUS_INFINITY_DEF];
        }

        /* fetch data from DB */
        $amntPers = $this->getAmount($idBonPers, $custId);
        $amntTeam = $this->getSum($idBonTeam, $custId);
        $amntCourt = $this->getAmount($idBonCourt, $custId);
        $amntOver = $this->getSum($idBonOver, $custId);
        $amntInf = $this->getSum($idBonInf, $custId);
        $amntTotal = $amntPers + $amntTeam + $amntCourt + $amntOver + $amntInf;
        $amntFee = 0; // TODO: unhardcode fee
        $amntNet = $amntTotal - $amntFee;

        /* compose result */
        $result = new DTotals();
        $result->setPersonalAmount($amntPers);
        $result->setTeamAmount($amntTeam);
        $result->setCourtesyAmount($amntCourt);
        $result->setOverrideAmount($amntOver);
        $result->setInfinityAmount($amntInf);
        $result->setTotalAmount($amntTotal);
        $result->setProcessingFee($amntFee);
        $result->setNetAmount($amntNet);
        return $result;
    }

    private function getAmount($calcId, $custId)
    {
        $query = $this->qbGetAmnt->build();
        $bind = [
            QBGetAmnt::BND_CALC_ID => $calcId,
            QBGetAmnt::BND_CUST_ID => $custId
        ];
        $conn = $query->getConnection();
        $result = $conn->fetchOne($query, $bind);
        $result = $result ? $result : 0;
        return $result;
    }

    private function getSum($calcId, $custId)
    {
        $query = $this->qbGetSum->build();
        $bind = [
            QBGetSum::BND_CALC_ID => $calcId,
            QBGetSum::BND_CUST_ID => $custId
        ];
        $conn = $query->getConnection();
        $result = $conn->fetchOne($query, $bind);
        $result = $result ? $result : 0;
        return $result;
    }

}