<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Dao\Downline;

use Praxigento\BonusHybrid\Repo\Data\Downline\Qualification as Entity;

/**
 * Customer qualification data for downline trees.
 */
class Qualification
    extends \Praxigento\Core\App\Repo\Dao
{
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Core\Api\App\Repo\Generic $daoGeneric
    )
    {
        parent::__construct(
            $resource,
            $daoGeneric,
            Entity::class
        );
    }

}