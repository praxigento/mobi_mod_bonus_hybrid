<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Dao\Cfg;

use Praxigento\BonusHybrid\Repo\Data\Cfg\Override as Entity;

class Override
    extends \Praxigento\Core\App\Repo\Def\Entity
{
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Core\App\Repo\IGeneric $daoGeneric
    )
    {
        parent::__construct($resource, $daoGeneric, Entity::class);
    }

}