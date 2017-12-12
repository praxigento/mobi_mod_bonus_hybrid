<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Data\Response\Body\Sections\PersonalBonus;


class Item
    extends \Praxigento\Core\Data
{
    const A_AMOUNT = 'amount';
    const A_CUSTOMER = 'customer';
    const A_VOLUME = 'volume';

    /**
     * @return float
     */
    public function getAmount(): float
    {
        $result = parent::get(self::A_AMOUNT);
        return $result;
    }

    /**
     * @return \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Data\Response\Body\Customer
     */
    public function getCustomer(): \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Data\Response\Body\Customer
    {
        $result = parent::get(self::A_CUSTOMER);
        return $result;
    }

    /**
     * @return float
     */
    public function getVolume(): float
    {
        $result = parent::get(self::A_VOLUME);
        return $result;
    }

    public function setAmount($data)
    {
        parent::set(self::A_AMOUNT, $data);
    }

    public function setCustomer($data)
    {
        parent::set(self::A_CUSTOMER, $data);
    }

    public function setVolume($data)
    {
        parent::set(self::A_VOLUME, $data);
    }
}