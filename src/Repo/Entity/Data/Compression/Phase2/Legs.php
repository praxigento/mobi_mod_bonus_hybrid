<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Entity\Data\Compression\Phase2;

/**
 *
 * Downline Tree for compressed data (phase 2) to calculate Override and Infinity bonuses.
 *
 */
class Legs
    extends \Praxigento\Core\Data\Entity\Base
{
    const ATTR_CALC_REF = 'calc_ref';
    const ATTR_CUST_REF = 'cust_ref';
    const ATTR_LEG_MAX = 'leg_max';
    const ATTR_LEG_OTHERS = 'leg_others';
    const ATTR_LEG_SECOND = 'leg_second';
    /** @deprecated only <10 customers have this value */
    const ATTR_PV_INF = 'pv_inf';
    const ENTITY_NAME = 'prxgt_bon_hyb_cmprs_ph2_legs';

    /**
     * @return integer
     */
    public function getCalcRef()
    {
        $result = parent::get(self::ATTR_CALC_REF);
        return $result;
    }

    /**
     * @return integer
     */
    public function getCustRef()
    {
        $result = parent::get(self::ATTR_CUST_REF);
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
    public function getLegOthers()
    {
        $result = parent::get(self::ATTR_LEG_OTHERS);
        return $result;
    }

    /**
     * @return integer
     */
    public function getLegSecond()
    {
        $result = parent::get(self::ATTR_LEG_SECOND);
        return $result;
    }

    /**
     * @return array
     */
    public static function getPrimaryKeyAttrs()
    {
        $result = [self::ATTR_CALC_REF, self::ATTR_CUST_REF];
        return $result;
    }

    /**
     * @return integer
     * @deprecated only <10 customers have this value
     */
    public function getPvInf()
    {
        $result = parent::get(self::ATTR_PV_INF);
        return $result;
    }

    /**
     * @param integer $data
     */
    public function setCalcRef($data)
    {
        parent::set(self::ATTR_CALC_REF, $data);
    }

    /**
     * @param integer $data
     */
    public function setCustRef($data)
    {
        parent::set(self::ATTR_CUST_REF, $data);
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
    public function setLegOthers($data)
    {
        parent::set(self::ATTR_LEG_OTHERS, $data);
    }

    /**
     * @param integer $data
     */
    public function setLegSecond($data)
    {
        parent::set(self::ATTR_LEG_SECOND, $data);
    }

    /**
     * @param integer $data
     * @deprecated only <10 customers have this value
     */
    public function setPvInf($data)
    {
        parent::set(self::ATTR_PV_INF, $data);
    }

}