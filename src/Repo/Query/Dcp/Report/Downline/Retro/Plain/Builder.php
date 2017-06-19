<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Retro\Plain;


/**
 * Build query to get DCP Downline Report data for retrospective plain tree.
 */
class Builder
    extends \Praxigento\Core\Repo\Query\Def\Builder
{
    const AS_DWNL_PLAIN = 'dwnlPlain';

    public function build(\Magento\Framework\DB\Select $source = null)
    {
        $result = $this->conn->select(); // this is root builder
        /* define tables aliases */
        $as = self::AS_DWNL_PLAIN;
        /* select from prxgt_bon_hyb_cache_dwnl_plain */
        $tbl = $this->resource->getTableName(\Praxigento\BonusHybrid\Entity\Cache\Downline\Plain::ENTITY_NAME);
        $result->from([$as => $tbl]);
        return $result;
    }


}