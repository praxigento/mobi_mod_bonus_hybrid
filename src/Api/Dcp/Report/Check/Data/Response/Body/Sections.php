<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body;


class Sections
    extends \Praxigento\Core\Data
{
    const A_INFINITY_BONUS = 'infinity_bonus';
    const A_ORG_PROFILE = 'org_profile';
    const A_OVERRIDE_BONUS = 'override_bonus';
    const A_PERSONAL_BONUS = 'personal_bonus';
    const A_QUAL_LEGS = 'qual_legs';
    const A_TEAM_BONUS = 'team_bonus';
    const A_TOTALS = 'totals';

    /**
     * @return int
     */
    public function getPersonalBonus(): int
    {
        $result = parent::get(self::A_PERSONAL_BONUS);
        return $result;
    }

    public function setPersonalBonus($data)
    {
        parent::set(self::A_PERSONAL_BONUS, $data);
    }
}