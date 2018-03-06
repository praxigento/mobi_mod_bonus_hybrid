<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Entity\Cfg;

use Praxigento\BonusHybrid\Repo\Entity\Data\Cfg\Param as Entity;

class Param
    extends \Praxigento\Core\App\Repo\Def\Entity
{
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Core\App\Repo\IGeneric $repoGeneric
    )
    {
        parent::__construct($resource, $repoGeneric, Entity::class);
    }

}