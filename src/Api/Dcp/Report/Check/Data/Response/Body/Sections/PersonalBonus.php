<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Sections;


class PersonalBonus
    extends \Praxigento\Core\Data
{
    const A_COMPRESSED_VOLUME = 'compressed_volume';
    const A_ITEMS = 'items';
    const A_OWN_VOLUME = 'own_volume';
    const A_PERCENT = 'percent';

    /**
     * @return float
     */
    public function getCompressedVolume(): float
    {
        $result = parent::get(self::A_COMPRESSED_VOLUME);
        return $result;
    }

    /**
     * @return \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Sections\PersonalBonus\Item[]
     */
    public function getItems()
    {
        $result = parent::get(self::A_ITEMS);
        return $result;
    }

    /**
     * @return float
     */
    public function getOwnVolume(): float
    {
        $result = parent::get(self::A_OWN_VOLUME);
        return $result;
    }

    /**
     * @return float
     */
    public function getPercent(): float
    {
        $result = parent::get(self::A_PERCENT);
        return $result;
    }

    public function setCompressedVolume($data)
    {
        parent::set(self::A_COMPRESSED_VOLUME, $data);
    }

    public function setItems($data)
    {
        parent::set(self::A_ITEMS, $data);
    }

    public function setOwnVolume($data)
    {
        parent::set(self::A_OWN_VOLUME, $data);
    }

    public function setPercent($data)
    {
        parent::set(self::A_PERCENT, $data);
    }
}