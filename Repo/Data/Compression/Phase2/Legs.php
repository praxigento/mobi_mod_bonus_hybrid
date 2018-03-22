<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Data\Compression\Phase2;

/**
 *
 * Downline Tree for compressed data (phase 2) to calculate Override and Infinity bonuses.
 *
 */
class Legs
    extends \Praxigento\Core\App\Repo\Data\Entity\Base
{
    const ATTR_CALC_REF = 'calc_ref';
    const ATTR_CUST_MAX_REF = 'cust_max_ref';
    const ATTR_CUST_REF = 'cust_ref';
    const ATTR_CUST_SECOND_REF = 'cust_second_ref';
    const ATTR_LEG_MAX = 'leg_max';
    const ATTR_LEG_OTHERS = 'leg_others';
    const ATTR_LEG_SECOND = 'leg_second';
    const ATTR_PV_QUAL_MAX = 'pv_qual_max';
    const ATTR_PV_QUAL_OTHER = 'pv_qual_other';
    const ATTR_PV_QUAL_SECOND = 'pv_qual_second';
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
    public function getCustMaxRef()
    {
        $result = parent::get(self::ATTR_CUST_MAX_REF);
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
    public function getCustSecondRef()
    {
        $result = parent::get(self::ATTR_CUST_SECOND_REF);
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
     * @return float
     */
    public function getPvQualMax()
    {
        $result = parent::get(self::ATTR_PV_QUAL_MAX);
        return $result;
    }

    /**
     * @return float
     */
    public function getPvQualOther()
    {
        $result = parent::get(self::ATTR_PV_QUAL_OTHER);
        return $result;
    }

    /**
     * @return float
     */
    public function getPvQualSecond()
    {
        $result = parent::get(self::ATTR_PV_QUAL_SECOND);
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
    public function setCustMaxRef($data)
    {
        parent::set(self::ATTR_CUST_MAX_REF, $data);
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
    public function setCustSecondRef($data)
    {
        parent::set(self::ATTR_CUST_SECOND_REF, $data);
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
     * @param float $data
     */
    public function setPvQualMax($data)
    {
        parent::set(self::ATTR_PV_QUAL_MAX, $data);
    }

    /**
     * @param float $data
     */
    public function setPvQualOther($data)
    {
        parent::set(self::ATTR_PV_QUAL_OTHER, $data);
    }

    /**
     * @param float $data
     */
    public function setPvQualSecond($data)
    {
        parent::set(self::ATTR_PV_QUAL_SECOND, $data);
    }

}