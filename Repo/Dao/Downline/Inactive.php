<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Dao\Downline;

use Praxigento\BonusHybrid\Repo\Data\Downline\Inactive as Entity;

/**
 * Customers inactivity statistics.
 */
class Inactive
    extends \Praxigento\Core\App\Repo\Def\Entity
{
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Core\App\Repo\IGeneric $daoGeneric
    )
    {
        parent::__construct(
            $resource,
            $daoGeneric,
            Entity::class
        );
    }

}