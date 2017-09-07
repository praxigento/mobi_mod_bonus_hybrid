<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Entity;

use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as Entity;

class Downline
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

    /**
     * Get downline tree by calculation ID.
     *
     * @param int $calcId
     * @return \Praxigento\BonusHybrid\Repo\Entity\Data\Downline[]
     */
    public function getByCalcId($calcId)
    {
        $where = Entity::ATTR_CALC_REF . '=' . (int)$calcId;
        $result = $this->get($where);
        return $result;
    }
}