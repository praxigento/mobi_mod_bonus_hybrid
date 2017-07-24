<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Actual\Plain;

use Praxigento\BonusHybrid\Repo\Data\Entity\Actual\Downline\Plain as EPlain;

/**
 * Build query to get DCP Downline Report data for actual plain tree.
 */
class Builder
    extends \Praxigento\Core\Repo\Query\Def\Builder
{
    const AS_DWNL_PLAIN = 'dwnlPlain';

    const A_CUST_REF = EPlain::ATTR_CUSTOMER_REF;
    const A_DEPTH = EPlain::ATTR_DEPTH;
    const A_EMAIL = EPlain::ATTR_EMAIL;
    const A_MLM_ID = EPlain::ATTR_MLM_ID;
    const A_NAME_FIRST = EPlain::ATTR_NAME_FIRST;
    const A_NAME_LAST = EPlain::ATTR_NAME_LAST;
    const A_OV = EPlain::ATTR_OV;
    const A_PARENT_REF = EPlain::ATTR_PARENT_REF;
    const A_PATH = EPlain::ATTR_PATH;
    const A_PV = EPlain::ATTR_PV;
    const A_RANK_CODE = EPlain::ATTR_RANK_CODE;
    const A_TV = EPlain::ATTR_TV;
    const A_UNQ_MONTHS = EPlain::ATTR_UNQ_MONTHS;


    public function build(\Magento\Framework\DB\Select $source = null)
    {
        $result = $this->conn->select(); // this is root builder
        /* define tables aliases */
        $as = self::AS_DWNL_PLAIN;
        /* select from prxgt_bon_hyb_cache_dwnl_plain */
        $tbl = $this->resource->getTableName(EPlain::ENTITY_NAME);
        $result->from([$as => $tbl]);
        return $result;
    }


}