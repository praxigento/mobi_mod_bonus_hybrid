<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc;

use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Context as AContext;
use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Sections as DSections;
use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc\MineData\Customer as SubCustomer;
use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc\MineData\PersBonusSection as SubPersBonus;
use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc\MineData\QualificationLegs as SubQualLegs;
use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc\MineData\TeamBonusSection as SubTeamBonus;

/**
 * Process step to mine requested data from DB.
 */
class MineData
{
    /** @var \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc\MineData\Customer */
    private $subCustomer;
    /** @var \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc\MineData\PersBonusSection */
    private $subPersBonus;
    /** @var \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc\MineData\TeamBonusSection */
    private $subTeamBonus;
    /** @var \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc\MineData\QualificationLegs */
    private $subQualLegs;

    public function __construct(
        SubCustomer $subCustomer,
        SubPersBonus $subPersBonus,
        SubTeamBonus $subTeamBonus,
        SubQualLegs $subQualLegs
    )
    {
        $this->subCustomer = $subCustomer;
        $this->subPersBonus = $subPersBonus;
        $this->subTeamBonus = $subTeamBonus;
        $this->subQualLegs = $subQualLegs;
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

            /* put result data into context */
            $ctx->respCustomer = $customer;
            $sections = new DSections();
            $sections->setPersonalBonus($persBonus);
            $sections->setTeamBonus($teamBonus);
            $sections->setQualLegs($qualLegs);
            $ctx->respSections = $sections;
        }
        return $ctx;
    }
}