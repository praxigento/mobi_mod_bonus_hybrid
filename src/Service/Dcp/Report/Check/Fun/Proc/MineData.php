<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc;

use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Context as AContext;
use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Sections as DSections;
use Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\Customer as SubCustomer;
use Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\OverrideBonus as SubOverBonus;
use Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\PersBonus as SubPersBonus;
use Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\QualLegs as SubQualLegs;
use Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\TeamBonus as SubTeamBonus;

/**
 * Process step to mine requested data from DB.
 */
class MineData
{
    /** @var \Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\Customer */
    private $subCustomer;
    /** @var \Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\OverrideBonus */
    private $subOverBonus;
    /** @var \Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\PersBonus */
    private $subPersBonus;
    /** @var \Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\QualLegs */
    private $subQualLegs;
    /** @var \Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\TeamBonus */
    private $subTeamBonus;

    public function __construct(
        SubCustomer $subCustomer,
        SubOverBonus $subOverBonus,
        SubPersBonus $subPersBonus,
        SubQualLegs $subQualLegs,
        SubTeamBonus $subTeamBonus
    )
    {
        $this->subCustomer = $subCustomer;
        $this->subOverBonus = $subOverBonus;
        $this->subPersBonus = $subPersBonus;
        $this->subQualLegs = $subQualLegs;
        $this->subTeamBonus = $subTeamBonus;
    }

    public function exec(AContext $ctx): AContext
    {
        /* if current instance is active */
        if ($ctx->state == AContext::DEF_STATE_ACTIVE) {
            /* get step's local data from the context */
            $custId = $ctx->getCustomerId();
            $period = $ctx->getPeriod();

            /* perform processing */
            $customer = $this->subCustomer->exec($custId, $period);
            $persBonus = $this->subPersBonus->exec($custId, $period);
            $teamBonus = $this->subTeamBonus->exec($custId, $period);
            $qualLegs = $this->subQualLegs->exec($custId, $period);
            $overBonus = $this->subOverBonus->exec($custId, $period);

            /* put result data into context */
            $ctx->respCustomer = $customer;
            $sections = new DSections();
            $sections->setPersonalBonus($persBonus);
            $sections->setTeamBonus($teamBonus);
            $sections->setQualLegs($qualLegs);
            $sections->setOverBonus($overBonus);
            $ctx->respSections = $sections;
        }
        return $ctx;
    }
}