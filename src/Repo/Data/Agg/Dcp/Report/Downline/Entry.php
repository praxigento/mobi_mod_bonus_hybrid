<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Data\Agg\Dcp\Report\Downline;

use Praxigento\BonusHybrid\Repo\Data\Entity\Actual\Downline\Plain as Plain;

/**
 * Common aggregate for DCP Downline report queries. Extends downline aggregate with bonus related attributes.
 */
class Entry
    extends \Praxigento\Downline\Repo\Data\Agg\Downline
{
    const A_OV = Plain::ATTR_OV;
    const A_PV = Plain::ATTR_PV;
    const A_RANK_CODE = Plain::ATTR_RANK_CODE;
    const A_TV = Plain::ATTR_TV;
    const A_UNQ_MONTHS = Plain::ATTR_UNQ_MONTHS;

    public function getOv()
    {
        $result = parent::get(self::A_OV);
        return $result;
    }

    public function getPv()
    {
        $result = parent::get(self::A_PV);
        return $result;
    }

    public function getRankCode()
    {
        $result = parent::get(self::A_RANK_CODE);
        return $result;
    }

    public function getTv()
    {
        $result = parent::get(self::A_TV);
        return $result;
    }

    public function getMonthsUnq()
    {
        $result = parent::get(self::A_UNQ_MONTHS);
        return $result;
    }

}