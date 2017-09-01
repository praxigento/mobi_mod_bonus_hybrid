<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Data\Entity\Retro\Downline;

/**
 * Retrospective data for plain downline reports (updated periodically).
 *
 * @deprecated see \Praxigento\BonusHybrid\Repo\Entity\Data\Downline
 */
class Plain
    extends \Praxigento\Core\Data\Entity\Base
{
    const ATTR_CALC_REF = 'calc_ref';
    /*
    * @var string ATTR_CUSTOMER_REF
    */
    const ATTR_CUST_REF = 'cust_ref';
    const ATTR_OV = 'ov';
    const ATTR_PARENT_REF = 'parent_ref';
    const ATTR_PV = 'pv';
    const ATTR_RANK_CODE = 'rank_code';
    const ATTR_TV = 'tv';
    const ATTR_UNQ_MONTHS = 'unq_months';
    const ENTITY_NAME = 'prxgt_bon_hyb_retro_dwnl_plain';

    /**
     * @return integer
     */
    public function getCalcRef()
    {
        $result = parent::get(self::ATTR_CALC_REF);
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
     * @return integer
     */
    public function getCustomerRef()
    {
        $result = parent::get(self::ATTR_CUST_REF);
        return $result;
    }

    /**
     * @param integer $data
     */
    public function setCustomerRef($data)
    {
        parent::set(self::ATTR_CUST_REF, $data);
    }

    /**
     * @return integer
     */
    public function getOv()
    {
        $result = parent::get(self::ATTR_OV);
        return $result;
    }

    /**
     * @param integer $data
     */
    public function setOv($data)
    {
        parent::set(self::ATTR_OV, $data);
    }

    /**
     * @return integer
     */
    public function getParentRef()
    {
        $result = parent::get(self::ATTR_PARENT_REF);
        return $result;
    }

    /**
     * @param integer $data
     */
    public function setParentRef($data)
    {
        parent::set(self::ATTR_PARENT_REF, $data);
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
     * @param integer $data
     */
    public function setPv($data)
    {
        parent::set(self::ATTR_PV, $data);
    }

    /**
     * @return integer
     */
    public function getRankCode()
    {
        $result = parent::get(self::ATTR_RANK_CODE);
        return $result;
    }

    /**
     * @param integer $data
     */
    public function setRankCode($data)
    {
        parent::set(self::ATTR_RANK_CODE, $data);
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
    public function setTv($data)
    {
        parent::set(self::ATTR_TV, $data);
    }

    /**
     * @return integer
     */
    public function getUnqMonths()
    {
        $result = parent::get(self::ATTR_UNQ_MONTHS);
        return $result;
    }

    /**
     * @param integer $data
     */
    public function setUnqMonths($data)
    {
        parent::set(self::ATTR_UNQ_MONTHS, $data);
    }

    public static function getPrimaryKeyAttrs()
    {
        $result = [self::ATTR_CALC_REF, self::ATTR_CUST_REF];
        return $result;
    }
}