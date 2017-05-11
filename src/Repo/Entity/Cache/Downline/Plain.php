<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Entity\Cache\Downline;

use Praxigento\BonusHybrid\Entity\Cache\Downline\Plain as Entity;

class Plain
    extends \Praxigento\Core\Repo\Def\Entity
    implements \Praxigento\BonusHybrid\Repo\Entity\Cache\Downline\IPlain
{
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Core\Repo\IGeneric $repoGeneric
    ) {
        parent::__construct($resource, $repoGeneric, Entity::class);
    }

}