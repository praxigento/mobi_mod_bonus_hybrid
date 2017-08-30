<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Actual\Plain;

use Praxigento\BonusHybrid\Api\Dcp\Report\Downline\Response\Entry as AReport;
use Praxigento\BonusHybrid\Repo\Data\Entity\Actual\Downline\Plain as EPlain;
use Praxigento\Downline\Repo\Entity\Data\Customer as ECust;

/**
 * Build query to get DCP Downline Report data for actual plain tree.
 */
class Builder
    extends \Praxigento\Core\Repo\Query\Def\Builder
{
    const AS_DWNL_CUST = 'dwnlCust';
    const AS_DWNL_PLAIN = 'dwnlPlain';

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


    public function build(\Magento\Framework\DB\Select $source = null)
    {
        $result = $this->conn->select(); // this is root builder
        /* define tables aliases */
        $asCust = self::AS_DWNL_CUST;
        $asPlain = self::AS_DWNL_PLAIN;
        /* select from prxgt_bon_hyb_cache_dwnl_plain */
        $tbl = $this->resource->getTableName(EPlain::ENTITY_NAME);
        $as = $asPlain;
        $cols = [
            self::A_CUST_REF => EPlain::ATTR_CUSTOMER_REF,
            self::A_DEPTH => EPlain::ATTR_DEPTH,
            self::A_EMAIL => EPlain::ATTR_EMAIL,
            self::A_MLM_ID => EPlain::ATTR_MLM_ID,
            self::A_NAME_FIRST => EPlain::ATTR_NAME_FIRST,
            self::A_NAME_LAST => EPlain::ATTR_NAME_LAST,
            self::A_OV => EPlain::ATTR_OV,
            self::A_PARENT_REF => EPlain::ATTR_PARENT_REF,
            self::A_PATH => EPlain::ATTR_PATH,
            self::A_PV => EPlain::ATTR_PV,
            self::A_RANK_CODE => EPlain::ATTR_RANK_CODE,
            self::A_TV => EPlain::ATTR_TV,
            self::A_UNQ_MONTHS => EPlain::ATTR_UNQ_MONTHS,
        ];
        $result->from([$as => $tbl], $cols);

        /* LEFT JOIN prxgt_dwnl_customer */
        $tbl = $this->resource->getTableName(ECust::ENTITY_NAME);
        $as = $asCust;
        $cols = [
            self::A_COUNTRY => ECust::ATTR_COUNTRY_CODE
        ];
        $cond = $asCust . '.' . ECust::ATTR_CUSTOMER_ID . '=' . $asPlain . '.' . EPlain::ATTR_CUSTOMER_REF;
        $result->joinLeft([$as => $tbl], $cond, $cols);

        return $result;
    }


}