<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData;

use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Sections\Totals as DTotals;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\A\Fun\Rou\GetCalcs as RouGetCalcs;
use Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\A\Fun\Rou\IsSchemeEu as RouIsSchemeEu;
use Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\Totals\Db\Query\GetPersonal as QBGetPersonal;

/**
 * Action to build "Totals" section of the DCP's "Check" report.
 */
class Totals
{
    /** @var \Praxigento\Core\Tool\IPeriod */
    private $hlpPeriod;
    /** @var \Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\Totals\Db\Query\GetPersonal */
    private $qbGetPersonal;
    /** @var \Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\A\Fun\Rou\GetCalcs */
    private $rouGetCalcs;
    /** @var \Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\A\Fun\Rou\IsSchemeEu */
    private $rouIsSchemeEu;

    public function __construct(
        \Praxigento\Core\Tool\IPeriod $hlpPeriod,
        QBGetPersonal $qbGetPersonal,
        RouGetCalcs $rouGetCalcs,
        RouIsSchemeEu $rouIsSchemeEu
    )
    {
        $this->hlpPeriod = $hlpPeriod;
        $this->qbGetPersonal = $qbGetPersonal;
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
        $calcIdBonPers = $calcs[Cfg::CODE_TYPE_CALC_BONUS_PERSONAL];
        if ($isSchemeEu) {
            $calcCompress = $calcs[Cfg::CODE_TYPE_CALC_COMPRESS_PHASE2_EU];
            $calcBonus = $calcs[Cfg::CODE_TYPE_CALC_BONUS_INFINITY_EU];
        } else {
            $calcCompress = $calcs[Cfg::CODE_TYPE_CALC_COMPRESS_PHASE2_DEF];
            $calcBonus = $calcs[Cfg::CODE_TYPE_CALC_BONUS_INFINITY_DEF];
        }

        /* fetch data from DB */
        $amntPers = $this->getPersonalBonus($calcIdBonPers, $custId);

        /* compose result */
        $result = new DTotals();
        $result->setPersonalAmount($amntPers);
        return $result;
    }

    private function getPersonalBonus($calcId, $custId)
    {
        $query = $this->qbGetPersonal->build();
        $bind = [
            QBGetPersonal::BND_CALC_ID => $calcId,
            QBGetPersonal::BND_CUST_ID => $custId
        ];
        $conn = $query->getConnection();
        $result = $conn->fetchOne($query, $bind);
        return $result;
    }

}