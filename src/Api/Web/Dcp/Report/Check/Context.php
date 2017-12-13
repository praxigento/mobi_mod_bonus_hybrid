<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check;

/**
 * Context for the process.
 */
class Context
    extends \Praxigento\Core\Data
{
    const CUSTOMER_ID = 'customerId';
    const DEF_STATE_ACTIVE = 'active';
    const DEF_STATE_FAILED = 'failed';
    const PERIOD = 'period';
    const QUERY_CUSTOMER = 'queryCustomer';
    const RESP_CUSTOMER = 'respCustomer';
    const RESP_SECTIONS = 'respSections';
    const STATE = 'state';
    const WEB_REQUEST = 'webRequest';
    const WEB_RESPONSE = 'webResponse';
    /** @var  \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Response\Body\Customer */
    public $respCustomer;
    /** @var  \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Response\Body\Sections */
    public $respSections;

    /** @var  string process state: [active|failed|success] */
    public $state;

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

    /**
     * @return \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Response\Body\Customer
     */
    public function getRespCustomer(): \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Response\Body\Customer
    {
        $result = $this->get(self::RESP_CUSTOMER);
        assert($result instanceof \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Response\Body\Customer);
        return $result;
    }

    /**
     * @return \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Response\Body\Sections
     */
    public function getRespSections(): \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Response\Body\Sections
    {
        $result = $this->get(self::RESP_SECTIONS);
        assert($result instanceof \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Response\Body\Sections);
        return $result;
    }

    /**
     * @return string
     */
    public function getState(): string
    {
        $result = (string)$this->get(self::STATE);
        return $result;
    }

    public function getWebRequest(): \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Request
    {
        $result = $this->get(self::WEB_REQUEST);
        return $result;
    }

    public function getWebResponse(): \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Response
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

    public function setState($data)
    {
        $this->set(self::STATE, $data);
    }

    public function setWebRequest(\Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Request $data)
    {
        $this->set(self::WEB_REQUEST, $data);
    }

    public function setWebResponse(\Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Response $data)
    {
        $this->set(self::WEB_RESPONSE, $data);
    }
}