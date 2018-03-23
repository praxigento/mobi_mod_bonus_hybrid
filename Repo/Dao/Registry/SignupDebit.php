<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Dao\Registry;

use Praxigento\BonusHybrid\Repo\Data\Registry\SignupDebit as Entity;

class SignupDebit
    extends \Praxigento\Core\App\Repo\Def\Entity
{
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Core\App\Repo\IGeneric $daoGeneric
    ) {
        parent::__construct($resource, $daoGeneric, Entity::class);
    }

}