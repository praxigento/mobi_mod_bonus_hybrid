<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Retro\Compress;


use Praxigento\BonusHybrid\Api\Dcp\Report\Downline\Response\Entry as AReport;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Retro\Downline\Compressed\Phase1 as EPh1;
use Praxigento\Core\Repo\Query\Expression as Exp;
use Praxigento\Downline\Repo\Entity\Data\Customer as EDwnl;

/**
 * Build query to get DCP Downline Report data for retrospective compressed tree.
 * @deprecated see \Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Builder
 */
class Builder
    extends \Praxigento\Core\Repo\Query\Def\Builder
{
    /** Tables aliases */
    const AS_CUST = Cfg::ENTITY_MAGE_CUSTOMER;
    const AS_DWNL = EDwnl::ENTITY_NAME;
    const AS_PHASE1 = EPh1::ENTITY_NAME;

    /** Columns/expressions aliases */
    const A_COUNTRY = AReport::A_COUNTRY;
    const A_CUST_REF = AReport::A_CUSTOMER_REF;
    const A_DEPTH = AReport::A_DEPTH;
    const A_EMAIL = AReport::A_EMAIL;
    const A_MLM_ID = AReport::A_MLM_ID;
    const A_NAME_FIRST = AReport::A_NAME_FIRST;
    const A_NAME_LAST = AReport::A_NAME_LAST;
    const A_OV = AReport::A_OV;
    const A_PARENT_REF = AReport::A_PARENT_REF;
    const A_PATH = AReport::A_PATH;
    const A_PV = AReport::A_PV;
    const A_RANK_CODE = AReport::A_RANK_CODE;
    const A_TV = AReport::A_TV;
    const A_UNQ_MONTHS = AReport::A_UNQ_MONTHS;

    /** Bound variables names ('camelCase' naming) */
    const BIND_CALC_ID = 'calcId';

    public function build(\Magento\Framework\DB\Select $source = null)
    {
        /* this is primary query (started from SELECT) */
        $result = $this->conn->select();

        /* define tables aliases for internal usage (in this method) */
        $asCust = self::AS_CUST;
        $asDwnl = self::AS_DWNL;
        $asPhase1 = self::AS_PHASE1;

        /* FROM prxgt_bon_hyb_retro_cmprs_phase1 */
        $tbl = $this->resource->getTableName(EPh1::ENTITY_NAME);
        $as = $asPhase1;
        $cols = [
            self::A_CUST_REF => EPh1::ATTR_CUSTOMER_REF,
            self::A_DEPTH => EPh1::ATTR_DEPTH,
            self::A_OV => EPh1::ATTR_OV,
            self::A_PARENT_REF => EPh1::ATTR_PARENT_REF,
            self::A_PATH => EPh1::ATTR_PATH,
            self::A_PV => EPh1::ATTR_PV,
            self::A_RANK_CODE => new Exp('"TODO"'),
            self::A_TV => EPh1::ATTR_TV,
            self::A_UNQ_MONTHS => new Exp('"0"'),
        ];
        $result->from([$as => $tbl], $cols);

        /* LEFT JOIN prxgt_dwnl_customer */
        $tbl = $this->resource->getTableName(EDwnl::ENTITY_NAME);
        $as = $asDwnl;
        $cols = [
            self::A_COUNTRY => EDwnl::ATTR_COUNTRY_CODE,
            self::A_MLM_ID => EDwnl::ATTR_HUMAN_REF,
        ];
        $cond = $as . '.' . EDwnl::ATTR_CUSTOMER_ID . '=' . $asPhase1 . '.' . EPh1::ATTR_CUSTOMER_REF;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* LEFT JOIN customer_entity */
        $tbl = $this->resource->getTableName(Cfg::ENTITY_MAGE_CUSTOMER);
        $as = $asCust;
        $cols = [
            self::A_EMAIL => Cfg::E_CUSTOMER_A_EMAIL,
            self::A_NAME_FIRST => Cfg::E_CUSTOMER_A_FIRSTNAME,
            self::A_NAME_LAST => Cfg::E_CUSTOMER_A_LASTNAME,
        ];
        $cond = $as . '.' . Cfg::E_CUSTADDR_A_ENTITY_ID . '=' . $asDwnl . '.' . EDwnl::ATTR_CUSTOMER_ID;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        /* WHERE */
        $result->where($asPhase1 . '.' . EPh1::ATTR_CALC_ID . '=:' . self::BIND_CALC_ID);

        return $result;
    }


}