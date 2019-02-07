<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2019
 */

namespace Praxigento\BonusHybrid\Service\Calc\Z\Helper;

use Praxigento\BonusBase\Repo\Data\Calculation as ECalc;
use Praxigento\BonusBase\Repo\Data\Period as EPeriod;
use Praxigento\BonusBase\Repo\Data\Type\Calc as ECalcType;
use Praxigento\BonusBase\Repo\Query\Period\Calcs\Builder as QGetCalcs;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;
use Praxigento\Core\Api\Helper\Period as HPeriod;

/**
 * Get plain downline tree for period previous for given (forecast or PV Write Off).
 */
class GetDownlinePlainPrev
{
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $daoBonDwnl;
    /** @var \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var \Praxigento\Downline\Api\Helper\Tree */
    private $hlpTree;
    /** @var \Praxigento\BonusBase\Repo\Query\Period\Calcs\Builder */
    private $qGetCalcs;

    public function __construct(
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoBonDwnl,
        \Praxigento\BonusBase\Repo\Query\Period\Calcs\Builder $qGetCalcs,
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        \Praxigento\Downline\Api\Helper\Tree $hlpTree
    ) {
        $this->daoBonDwnl = $daoBonDwnl;
        $this->qGetCalcs = $qGetCalcs;
        $this->hlpPeriod = $hlpPeriod;
        $this->hlpTree = $hlpTree;
    }

    /**
     * Get plain tree for previous period (with unq. months data).
     *
     * @param string $dateOn YYYYMMDD
     * @return EBonDwnl[]
     */
    public function exec($dateOn)
    {
        $prevCalcId = $this->getPrevTreeCalcId($dateOn);
        $tree = $this->daoBonDwnl->getByCalcId($prevCalcId);
        $result = $this->hlpTree->mapById($tree, EBonDwnl::A_CUST_REF);
        return $result;
    }

    /**
     * Get calc ID (Forecast or PV Write Off) to load plain tree for previous period for $dateOn.
     *
     * @param string $dateOn
     * @return int
     */
    private function getPrevTreeCalcId($dateOn)
    {
        /* get plain tree calc (PV_WRITE_OFF) for prev. period */
        $periodPrev = $this->hlpPeriod->getPeriodPrev($dateOn, HPeriod::TYPE_MONTH);
        $dsLast = $this->hlpPeriod->getPeriodLastDate($periodPrev);

        $query = $this->qGetCalcs->build();

        /* WHERE */
        $bndTypeForecast = 'forecast';
        $bndTypeWriteOff = 'writeOff';
        $bndEnd = 'end';
        $bndState = 'state';
        $byTypeForecast = QGetCalcs::AS_CALC_TYPE . '.' . ECalcType::A_CODE . "=:$bndTypeForecast";
        $byTypeWriteOff = QGetCalcs::AS_CALC_TYPE . '.' . ECalcType::A_CODE . "=:$bndTypeWriteOff";
        $byDateEnd = QGetCalcs::AS_PERIOD . '.' . EPeriod::A_DSTAMP_END . "=:$bndEnd";
        $byState = QGetCalcs::AS_CALC . '.' . ECalc::A_STATE . "=:$bndState";
        $where = "(($byTypeForecast) OR ($byTypeWriteOff)) AND ($byDateEnd) AND ($byState)";
        $query->where($where);

        /* ORDER BY */
        $byCalcIdDesc = QGetCalcs::AS_CALC . '.' . ECalc::A_ID . ' DESC';
        $query->order($byCalcIdDesc);

        /* EXEC QUERY */
        $bind = [
            $bndTypeForecast => Cfg::CODE_TYPE_CALC_FORECAST_PLAIN,
            $bndTypeWriteOff => Cfg::CODE_TYPE_CALC_PV_WRITE_OFF,
            $bndEnd => $dsLast,
            $bndState => Cfg::CALC_STATE_COMPLETE,
        ];
        $conn = $query->getConnection();
        $rs = $conn->fetchAll($query, $bind);
        $one = reset($rs);
        $result = $one[QGetCalcs::A_CALC_ID];

        return $result;
    }

}