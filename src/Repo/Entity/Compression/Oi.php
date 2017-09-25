<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Entity\Compression;

use Praxigento\BonusHybrid\Repo\Entity\Data\Compression\Oi as Entity;

/**
 * @deprecated use \Praxigento\BonusHybrid\Repo\Entity\Downline
 */
class Oi
    extends \Praxigento\Core\Repo\Def\Entity
{
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Core\Repo\IGeneric $repoGeneric
    ) {
        parent::__construct($resource, $repoGeneric, Entity::class);
    }

}