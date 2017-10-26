<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Check\Proc;

use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Context as Ctx;
use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Db\Query\GetCustomer\Builder as QBGetCustomer;

/**
 * Process to execute DB queries, fetch result sets and compose request data.
 *
 */
class PerformQueries
{
    public function __construct()
    {

    }

    public function exec(Ctx $ctx): Ctx
    {
        $response = $ctx->getWebResponse();

        /* perform queries */
        $dataCustomer = $this->fetchCustomer($ctx->queryCustomer);

        /* compose result */
        $body = new \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body();
        $response->setData($body);
        $body->setPeriod('20170630');
        $body->setCustomer($dataCustomer);
        return $ctx;
    }

    private function fetchCustomer(\Magento\Framework\DB\Select $query)
    {
        /* prepare query & bind parameters */
        $bind = [
            QBGetCustomer::BND_ON_DATE => '20170630',
            QBGetCustomer::BND_CUST_ID => '8878'
        ];

        /* perform query and extract data from result set */
        $conn = $query->getConnection();
        $rs = $conn->fetchRow($query, $bind);

        $custId = $rs[QBGetCustomer::A_CUST_ID] ?? null;
        $mlmId = $rs[QBGetCustomer::A_MLM_ID] ?? null;
        $level = $rs[QBGetCustomer::A_DEPTH] ?? null;
        $nameFirst = $rs[QBGetCustomer::A_NAME_FIRST] ?? null;
        $nameLast = $rs[QBGetCustomer::A_NAME_LAST] ?? null;
        $name = "$nameFirst $nameLast";

        /* compose result */
        $result = new \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Customer();
        $result->setId($custId);
        $result->setMlmId($mlmId);
        $result->setLevel($level);
        $result->setName($name);
        return $result;
    }
}