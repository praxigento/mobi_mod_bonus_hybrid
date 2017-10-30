<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body;

use Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Sections\PersonalBonus as DPersonal;

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
     * @return \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Sections\PersonalBonus
     */
    public function getPersonalBonus(): DPersonal
    {
        $result = parent::get(self::A_PERSONAL_BONUS);
        return $result;
    }

    /**
     * @param DPersonal $data
     */
    public function setPersonalBonus(DPersonal $data)
    {
        parent::set(self::A_PERSONAL_BONUS, $data);
    }
}