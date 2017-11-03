<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc;

use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Context as AContext;
use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Sections as DSections;
use Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\Customer as SubCustomer;
use Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\InfinityBonus as SubInfBonus;
use Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\OverrideBonus as SubOverBonus;
use Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\PersBonus as SubPersBonus;
use Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\QualLegs as SubQualLegs;
use Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\TeamBonus as SubTeamBonus;
use Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\Totals as SubTotals;

/**
 * Process step to mine requested data from DB.
 */
class MineData
{
    /** @var \Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\Customer */
    private $subCustomer;
    /** @var \Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\InfinityBonus */
    private $subInfBonus;
    /** @var \Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\OverrideBonus */
    private $subOverBonus;
    /** @var \Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\PersBonus */
    private $subPersBonus;
    /** @var \Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\QualLegs */
    private $subQualLegs;
    /** @var \Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\TeamBonus */
    private $subTeamBonus;
    /** @var \Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\Totals */
    private $subTotals;

    public function __construct(
        SubCustomer $subCustomer,
        SubInfBonus $subInfBonus,
        SubOverBonus $subOverBonus,
        SubPersBonus $subPersBonus,
        SubQualLegs $subQualLegs,
        SubTeamBonus $subTeamBonus,
        SubTotals $subTotals
    )
    {
        $this->subCustomer = $subCustomer;
        $this->subInfBonus = $subInfBonus;
        $this->subOverBonus = $subOverBonus;
        $this->subPersBonus = $subPersBonus;
        $this->subQualLegs = $subQualLegs;
        $this->subTeamBonus = $subTeamBonus;
        $this->subTotals = $subTotals;
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
            $infBonus = $this->subInfBonus->exec($custId, $period);
            $totals = $this->subTotals->exec($custId, $period);

            /* put result data into context */
            $ctx->respCustomer = $customer;
            $sections = new DSections();
            $sections->setPersonalBonus($persBonus);
            $sections->setTeamBonus($teamBonus);
            $sections->setQualLegs($qualLegs);
            $sections->setOverBonus($overBonus);
            $sections->setInfBonus($infBonus);
            $sections->setTotals($totals);
            $ctx->respSections = $sections;
        }
        return $ctx;
    }
}