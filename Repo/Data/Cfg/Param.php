<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Data\Cfg;


class Param
    extends \Praxigento\Core\App\Repo\Data\Entity\Base
{
    const A_INFINITY = 'infinity';
    const A_LEG_MAX = 'leg_max';
    const A_LEG_MEDIUM = 'leg_medium';
    const A_LEG_MIN = 'leg_min';
    const A_QUALIFY_PV = 'qualify_pv';
    const A_QUALIFY_TV = 'qualify_tv';
    const A_RANK_ID = 'rank_id';
    const A_SCHEME = 'scheme';
    const ENTITY_NAME = 'prxgt_bon_hyb_cfg_param';

    /**
     * @return integer
     */
    public function getInfinity()
    {
        $result = parent::get(self::A_INFINITY);
        return $result;
    }

    /**
     * @return integer
     */
    public function getLegMax()
    {
        $result = parent::get(self::A_LEG_MAX);
        return $result;
    }

    /**
     * @return integer
     */
    public function getLegMedium()
    {
        $result = parent::get(self::A_LEG_MEDIUM);
        return $result;
    }

    /**
     * @return integer
     */
    public function getLegMin()
    {
        $result = parent::get(self::A_LEG_MIN);
        return $result;
    }

    /**
     * @return array
     */
    public static function getPrimaryKeyAttrs()
    {
        return [self::A_RANK_ID, self::A_SCHEME];

    }

    /**
     * @return integer
     */
    public function getQualifyPv()
    {
        $result = parent::get(self::A_QUALIFY_PV);
        return $result;
    }

    /**
     * @return integer
     */
    public function getQualifyTv()
    {
        $result = parent::get(self::A_QUALIFY_TV);
        return $result;
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
    public function setInfinity($data)
    {
        parent::set(self::A_INFINITY, $data);
    }

    /**
     * @param integer $data
     */
    public function setLegMax($data)
    {
        parent::set(self::A_LEG_MAX, $data);
    }

    /**
     * @param integer $data
     */
    public function setLegMedium($data)
    {
        parent::set(self::A_LEG_MEDIUM, $data);
    }

    /**
     * @param integer $data
     */
    public function setLegMin($data)
    {
        parent::set(self::A_LEG_MIN, $data);
    }

    /**
     * @param integer $data
     */
    public function setQualifyPv($data)
    {
        parent::set(self::A_QUALIFY_PV, $data);
    }

    /**
     * @param integer $data
     */
    public function setQualifyTv($data)
    {
        parent::set(self::A_QUALIFY_TV, $data);
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