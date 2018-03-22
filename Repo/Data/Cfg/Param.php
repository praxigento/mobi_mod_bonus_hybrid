<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Data\Cfg;


class Param
    extends \Praxigento\Core\App\Repo\Data\Entity\Base
{
    const ATTR_INFINITY = 'infinity';
    const ATTR_LEG_MAX = 'leg_max';
    const ATTR_LEG_MEDIUM = 'leg_medium';
    const ATTR_LEG_MIN = 'leg_min';
    const ATTR_QUALIFY_PV = 'qualify_pv';
    const ATTR_QUALIFY_TV = 'qualify_tv';
    const ATTR_RANK_ID = 'rank_id';
    const ATTR_SCHEME = 'scheme';
    const ENTITY_NAME = 'prxgt_bon_hyb_cfg_param';

    /**
     * @return integer
     */
    public function getInfinity()
    {
        $result = parent::get(self::ATTR_INFINITY);
        return $result;
    }

    /**
     * @return integer
     */
    public function getLegMax()
    {
        $result = parent::get(self::ATTR_LEG_MAX);
        return $result;
    }

    /**
     * @return integer
     */
    public function getLegMedium()
    {
        $result = parent::get(self::ATTR_LEG_MEDIUM);
        return $result;
    }

    /**
     * @return integer
     */
    public function getLegMin()
    {
        $result = parent::get(self::ATTR_LEG_MIN);
        return $result;
    }

    /**
     * @return array
     */
    public static function getPrimaryKeyAttrs()
    {
        return [self::ATTR_RANK_ID, self::ATTR_SCHEME];

    }

    /**
     * @return integer
     */
    public function getQualifyPv()
    {
        $result = parent::get(self::ATTR_QUALIFY_PV);
        return $result;
    }

    /**
     * @return integer
     */
    public function getQualifyTv()
    {
        $result = parent::get(self::ATTR_QUALIFY_TV);
        return $result;
    }

    /**
     * @return integer
     */
    public function getRankId()
    {
        $result = parent::get(self::ATTR_RANK_ID);
        return $result;
    }

    /**
     * @return string
     */
    public function getScheme()
    {
        $result = parent::get(self::ATTR_SCHEME);
        return $result;
    }

    /**
     * @param integer $data
     */
    public function setInfinity($data)
    {
        parent::set(self::ATTR_INFINITY, $data);
    }

    /**
     * @param integer $data
     */
    public function setLegMax($data)
    {
        parent::set(self::ATTR_LEG_MAX, $data);
    }

    /**
     * @param integer $data
     */
    public function setLegMedium($data)
    {
        parent::set(self::ATTR_LEG_MEDIUM, $data);
    }

    /**
     * @param integer $data
     */
    public function setLegMin($data)
    {
        parent::set(self::ATTR_LEG_MIN, $data);
    }

    /**
     * @param integer $data
     */
    public function setQualifyPv($data)
    {
        parent::set(self::ATTR_QUALIFY_PV, $data);
    }

    /**
     * @param integer $data
     */
    public function setQualifyTv($data)
    {
        parent::set(self::ATTR_QUALIFY_TV, $data);
    }

    /**
     * @param integer $data
     */
    public function setRankId($data)
    {
        parent::set(self::ATTR_RANK_ID, $data);
    }

    /**
     * @param string $data
     */
    public function setScheme($data)
    {
        parent::set(self::ATTR_SCHEME, $data);
    }
}