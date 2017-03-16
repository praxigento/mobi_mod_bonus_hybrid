<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Repo\Query\Stats\Phase1;

use Praxigento\BonusHybrid\Entity\Compression\Ptc as Ptc;
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
    const AS_TREE = 'tree';

    /** Columns aliases */
    const A_CUST_ID = Ptc::ATTR_CUSTOMER_ID;
    const A_CUST_MLM_ID = 'customer_mlm_id';
    const A_DEPTH = Ptc::ATTR_DEPTH;
    const A_OV = Ptc::ATTR_OV;
    const A_PARENT_ID = Ptc::ATTR_PARENT_ID;
    const A_PARENT_MLM_ID = 'parent_mlm_id';
    const A_PATH = Ptc::ATTR_PATH;
    const A_PV = Ptc::ATTR_PV;
    const A_TV = Ptc::ATTR_TV;

    /** Bound variables names */
    const BIND_CALC_REF = 'calcRef';

    /**
     * SELECT
     * `tree`.`customer_id`,
     * `tree`.`parent_id`,
     * `tree`.`depth`,
     * `tree`.`path`,
     * `tree`.`pv`,
     * `tree`.`tv`,
     * `tree`.`ov`,
     * `cst`.`human_ref` AS `customer_mlm_id`,
     * `prn`.`human_ref` AS `parent_mlm_id`
     * FROM `prxgt_bon_hyb_cmprs_ptc` AS `tree`
     * LEFT JOIN `prxgt_dwnl_customer` AS `cst`
     * ON cst.customer_id = ph1.customer_id
     * LEFT JOIN `prxgt_dwnl_customer` AS `prn`
     * ON prn.customer_id = ph1.parent_id
     *
     * @inheritdoc
     */
    public function getSelectQuery(\Praxigento\Core\Repo\Query\IBuilder $qbuild = null)
    {
        $result = $this->conn->select(); // this is root query
        /* define tables aliases */
        $asTree = self::AS_TREE;
        $asCust = self::AS_CUST;
        $asPrnt = self::AS_PARENT;

        /* SELECT FROM prxgt_bon_hyb_cmprs_ptc */
        $tbl = $this->resource->getTableName(Ptc::ENTITY_NAME);
        $cols = [
            self::A_CUST_ID => Ptc::ATTR_CUSTOMER_ID,
            self::A_PARENT_ID => Ptc::ATTR_PARENT_ID,
            self::A_DEPTH => Ptc::ATTR_DEPTH,
            self::A_PATH => Ptc::ATTR_PATH,
            self::A_PV => Ptc::ATTR_PV,
            self::A_TV => Ptc::ATTR_TV,
            self::A_OV => Ptc::ATTR_OV
        ];
        $result->from([$asTree => $tbl], $cols);

        /* LEFT JOIN prxgt_dwnl_customer (for customer MLM ID) */
        $tbl = $this->resource->getTableName(Cust::ENTITY_NAME);
        $cols = [
            self::A_CUST_MLM_ID => Cust::ATTR_HUMAN_REF
        ];
        $on = $asCust . '.' . Cust::ATTR_CUSTOMER_ID . '=' . $asTree . '.' . Ptc::ATTR_CUSTOMER_ID;
        $result->joinLeft([$asCust => $tbl], $on, $cols);

        /* LEFT JOIN prxgt_dwnl_customer (for parent MLM ID) */
        $tbl = $this->resource->getTableName(Cust::ENTITY_NAME);
        $cols = [
            self::A_PARENT_MLM_ID => Cust::ATTR_HUMAN_REF
        ];
        $on = $asPrnt . '.' . Cust::ATTR_CUSTOMER_ID . '=' . $asTree . '.' . Ptc::ATTR_PARENT_ID;
        $result->joinLeft([$asPrnt => $tbl], $on, $cols);

        return $result;
    }

}