<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Sections\OverBonus;


class Item
    extends \Praxigento\Core\Data
{

    const A_AMOUNT = 'amount';
    const A_CUSTOMER = 'customer';
    const A_PERCENT = 'percent';
    const A_RANK = 'rank';
    const A_VOLUME = 'volume';

    /**
     * @return float
     */
    public function getAmount()
    {
        $result = parent::get(self::A_AMOUNT);
        return $result;
    }

    /**
     * @return \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Customer
     */
    public function getCustomer(): \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Customer
    {
        $result = parent::get(self::A_CUSTOMER);
        return $result;
    }

    /**
     * @return float
     */
    public function getPercent()
    {
        $result = parent::get(self::A_PERCENT);
        return $result;
    }

    /**
     * @return string
     */
    public function getRank()
    {
        $result = parent::get(self::A_RANK);
        return $result;
    }

    /**
     * @return float
     */
    public function getVolume()
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

    public function setPercent($data)
    {
        parent::set(self::A_PERCENT, $data);
    }

    public function setRank($data)
    {
        parent::set(self::A_RANK, $data);
    }

    public function setVolume($data)
    {
        parent::set(self::A_VOLUME, $data);
    }
}