<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Data\Cfg;


class Override
    extends \Praxigento\Core\App\Repo\Data\Entity\Base
{
    const A_GENERATION = 'generation';
    const A_PERCENT = 'percent';
    const A_RANK_ID = 'rank_id';
    const A_SCHEME = 'scheme';
    const ENTITY_NAME = 'prxgt_bon_hyb_cfg_override';

    /**
     * @return integer
     */
    public function getGeneration()
    {
        $result = parent::get(self::A_GENERATION);
        return $result;
    }

    /**
     * @return integer
     */
    public function getPercent()
    {
        $result = parent::get(self::A_PERCENT);
        return $result;
    }

    public static function getPrimaryKeyAttrs()
    {
        return [self::A_RANK_ID, self::A_SCHEME, self::A_GENERATION];

    }

    /**
     * @return integer
     */
    public function getRankId()
    {
        $result = parent::get(self::A_RANK_ID);
        return $result;
    }

    /**
     * @return string
     */
    public function getScheme()
    {
        $result = parent::get(self::A_SCHEME);
        return $result;
    }

    /**
     * @param integer $data
     */
    public function setGeneration($data)
    {
        parent::set(self::A_GENERATION, $data);
    }

    /**
     * @param float $data
     */
    public function setPercent($data)
    {
        parent::set(self::A_PERCENT, $data);
    }

    /**
     * @param integer $data
     */
    public function setRankId($data)
    {
        parent::set(self::A_RANK_ID, $data);
    }

    /**
     * @param string $data
     */
    public function setScheme($data)
    {
        parent::set(self::A_SCHEME, $data);
    }
}