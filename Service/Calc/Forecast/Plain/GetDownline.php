<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast\Plain;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\Downline\Repo\Query\Snap\OnDate\Builder as QBSnapOnDate;

/**
 * Process to collect downline data and compose array of entities
 * (\Praxigento\BonusHybrid\Repo\Data\Downline) to populate it with additional values and to it save in the end.
 */
class GetDownline
{
    const CTX_IN_CALC_ID = 'calcId';
    const CTX_IN_DATE_ON = 'dateOn';
    const CTX_OUT_DWNL = 'downline';

    /** @var \Praxigento\Core\Api\App\Repo\Generic */
    private $daoGeneric;
    /** @var \Praxigento\BonusBase\Repo\Dao\Rank */
    private $daoRanks;
    /** @var \Praxigento\Downline\Api\Helper\Config */
    private $hlpCfgDwnl;
    /** @var \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder */
    private $qbSnapOnDate;

    public function __construct(
        \Praxigento\Core\Api\App\Repo\Generic $daoGeneric,
        \Praxigento\BonusBase\Repo\Dao\Rank $daoRank,
        \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder $qbSnapOnDate,
        \Praxigento\Downline\Api\Helper\Config $hlpCfgDwnl
    ) {
        $this->daoGeneric = $daoGeneric;
        $this->daoRanks = $daoRank;
        $this->qbSnapOnDate = $qbSnapOnDate;
        $this->hlpCfgDwnl = $hlpCfgDwnl;
    }

    /**
     * @param \Praxigento\Core\Data $ctx
     */
    public function exec(\Praxigento\Core\Data $ctx)
    {
        /* get input data from context */
        $calcId = $ctx->get(self::CTX_IN_CALC_ID);
        $dateOn = $ctx->get(self::CTX_IN_DATE_ON);

        /* collect downline data to given date */
        $query = $this->qbSnapOnDate->build();
        $conn = $query->getConnection();
        $bind = [QBSnapOnDate::BND_ON_DATE => $dateOn];
        $rows = $conn->fetchAll($query, $bind);
        /* ... and default & unqualified ranks IDs */
        $mapRanks = $this->mapDefRanksByCustId();

        /* convert downline data to the entity (prxgt_bon_hyb_dwnl) */
        $result = [];
        foreach ($rows as $row) {
            /* extract repo data */
            $customerId = $row[QBSnapOnDate::A_CUST_ID];
            $parentId = $row[QBSnapOnDate::A_PARENT_ID];
            $depth = $row[QBSnapOnDate::A_DEPTH];
            $path = $row[QBSnapOnDate::A_PATH];
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
            /* init ranks and unqualified months count */
            $rankIdDef = $mapRanks[$customerId];
            $item->setRankRef($rankIdDef);
            $result[$customerId] = $item;
        }

        /* put results into context and return it (classic way) */
        $ctx->set(self::CTX_OUT_DWNL, $result);
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
}