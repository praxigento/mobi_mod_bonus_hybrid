<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Entity\Compression;

use Praxigento\BonusHybrid\Repo\Data\Entity\Compression\Oi as Entity;

class Oi
    extends \Praxigento\Core\Repo\Def\Entity
    implements \Praxigento\BonusHybrid\Repo\Entity\Compression\IOi
{
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Core\Repo\IGeneric $repoGeneric
    ) {
        parent::__construct($resource, $repoGeneric, Entity::class);
    }

}