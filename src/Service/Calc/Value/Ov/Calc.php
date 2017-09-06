<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Value\Ov;

use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EDwnlBon;

/**
 * Calculate OV on the compressed downline tree.
 */
class Calc
{
    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\Traits\TMap {
        mapById as protected;
        mapByTeams as protected;
    }

    /**
     * Calculate TV for the downline tree.
     *
     * @param EDwnlBon[] $dwnlBonus
     * @return EDwnlBon[] updated tree (with TV)
     */
    public function exec($dwnlBonus)
    {
        $result = [];

        return $result;
    }
}