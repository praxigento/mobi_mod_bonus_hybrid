<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report;

class Accounting
    extends \Praxigento\Core\Api\Processor\WithQuery
    implements \Praxigento\BonusHybrid\Api\Dcp\Report\AccountingInterface
{

    /** @var \Praxigento\Core\Api\IAuthenticator */
    private $authenticator;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Accounting\Builder $qbld,
        \Praxigento\Core\Helper\Config $hlpCfg,
        \Praxigento\Core\Api\IAuthenticator $authenticator
    )
    {
        parent::__construct($manObj, $qbld, $hlpCfg);
        $this->authenticator = $authenticator;
    }

    protected function authorize(\Praxigento\Core\Data $ctx)
    {
        // TODO: Implement authorize() method.
    }

    public function exec(\Praxigento\BonusHybrid\Api\Dcp\Report\Accounting\Request $data)
    {
        $result = parent::process($data);
        return $result;
    }

    protected function populateQuery(\Praxigento\Core\Data $ctx)
    {
        // TODO: Implement populateQuery() method.
    }

    protected function prepareQueryParameters(\Praxigento\Core\Data $ctx)
    {
        // TODO: Implement prepareQueryParameters() method.
    }

}