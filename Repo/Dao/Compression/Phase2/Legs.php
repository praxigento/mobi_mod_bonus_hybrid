<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Dao\Compression\Phase2;

use Praxigento\BonusHybrid\Repo\Data\Compression\Phase2\Legs as Entity;

class Legs
    extends \Praxigento\Core\App\Repo\Dao
{
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Core\Api\App\Repo\Generic $daoGeneric
    )
    {
        parent::__construct($resource, $daoGeneric, Entity::class);
    }

}