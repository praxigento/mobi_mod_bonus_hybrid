<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase2\Fun\Act\Qualify\Data;

/**
 * Output data for customer qualification.
 */
class Response
    extends \Praxigento\Core\Data
{
    const LEGS_ENTRY = 'legsEntry';
    const RANK_ID = 'rankId';

    /**
     * @return \Praxigento\BonusHybrid\Repo\Data\Compression\Phase2\Legs
     */
    public function getLegsEntry()
    {
        $result = parent::get(self::LEGS_ENTRY);
        return $result;
    }

    /**
     * @return int
     */
    public function getRankId()
    {
        $result = parent::get(self::RANK_ID);
        return $result;
    }

    public function setLegsEntry(\Praxigento\BonusHybrid\Repo\Data\Compression\Phase2\Legs $data)
    {
        parent::set(self::LEGS_ENTRY, $data);
    }

    /**
     * @param int $data
     */
    public function setRankId($data)
    {
        parent::set(self::RANK_ID, $data);
    }
}