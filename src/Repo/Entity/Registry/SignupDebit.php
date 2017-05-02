<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Entity\Registry;

use Praxigento\BonusHybrid\Entity\Registry\SignupDebit as Entity;

class SignupDebit
    extends \Praxigento\Core\Repo\Def\Entity
    implements \Praxigento\BonusHybrid\Repo\Entity\Registry\ISignupDebit
{
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Core\Repo\IGeneric $repoGeneric
    ) {
        parent::__construct($resource, $repoGeneric, Entity::class);
    }

}