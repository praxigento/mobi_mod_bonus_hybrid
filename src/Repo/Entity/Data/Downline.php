<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Entity\Data;

/**
 * Downline tree data (plain & compressed, retro & actual).
 * Separate trees for the appropriate calculations.
 */
class Downline
    extends \Praxigento\Core\Data\Entity\Base
{
    /* names of the entity attributes (table columns) */
    const ATTR_CALC_REF = 'calc_ref';
    const ATTR_CUST_REF = 'cust_ref';
    const ATTR_DEPTH = 'depth';
    const ATTR_ID = 'id';
    const ATTR_OV = 'ov';
    const ATTR_PARENT_REF = 'parent_ref';
    const ATTR_PATH = 'path';
    const ATTR_PV = 'pv';
    const ATTR_RANK_REF = 'rank_ref';
    const ATTR_TV = 'tv';
    const ATTR_UNQ_MONTHS = 'unq_months';
    /* entity (table) name */
    const ENTITY_NAME = 'prxgt_bon_hyb_dwnl';

    public function getCalculationRef()
    {
        $result = parent::get(self::ATTR_CALC_REF);
        return $result;
    }

    public function getCustomerRef()
    {
        $result = parent::get(self::ATTR_CUST_REF);
        return $result;
    }

    public function getDepth()
    {
        $result = parent::get(self::ATTR_DEPTH);
        return $result;
    }

    public function getId()
    {
        $result = parent::get(self::ATTR_ID);
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

    /**
     * Complex primary key for the entity (calc_ref & cust_ref).
     *
     * @inheritdoc
     */
    public static function getPrimaryKeyAttrs()
    {
        return [self::ATTR_ID];
    }

    public function getPv()
    {
        $result = parent::get(self::ATTR_PV);
        return $result;
    }

    public function getRankRef()
    {
        $result = parent::get(self::ATTR_RANK_REF);
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

    public function setCalculationRef($data)
    {
        parent::set(self::ATTR_CALC_REF, $data);
    }

    public function setCustomerRef($data)
    {
        parent::set(self::ATTR_CUST_REF, $data);
    }

    public function setDepth($data)
    {
        parent::set(self::ATTR_DEPTH, $data);
    }

    public function setId($data)
    {
        parent::set(self::ATTR_ID, $data);
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

    public function setRankRef($data)
    {
        parent::set(self::ATTR_RANK_REF, $data);
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