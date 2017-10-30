<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response;

/**
 * This is Magento API data object, so we need to declare get/set methods explicitly.
 */
class Body
    extends \Praxigento\Core\Data
{
    const A_CUSTOMER = 'customer';
    const A_PERIOD = 'period';
    const A_SECTIONS = 'sections';

    /**
     * @return \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Customer
     */
    public function getCustomer(): \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Customer
    {
        $result = parent::get(self::A_CUSTOMER);
        return $result;
    }

    /**
     * @return string
     */
    public function getPeriod(): string
    {
        $result = parent::get(self::A_PERIOD);
        return $result;
    }

    /**
     * @return \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Sections
     */
    public function getSections(): \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Sections
    {
        $result = parent::get(self::A_SECTIONS);
        return $result;
    }

    /**
     * @param \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Customer $data
     */
    public function setCustomer($data)
    {
        parent::set(self::A_CUSTOMER, $data);
    }

    public function setPeriod(string $data)
    {
        parent::set(self::A_PERIOD, $data);
    }

    /**
     * @param  \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Sections $data
     */
    public function setSections($data)
    {
        parent::set(self::A_SECTIONS, $data);
    }
}