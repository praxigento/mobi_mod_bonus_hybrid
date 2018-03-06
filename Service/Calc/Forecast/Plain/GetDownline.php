<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast\Plain;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\Downline\Repo\Query\Snap\OnDate\Builder as QBSnapOnDate;

/**
 * Process to collect downline data and compose array of entities
 * (\Praxigento\BonusHybrid\Repo\Entity\Data\Downline) to populate it with additional values and to it save in the end.
 */
class GetDownline
{
    const CTX_IN_CALC_ID = 'calcId';
    const CTX_IN_DATE_ON = 'dateOn';
    const CTX_OUT_DWNL = 'downline';

    /** @var \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder */
    private $qbSnapOnDate;
    /** @var \Praxigento\BonusBase\Repo\Entity\Rank */
    private $repoRanks;

    public function __construct(
        \Praxigento\BonusBase\Repo\Entity\Rank $repoRank,
        \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder $qbSnapOnDate
    ) {
        $this->repoRanks = $repoRank;
        $this->qbSnapOnDate = $qbSnapOnDate;
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
        /* ... and default rank ID */
        $rankIdDef = $this->getDefaultRankId();

        /* convert downline data to the entity (prxgt_bon_hyb_dwnl) */
        $result = [];
        foreach ($rows as $row) {
            /* extract repo data */
            $customerId = $row[QBSnapOnDate::A_CUST_ID];
            $parentId = $row[QBSnapOnDate::A_PARENT_ID];
            $depth = $row[QBSnapOnDate::A_DEPTH];
            $path = $row[QBSnapOnDate::A_PATH];
            /* prepare result data object */
            $item = new \Praxigento\BonusHybrid\Repo\Entity\Data\Downline();
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
            $item->setRankRef($rankIdDef);
            $item->setUnqMonths(0);
            $result[$customerId] = $item;
        }

        /* put results into context and return it (classic way) */
        $ctx->set(self::CTX_OUT_DWNL, $result);
    }

    /**
     * Get ID for rank with code DISTRIBUTOR.
     * @return int
     */
    private function getDefaultRankId()
    {
        $result = $this->repoRanks->getIdByCode(Cfg::RANK_DISTRIBUTOR);
        return $result;
    }
}