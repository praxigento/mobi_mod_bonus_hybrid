<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data;

/**
 * Context for the process.
 */
class Context
    extends \Praxigento\Core\Data
{
    const CUSTOMER_ID = 'customerId';
    const PERIOD = 'period';
    const QUERY_CUSTOMER = 'queryCustomer';
    const WEB_REQUEST = 'webRequest';
    const WEB_RESPONSE = 'webResponse';

    /** @var  \Praxigento\Core\Repo\Query\Def\Select */
    public $queryCustomer;

    public function getCustomerId(): int
    {
        $result = (int)$this->get(self::CUSTOMER_ID);
        return $result;
    }

    public function getPeriod(): string
    {
        $result = (string)$this->get(self::PERIOD);
        return $result;
    }

    public function getWebRequest(): \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Request
    {
        $result = $this->get(self::WEB_REQUEST);
        return $result;
    }

    public function getWebResponse(): \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response
    {
        $result = $this->get(self::WEB_RESPONSE);
        return $result;
    }


    public function setCustomerId($data)
    {
        $this->set(self::CUSTOMER_ID, $data);
    }

    public function setPeriod($data)
    {
        $this->set(self::PERIOD, $data);
    }

    public function setWebRequest(\Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Request $data)
    {
        $this->set(self::WEB_REQUEST, $data);
    }

    public function setWebResponse(\Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response $data)
    {
        $this->set(self::WEB_RESPONSE, $data);
    }
}