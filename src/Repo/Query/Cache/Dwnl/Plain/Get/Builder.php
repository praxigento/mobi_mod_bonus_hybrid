<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Query\Cache\Dwnl\Plain\Get;


/**
 * Build query to get cached data for forecast calculation (plain tree).
 */
class Builder
    extends \Praxigento\Core\Repo\Query\Def\Builder
{
    const AS_DWNL_PLAIN = 'dwnlPlain';

    public function getSelectQuery(\Praxigento\Core\Repo\Query\IBuilder $qbuild = null)
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