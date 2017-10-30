<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Check\Fun\Proc\MineData\PersBonusSection\Data;

class Response
    extends \Praxigento\Core\Data
{
    const A_SECTION_DATA = 'sectionData';

    /**
     * @return \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Sections\PersonalBonus
     */
    public function getSectionData()
    {
        $result = parent::get(self::A_SECTION_DATA);
        return $result;
    }

    /**
     * @param \Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Sections\PersonalBonus $data
     */
    public function setSectionData($data)
    {
        parent::set(self::A_SECTION_DATA, $data);
    }
}