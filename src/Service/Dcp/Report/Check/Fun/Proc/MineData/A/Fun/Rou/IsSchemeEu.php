<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Dcp\Report\Check\Fun\Proc\MineData\A\Fun\Rou;

use Praxigento\BonusHybrid\Config as Cfg;

/**
 * Return 'true' if customer belongs to EU scheme.
 */
class IsSchemeEu
{
    /** @var \Praxigento\BonusHybrid\Helper\IScheme */
    private $hlpScheme;
    /** @var \Praxigento\Downline\Repo\Entity\Customer */
    private $repoDwnlCust;

    public function __construct(
        \Praxigento\BonusHybrid\Helper\IScheme $hlpScheme,
        \Praxigento\Downline\Repo\Entity\Customer $repoDwnlCust
    )
    {
        $this->hlpScheme = $hlpScheme;
        $this->repoDwnlCust = $repoDwnlCust;
    }

    /**
     * Return 'true' if customer belongs to EU scheme.
     *
     * @param int $custId
     * @return bool
     */
    public function exec($custId)
    {
        $custData = $this->repoDwnlCust->getById($custId);
        $scheme = $this->hlpScheme->getSchemeByCustomer($custData);
        $result = ($scheme == Cfg::SCHEMA_EU);
        return $result;
    }
}