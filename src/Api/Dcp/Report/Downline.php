<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report;

class Downline
    extends \Praxigento\BonusHybrid\Api\Stats\Base
    implements \Praxigento\BonusHybrid\Api\Dcp\Report\DownlineInterface
{
    public function exec(\Praxigento\BonusHybrid\Api\Dcp\Report\Downline\Request $data)
    {
        $result = parent::process($data);
        return $result;
    }

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\BonusHybrid\Repo\Query\Cache\Dwnl\Plain\Get\Builder $qbld,
        \Praxigento\Core\Api\IAuthenticator $authenticator,
        \Praxigento\Core\Tool\IPeriod $toolPeriod,
        \Praxigento\Downline\Repo\Entity\ISnap $repoSnap,
        \Praxigento\BonusHybrid\Api\Stats\Base\Query\GetLastCalc $qPeriodCalc
    ) {
        parent::__construct($manObj, $qbld, $authenticator, $toolPeriod, $repoSnap, $qPeriodCalc);
    }

    protected function populateQuery(\Flancer32\Lib\Data $ctx)
    {
        // TODO: Implement populateQuery() method.
    }


    protected function prepareCalcRefData(\Flancer32\Lib\Data $ctx)
    {
        // TODO: Implement prepareCalcRefData() method.
    }

}