<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Entity\Registry;

use Praxigento\BonusHybrid\Entity\Registry\Pto as Entity;

class Pto
    extends \Praxigento\Core\Repo\Def\Entity
    implements \Praxigento\BonusHybrid\Repo\Entity\Registry\IPto
{
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Core\Repo\IGeneric $repoGeneric
    ) {
        parent::__construct($resource, $repoGeneric, Entity::class);
    }

}