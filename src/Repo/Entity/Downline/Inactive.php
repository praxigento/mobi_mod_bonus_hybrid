<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Entity\Downline;

use Praxigento\BonusHybrid\Repo\Entity\Data\Downline\Inactive as Entity;

/**
 * Customers inactivity statistics.
 */
class Inactive
    extends \Praxigento\Core\Repo\Def\Entity
{
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Core\Repo\IGeneric $repoGeneric
    )
    {
        parent::__construct(
            $resource,
            $repoGeneric,
            Entity::class
        );
    }

}