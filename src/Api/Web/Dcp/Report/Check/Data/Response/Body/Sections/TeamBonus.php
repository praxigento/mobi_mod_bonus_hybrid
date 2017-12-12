<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Data\Response\Body\Sections;


class TeamBonus
    extends \Praxigento\Core\Data
{
    const A_ITEMS = 'items';
    const A_PERCENT = 'percent';
    const A_TOTAL_VOLUME = 'total_volume';

    /**
     * @return \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Data\Response\Body\Sections\TeamBonus\Item[]
     */
    public function getItems()
    {
        $result = parent::get(self::A_ITEMS);
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

    /**
     * @return float
     */
    public function getTotalVolume(): float
    {
        $result = parent::get(self::A_TOTAL_VOLUME);
        return $result;
    }

    public function setItems($data)
    {
        parent::set(self::A_ITEMS, $data);
    }

    public function setPercent($data)
    {
        parent::set(self::A_PERCENT, $data);
    }

    public function setTotalVolume($data)
    {
        parent::set(self::A_TOTAL_VOLUME, $data);
    }
}