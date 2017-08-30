<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Query\Stats\Phase2;

use Praxigento\BonusBase\Repo\Entity\Data\Rank as Rank;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Entity\Compression\Oi as Oi;
use Praxigento\Core\Repo\Query\Expression as Expression;
use Praxigento\Downline\Repo\Entity\Data\Customer as Cust;

/**
 * Build query to get phase1 compressed PV/TV/OV statistics for the given calculation.
 */
class Builder
    extends \Praxigento\Core\Repo\Query\Def\Builder
{
    /** Tables aliases */
    const AS_CUST = 'cst';
    const AS_CUST_MAGE = 'mcst';
    const AS_PARENT = 'prn';
    const AS_RANK = 'rank';
    const AS_TREE = 'tree';

    /** Columns aliases */
    const A_CUST_ID = Oi::ATTR_CUSTOMER_REF;
    const A_CUST_MLM_ID = 'customer_mlm_id';
    const A_CUST_NAME = 'name';
    const A_DEPTH = Oi::ATTR_DEPTH;
    const A_OV_MAX = Oi::ATTR_OV_LEG_MAX;
    const A_OV_OTHER = Oi::ATTR_OV_LEG_OTHERS;
    const A_OV_SECOND = Oi::ATTR_OV_LEG_SECOND;
    const A_PARENT_ID = Oi::ATTR_PARENT_REF;
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
        $asCustM = self::AS_CUST_MAGE;
        $asPrnt = self::AS_PARENT;

        /* SELECT FROM prxgt_bon_hyb_cmprs_ptc */
        $tbl = $this->resource->getTableName(Oi::ENTITY_NAME);
        $as = $asTree;
        $cols = [
            self::A_CUST_ID => Oi::ATTR_CUSTOMER_REF,
            self::A_PARENT_ID => Oi::ATTR_PARENT_REF,
            self::A_DEPTH => Oi::ATTR_DEPTH,
            self::A_PATH => Oi::ATTR_PATH,
            self::A_PV => Oi::ATTR_PV,
            self::A_TV => Oi::ATTR_TV,
            self::A_OV_MAX => Oi::ATTR_OV_LEG_MAX,
            self::A_OV_SECOND => Oi::ATTR_OV_LEG_SECOND,
            self::A_OV_OTHER => Oi::ATTR_OV_LEG_OTHERS
        ];
        $result->from([$as => $tbl], $cols);

        /* LEFT JOIN customer_entity */
        $tbl = $this->resource->getTableName(Cfg::ENTITY_MAGE_CUSTOMER);
        $as = $asCustM;
        $fldFirst = "`$asCustM`.`" . Cfg::E_CUSTOMER_A_FIRSTNAME . "`";
        $fldLast = "`$asCustM`.`" . Cfg::E_CUSTOMER_A_LASTNAME . "`";
        $exp = new Expression("CONCAT($fldFirst, ' ', $fldLast)");
        $cols = [
            self::A_CUST_NAME => $exp
        ];
        $on = $asCustM . '.' . Cfg::E_CUSTOMER_A_ENTITY_ID . '=' . $asTree . '.' . Oi::ATTR_CUSTOMER_REF;
        $result->joinLeft([$as => $tbl], $on, $cols);

        /* LEFT JOIN prxgt_bon_base_rank */
        $tbl = $this->resource->getTableName(Rank::ENTITY_NAME);
        $as = $asRank;
        $cols = [
            self::A_RANK => Rank::ATTR_CODE
        ];
        $on = $asRank . '.' . Rank::ATTR_ID . '=' . $asTree . '.' . Oi::ATTR_RANK_ID;
        $result->joinLeft([$as => $tbl], $on, $cols);

        /* LEFT JOIN prxgt_dwnl_customer (for customer MLM ID) */
        $tbl = $this->resource->getTableName(Cust::ENTITY_NAME);
        $as = $asCust;
        $cols = [
            self::A_CUST_MLM_ID => Cust::ATTR_HUMAN_REF
        ];
        $on = $asCust . '.' . Cust::ATTR_CUSTOMER_ID . '=' . $asTree . '.' . Oi::ATTR_CUSTOMER_REF;
        $result->joinLeft([$as => $tbl], $on, $cols);

        /* LEFT JOIN prxgt_dwnl_customer (for parent MLM ID) */
        $tbl = $this->resource->getTableName(Cust::ENTITY_NAME);
        $as = $asPrnt;
        $cols = [
            self::A_PARENT_MLM_ID => Cust::ATTR_HUMAN_REF
        ];
        $on = $asPrnt . '.' . Cust::ATTR_CUSTOMER_ID . '=' . $asTree . '.' . Oi::ATTR_PARENT_REF;
        $result->joinLeft([$as => $tbl], $on, $cols);

        return $result;
    }

}