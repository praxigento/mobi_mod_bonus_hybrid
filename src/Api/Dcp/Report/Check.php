<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report;

use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Request as Request;
use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response as Response;

class Check
    extends \Praxigento\Core\Api\Processor\WithQuery
    implements \Praxigento\BonusHybrid\Api\Dcp\Report\CheckInterface
{
    /** @var \Praxigento\Core\Api\IAuthenticator */
    private $authenticator;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\Core\Helper\Config $hlpCfg,
        \Praxigento\Core\Api\IAuthenticator $authenticator,
        \Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Accounting\Trans\Builder $qbDcpTrans
    )
    {
        parent::__construct($manObj, $qbDcpTrans, $hlpCfg);
        $this->authenticator = $authenticator;
    }

    protected function authorize(\Praxigento\Core\Data $ctx)
    {
        /* do nothing - in Production Mode current customer's ID is used as root customer ID */
    }

    protected function createQuerySelect(\Praxigento\Core\Data $ctx)
    {
        parent::createQuerySelect($ctx);
        /* add more query builders */


    }

    public function exec(Request $data): Response
    {
        $res = parent::process($data);
        $result = new Response($res->getData());
        return $result;
    }

    protected function performQuery(\Praxigento\Core\Data $ctx)
    {

    }

    protected function populateQuery(\Praxigento\Core\Data $ctx)
    {


    }

    protected function prepareQueryParameters(\Praxigento\Core\Data $ctx)
    {

    }

}