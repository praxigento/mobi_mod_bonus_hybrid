<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast\Plain;

use Praxigento\BonusBase\Repo\Data\Calculation as ECalc;
use Praxigento\BonusBase\Repo\Data\Period as EPeriod;
use Praxigento\BonusBase\Repo\Data\Type\Calc as ECalcType;
use Praxigento\BonusBase\Repo\Query\Period\Calcs\Builder as QGetCalcs;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;
use Praxigento\Core\Api\Helper\Period as HPeriod;
use Praxigento\Downline\Repo\Query\Snap\OnDate\Builder as QSnapOnDate;

/**
 * Process to collect downline data and compose array of entities
 * (\Praxigento\BonusHybrid\Repo\Data\Downline) to populate it with additional values and to it save in the end.
 */
class GetDownline
{
    const CTX_IN_CALC_ID = 'calcId';
    const CTX_IN_DATE_ON = 'dateOn';
    const CTX_OUT_DWNL = 'downline';

    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $daoBonDwnl;
    /** @var \Praxigento\Core\Api\App\Repo\Generic */
    private $daoGeneric;
    /** @var \Praxigento\BonusBase\Repo\Dao\Rank */
    private $daoRanks;
    /** @var \Praxigento\Downline\Api\Helper\Config */
    private $hlpCfgDwnl;
    /** @var \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var \Praxigento\Downline\Api\Helper\Tree */
    private $hlpTree;
    /** @var \Praxigento\BonusBase\Repo\Query\Period\Calcs\Builder */
    private $qGetCalcs;
    /** @var \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder */
    private $qSnapOnDate;

    public function __construct(
        \Praxigento\Core\Api\App\Repo\Generic $daoGeneric,
        \Praxigento\BonusBase\Repo\Dao\Rank $daoRank,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoBonDwnl,
        \Praxigento\BonusBase\Repo\Query\Period\Calcs\Builder $qGetCalcs,
        \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder $qSnapOnDate,
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        \Praxigento\Downline\Api\Helper\Tree $hlpTree,
        \Praxigento\Downline\Api\Helper\Config $hlpCfgDwnl
    ) {
        $this->daoGeneric = $daoGeneric;
        $this->daoRanks = $daoRank;
        $this->daoBonDwnl = $daoBonDwnl;
        $this->qGetCalcs = $qGetCalcs;
        $this->qSnapOnDate = $qSnapOnDate;
        $this->hlpPeriod = $hlpPeriod;
        $this->hlpTree = $hlpTree;
        $this->hlpCfgDwnl = $hlpCfgDwnl;
    }

    /**
     * @param \Praxigento\Core\Data $ctx
     * @throws \Exception
     */
    public function exec(\Praxigento\Core\Data $ctx)
    {
        /* get input data from context */
        $calcId = $ctx->get(self::CTX_IN_CALC_ID);
        $dateOn = $ctx->get(self::CTX_IN_DATE_ON);

        /* collect downline data to given date */
        $query = $this->qSnapOnDate->build();
        $conn = $query->getConnection();
        $bind = [QSnapOnDate::BND_ON_DATE => $dateOn];
        $rows = $conn->fetchAll($query, $bind);
        /* ... and default & unqualified ranks IDs */
        $mapRanks = $this->mapDefRanksByCustId();
        /* ... and unq. months for previous period */
        /** @var EBonDwnl[] $mapPlain */
        $mapPlain = $this->mapTreePlainPrev($dateOn);

        /* convert downline data to the entity (prxgt_bon_hyb_dwnl) */
        $result = [];
        foreach ($rows as $row) {
            /* extract repo data */
            $customerId = $row[QSnapOnDate::A_CUST_ID];
            $parentId = $row[QSnapOnDate::A_PARENT_ID];
            $depth = $row[QSnapOnDate::A_DEPTH];
            $path = $row[QSnapOnDate::A_PATH];
            /* prepare result data object */
            $item = new \Praxigento\BonusHybrid\Repo\Data\Downline();
            $item->setCalculationRef($calcId);
            $item->setCustomerRef($customerId);
            $item->setParentRef($parentId);
            $item->setDepth($depth);
            $item->setPath($path);
            /* init PV/TV/OV */
            $item->setPv(0);
            $item->setTv(0);
            $item->setOv(0);
            /* initial ranks and default unqualified months count (will be reset on qualification) */
            $rankIdDef = $mapRanks[$customerId];
            $item->setRankRef($rankIdDef);
            if (isset($mapPlain[$customerId])) {
                $itemPrev = $mapPlain[$customerId];
                $unq = $itemPrev->getUnqMonths();
                $unq++;
            } else {
                $unq = 1;
            }
            $item->setUnqMonths($unq);
            $result[$customerId] = $item;
        }

        /* put results into context and return it (classic way) */
        $ctx->set(self::CTX_OUT_DWNL, $result);
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

    /**
     * @return array [custId => rankId]
     */
    private function mapDefRanksByCustId()
    {
        $result = [];
        /* unqual. customer's group ID & rank */
        $groupIdUnqual = $this->hlpCfgDwnl->getDowngradeGroupUnqual();
        $rankIdUnranked = $this->daoRanks->getIdByCode(Cfg::RANK_UNRANKED);
        $rankIdDefault = $this->daoRanks->getIdByCode(Cfg::RANK_DISTRIBUTOR);

        /* get all customers & map ranks by groups */
        $entity = Cfg::ENTITY_MAGE_CUSTOMER;
        $cols = [
            Cfg::E_CUSTOMER_A_ENTITY_ID,
            Cfg::E_CUSTOMER_A_GROUP_ID
        ];
        $all = $this->daoGeneric->getEntities($entity, $cols);
        foreach ($all as $one) {
            $custId = $one[Cfg::E_CUSTOMER_A_ENTITY_ID];
            $groupId = $one[Cfg::E_CUSTOMER_A_GROUP_ID];
            $rankId = ($groupId == $groupIdUnqual) ? $rankIdUnranked : $rankIdDefault;
            $result[$custId] = $rankId;
        }
        return $result;
    }

    /**
     * Get plain tree for previous period (with unq. months data).
     *
     * @param string $dateOn YYYYMMDD
     * @return EBonDwnl
     */
    private function mapTreePlainPrev($dateOn)
    {
        $prevCalcId = $this->getPrevTreeCalcId($dateOn);
        $tree = $this->daoBonDwnl->getByCalcId($prevCalcId);
        $result = $this->hlpTree->mapById($tree, EBonDwnl::A_CUST_REF);
        return $result;
    }
}