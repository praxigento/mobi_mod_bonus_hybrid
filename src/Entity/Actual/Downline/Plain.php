<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Entity\Actual\Downline;

/**
 * Actual data for plain downline reports (updated daily).
 */
class Plain
    extends \Praxigento\Core\Data\Entity\Base
{
    /**
     * Attribute names are the same as names in "\Praxigento\BonusHybrid\Repo\Data\Agg\Dcp\Report\Downline\Entry"
     */
    const ATTR_CUSTOMER_REF = 'customer_ref';
    const ATTR_DEPTH = 'depth';
    const ATTR_EMAIL = 'email';
    const ATTR_MLM_ID = 'mlm_id';
    const ATTR_NAME = 'name';
    const ATTR_OV = 'ov';
    const ATTR_PARENT_REF = 'parent_ref';
    const ATTR_PATH = 'path';
    const ATTR_PV = 'pv';
    const ATTR_RANK_CODE = 'rank_code';
    const ATTR_TV = 'tv';
    const ATTR_UNQ_MONTHS = 'unq_months';
    const ENTITY_NAME = 'prxgt_bon_hyb_act_dwnl_plain';

    public function getCustomerRef()
    {
        $result = parent::get(self::ATTR_CUSTOMER_REF);
        return $result;
    }

    public function getDepth()
    {
        $result = parent::get(self::ATTR_DEPTH);
        return $result;
    }

    public function getEmail()
    {
        $result = parent::get(self::ATTR_EMAIL);
        return $result;
    }

    public function getMlmId()
    {
        $result = parent::get(self::ATTR_MLM_ID);
        return $result;
    }

    public function getName()
    {
        $result = parent::get(self::ATTR_NAME);
        return $result;
    }

    public function getOv()
    {
        $result = parent::get(self::ATTR_OV);
        return $result;
    }

    public function getParentRef()
    {
        $result = parent::get(self::ATTR_PARENT_REF);
        return $result;
    }

    public function getPath()
    {
        $result = parent::get(self::ATTR_PATH);
        return $result;
    }

    public function getPrimaryKeyAttrs()
    {
        return [self::ATTR_CUSTOMER_REF];
    }

    public function getPv()
    {
        $result = parent::get(self::ATTR_PV);
        return $result;
    }

    public function getRankCode()
    {
        $result = parent::get(self::ATTR_RANK_CODE);
        return $result;
    }

    public function getTv()
    {
        $result = parent::get(self::ATTR_TV);
        return $result;
    }

    public function getUnqMonths()
    {
        $result = parent::get(self::ATTR_UNQ_MONTHS);
        return $result;
    }

    public function setCustomerRef($data)
    {
        parent::set(self::ATTR_CUSTOMER_REF, $data);
    }

    public function setDepth($data)
    {
        parent::set(self::ATTR_DEPTH, $data);
    }

    public function setEmail($data)
    {
        parent::set(self::ATTR_EMAIL, $data);
    }

    public function setMlmId($data)
    {
        parent::set(self::ATTR_MLM_ID, $data);
    }

    public function setName($data)
    {
        parent::set(self::ATTR_NAME, $data);
    }

    public function setOv($data)
    {
        parent::set(self::ATTR_OV, $data);
    }

    public function setParentRef($data)
    {
        parent::set(self::ATTR_PARENT_REF, $data);
    }

    public function setPath($data)
    {
        parent::set(self::ATTR_PATH, $data);
    }

    public function setPv($data)
    {
        parent::set(self::ATTR_PV, $data);
    }

    public function setRankCode($data)
    {
        parent::set(self::ATTR_RANK_CODE, $data);
    }

    public function setTv($data)
    {
        parent::set(self::ATTR_TV, $data);
    }

    public function setUnqMonths($data)
    {
        parent::set(self::ATTR_UNQ_MONTHS, $data);
    }

}