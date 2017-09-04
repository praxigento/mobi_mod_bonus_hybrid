<?php

namespace Praxigento\BonusHybrid\Repo\Entity\Data\Compression;
/**
 * Downline Tree for compressed data (phase 2) to calculate Override and Infinity bonuses.
 *
 * User: Alex Gusev <alex@flancer64.com>
 */
class Oi
    extends \Praxigento\Core\Data\Entity\Base
{
    const ATTR_CALC_ID = 'calc_id';
    /*
     * @var string ATTR_CUSTOMER_ID
     */
    const ATTR_CUSTOMER_REF = 'customer_ref';
    const ATTR_DEPTH = 'depth';
    const ATTR_OV_LEG_MAX = 'ov_leg_max';
    const ATTR_OV_LEG_OTHERS = 'ov_leg_others';
    const ATTR_OV_LEG_SECOND = 'ov_leg_second';
    /*
     * @var string ATTR_PARENT_ID
     */
    const ATTR_PARENT_REF = 'parent_ref';
    const ATTR_PATH = 'path';
    const ATTR_PV = 'pv';
    const ATTR_PV_INF = 'pv_inf';
    const ATTR_RANK_ID = 'rank_id';
    const ATTR_SCHEME = 'scheme';
    const ATTR_TV = 'tv';
    const ENTITY_NAME = 'prxgt_bon_hyb_cmprs_oi';

    /**
     * @return integer
     */
    public function getCalcId()
    {
        $result = parent::get(self::ATTR_CALC_ID);
        return $result;
    }

    /**
     * @return integer
     */
    public function getCustomerId()
    {
        $result = parent::get(self::ATTR_CUSTOMER_REF);
        return $result;
    }

    /**
     * @return integer
     */
    public function getDepth()
    {
        $result = parent::get(self::ATTR_DEPTH);
        return $result;
    }

    /**
     * @return integer
     */
    public function getOvLegMax()
    {
        $result = parent::get(self::ATTR_OV_LEG_MAX);
        return $result;
    }

    /**
     * @return integer
     */
    public function getOvLegOthers()
    {
        $result = parent::get(self::ATTR_OV_LEG_OTHERS);
        return $result;
    }

    /**
     * @return integer
     */
    public function getOvLegSecond()
    {
        $result = parent::get(self::ATTR_OV_LEG_SECOND);
        return $result;
    }

    /**
     * @return integer
     */
    public function getParentId()
    {
        $result = parent::get(self::ATTR_PARENT_REF);
        return $result;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        $result = parent::get(self::ATTR_PATH);
        return $result;
    }

    /**
     * @return string
     */

    public static function getPrimaryKeyAttrs()
    {
        return [self::ATTR_CALC_ID, self::ATTR_CUSTOMER_REF, self::ATTR_SCHEME];
    }

    /**
     * @return integer
     */
    public function getPv()
    {
        $result = parent::get(self::ATTR_PV);
        return $result;
    }

    /**
     * @return integer
     */
    public function getPvInf()
    {
        $result = parent::get(self::ATTR_PV_INF);
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
     * @return integer
     */
    public function getTv()
    {
        $result = parent::get(self::ATTR_TV);
        return $result;
    }

    /**
     * @param integer $data
     */
    public function setCalcId($data)
    {
        parent::set(self::ATTR_CALC_ID, $data);
    }

    /**
     * @param integer $data
     */
    public function setCustomerId($data)
    {
        parent::set(self::ATTR_CUSTOMER_REF, $data);
    }

    /**
     * @param integer $data
     */
    public function setDepth($data)
    {
        parent::set(self::ATTR_DEPTH, $data);
    }

    /**
     * @param integer $data
     */
    public function setOvLegMax($data)
    {
        parent::set(self::ATTR_OV_LEG_MAX, $data);
    }

    /**
     * @param integer $data
     */
    public function setOvLegOthers($data)
    {
        parent::set(self::ATTR_OV_LEG_OTHERS, $data);
    }

    /**
     * @param integer $data
     */
    public function setOvLegSecond($data)
    {
        parent::set(self::ATTR_OV_LEG_SECOND, $data);
    }

    /**
     * @param integer $data
     */
    public function setParentId($data)
    {
        parent::set(self::ATTR_PARENT_REF, $data);
    }

    /**
     * @param string $data
     */
    public function setPath($data)
    {
        parent::set(self::ATTR_PATH, $data);
    }

    /**
     * @param integer $data
     */
    public function setPv($data)
    {
        parent::set(self::ATTR_PV, $data);
    }

    /**
     * @param integer $data
     */
    public function setPvInf($data)
    {
        parent::set(self::ATTR_PV_INF, $data);
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

    /**
     * @param integer $data
     */
    public function setTv($data)
    {
        parent::set(self::ATTR_TV, $data);
    }
}