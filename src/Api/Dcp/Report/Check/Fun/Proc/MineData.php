<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc;

use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Context as AContext;
use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Sections as DSections;

/**
 * Process step to mine requested data from DB.
 */
class MineData
{
    /** @var \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc\MineData\PersBonusSection */
    private $actBuildPersBonus;
    private $subCustomer;

    public function __construct(
        \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc\MineData\PersBonusSection $actBuildPersBonus,
        \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc\MineData\Customer $subCustomer
    )
    {
        $this->actBuildPersBonus = $actBuildPersBonus;
        $this->subCustomer = $subCustomer;
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
            $persBonus = $this->sectionPersonalBonus($custId, $period);

            /* put result data into context */
            $ctx->respCustomer = $customer;
            $sections = new DSections();
            $sections->setPersonalBonus($persBonus);
            $ctx->respSections = $sections;
        }
        return $ctx;
    }

    /**
     * @param int $custId
     * @param string $period
     * @return \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Sections\PersonalBonus
     */
    private function sectionPersonalBonus(int $custId, string $period)
    {
        $req = new \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc\MineData\PersBonusSection\Data\Request();
        $req->setCustomerId($custId);
        $req->setPeriod($period);
        $resp = $this->actBuildPersBonus->exec($req);
        $result = $resp->getSectionData();
        return $result;
    }
}