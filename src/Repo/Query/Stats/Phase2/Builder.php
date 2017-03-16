<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Repo\Query\Stats\Phase2;

use Praxigento\BonusBase\Data\Entity\Rank as Rank;
use Praxigento\BonusHybrid\Entity\Compression\Oi as Oi;
use Praxigento\Downline\Data\Entity\Customer as Cust;
use Praxigento\Pv\Data\Entity\Sale as Pv;

/**
 * Build query to get phase1 compressed PV/TV/OV statistics for the given calculation.
 */
class Builder
    extends \Praxigento\Core\Repo\Query\Def\Builder
{
    /** Tables aliases */
    const AS_CUST = 'cst';
    const AS_PARENT = 'prn';
    const AS_RANK = 'rank';
    const AS_TREE = 'tree';

    /** Columns aliases */
    const A_CUST_ID = Oi::ATTR_CUSTOMER_ID;
    const A_CUST_MLM_ID = 'customer_mlm_id';
    const A_DEPTH = Oi::ATTR_DEPTH;
    const A_OV_MAX = Oi::ATTR_OV_LEG_MAX;
    const A_OV_OTHER = Oi::ATTR_OV_LEG_OTHERS;
    const A_OV_SECOND = Oi::ATTR_OV_LEG_SECOND;
    const A_PARENT_ID = Oi::ATTR_PARENT_ID;
    const A_PARENT_MLM_ID = 'parent_mlm_id';
    const A_PATH = Oi::ATTR_PATH;
    const A_PV = Oi::ATTR_PV;
    const A_RANK = 'rank';
    const A_TV = Oi::ATTR_TV;

    /** Bound variables names */
    const BIND_CALC_REF = 'calcRef';

    /**
     * @inheritdoc
     */
    public function getSelectQuery(\Praxigento\Core\Repo\Query\IBuilder $qbuild = null)
    {
        $result = $this->conn->select(); // this is root query
        /* define tables aliases */
        $asTree = self::AS_TREE;
        $asRank = self::AS_RANK;
        $asCust = self::AS_CUST;
        $asPrnt = self::AS_PARENT;

        /* SELECT FROM prxgt_bon_hyb_cmprs_ptc */
        $tbl = $this->resource->getTableName(Oi::ENTITY_NAME);
        $cols = [
            self::A_CUST_ID => Oi::ATTR_CUSTOMER_ID,
            self::A_PARENT_ID => Oi::ATTR_PARENT_ID,
            self::A_DEPTH => Oi::ATTR_DEPTH,
            self::A_PATH => Oi::ATTR_PATH,
            self::A_PV => Oi::ATTR_PV,
            self::A_TV => Oi::ATTR_TV,
            self::A_OV_MAX => Oi::ATTR_OV_LEG_MAX,
            self::A_OV_SECOND => Oi::ATTR_OV_LEG_SECOND,
            self::A_OV_OTHER => Oi::ATTR_OV_LEG_OTHERS
        ];
        $result->from([$asTree => $tbl], $cols);

        /* LEFT JOIN prxgt_bon_base_rank */
        $tbl = $this->resource->getTableName(Rank::ENTITY_NAME);
        $cols = [
            self::A_RANK => Rank::ATTR_CODE
        ];
        $on = $asRank. '.' . Rank::ATTR_ID. '=' . $asTree . '.' . Oi::ATTR_RANK_ID;
        $result->joinLeft([$asRank => $tbl], $on, $cols);

        /* LEFT JOIN prxgt_dwnl_customer (for customer MLM ID) */
        $tbl = $this->resource->getTableName(Cust::ENTITY_NAME);
        $cols = [
            self::A_CUST_MLM_ID => Cust::ATTR_HUMAN_REF
        ];
        $on = $asCust . '.' . Cust::ATTR_CUSTOMER_ID . '=' . $asTree . '.' . Oi::ATTR_CUSTOMER_ID;
        $result->joinLeft([$asCust => $tbl], $on, $cols);

        /* LEFT JOIN prxgt_dwnl_customer (for parent MLM ID) */
        $tbl = $this->resource->getTableName(Cust::ENTITY_NAME);
        $cols = [
            self::A_PARENT_MLM_ID => Cust::ATTR_HUMAN_REF
        ];
        $on = $asPrnt . '.' . Cust::ATTR_CUSTOMER_ID . '=' . $asTree . '.' . Oi::ATTR_PARENT_ID;
        $result->joinLeft([$asPrnt => $tbl], $on, $cols);

        return $result;
    }

}