<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Sections\QualificationLegs;


class Item
    extends \Praxigento\Core\Data
{
    const A_CUSTOMER = 'customer';
    const A_VOLUME = 'volume';
    const A_VOLUME_QUALIFIED = 'volume_qualified';

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
    public function getVolume(): float
    {
        $result = parent::get(self::A_VOLUME);
        return $result;
    }

    /**
     * @return float
     */
    public function getVolumeQualified(): float
    {
        $result = parent::get(self::A_VOLUME_QUALIFIED);
        return $result;
    }

    public function setCustomer($data)
    {
        parent::set(self::A_CUSTOMER, $data);
    }

    public function setVolume($data)
    {
        parent::set(self::A_VOLUME, $data);
    }

    public function setVolumeQualified($data)
    {
        parent::set(self::A_VOLUME_QUALIFIED, $data);
    }
}