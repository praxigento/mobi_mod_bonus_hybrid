<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Entity\Retro\Downline;

use Praxigento\BonusHybrid\Repo\Data\Entity\Retro\Downline\Plain as Entity;

/**
 * Repository for retrospective data for plain downline reports (updated periodically).
 */
class Plain
    extends \Praxigento\Core\Repo\Def\Entity
{
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Core\Repo\IGeneric $repoGeneric
    ) {
        parent::__construct($resource, $repoGeneric, Entity::class);
    }

}