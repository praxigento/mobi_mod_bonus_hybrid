<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Repo\Query\Stats\Phase2;

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
    const AS_PHASE1 = 'ph1';

    /** Columns aliases */
    const A_CUST_ID = Oi::ATTR_CUSTOMER_ID;
    const A_CUST_MLM_ID = 'customer_mlm_id';
    const A_DEPTH = Oi::ATTR_DEPTH;
    const A_OV_MAX = Oi::ATTR_OV_LEG_MAX;
    const A_OV_SECOND = Oi::ATTR_OV_LEG_SECOND;
    const A_OV_OTHER = Oi::ATTR_OV_LEG_OTHERS;
    const A_PARENT_ID = Oi::ATTR_PARENT_ID;
    const A_PARENT_MLM_ID = 'parent_mlm_id';
    const A_PATH = Oi::ATTR_PATH;
    const A_PV = Oi::ATTR_PV;
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
        $asPtc = self::AS_PHASE1;
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
            self::A_OV_MAX => Oi::ATTR_OV
        ];
        $result->from([$asPtc => $tbl], $cols);

        /* LEFT JOIN prxgt_dwnl_customer (for customer MLM ID) */
        $tbl = $this->resource->getTableName(Cust::ENTITY_NAME);
        $cols = [
            self::A_CUST_MLM_ID => Cust::ATTR_HUMAN_REF
        ];
        $on = $asCust . '.' . Cust::ATTR_CUSTOMER_ID . '=' . $asPtc . '.' . Oi::ATTR_CUSTOMER_ID;
        $result->joinLeft([$asCust => $tbl], $on, $cols);

        /* LEFT JOIN prxgt_dwnl_customer (for parent MLM ID) */
        $tbl = $this->resource->getTableName(Cust::ENTITY_NAME);
        $cols = [
            self::A_PARENT_MLM_ID => Cust::ATTR_HUMAN_REF
        ];
        $on = $asPrnt . '.' . Cust::ATTR_CUSTOMER_ID . '=' . $asPtc . '.' . Oi::ATTR_PARENT_ID;
        $result->joinLeft([$asPrnt => $tbl], $on, $cols);

        return $result;
    }

}