<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Data;

/**
 * Downline tree data (plain & compressed, retro & actual).
 * Separate trees for the appropriate calculations.
 */
class Downline
    extends \Praxigento\Core\App\Repo\Data\Entity\Base
{
    /* names of the entity attributes (table columns) */
    const A_CALC_REF = 'calc_ref';
    const A_CUST_REF = 'cust_ref';
    const A_DEPTH = 'depth';
    const A_ID = 'id';
    const A_OV = 'ov';
    const A_PARENT_REF = 'parent_ref';
    const A_PATH = 'path';
    const A_PV = 'pv';
    const A_RANK_REF = 'rank_ref';
    const A_TV = 'tv';
    /** @deprecated */
    const A_UNQ_MONTHS = 'unq_months';
    /* entity (table) name */
    const ENTITY_NAME = 'prxgt_bon_hyb_dwnl';

    public function getCalculationRef()
    {
        $result = parent::get(self::A_CALC_REF);
        return $result;
    }

    public function getCustomerRef()
    {
        $result = parent::get(self::A_CUST_REF);
        return $result;
    }

    public function getDepth()
    {
        $result = parent::get(self::A_DEPTH);
        return $result;
    }

    public function getId()
    {
        $result = parent::get(self::A_ID);
        return $result;
    }

    public function getOv()
    {
        $result = parent::get(self::A_OV);
        return $result;
    }

    public function getParentRef()
    {
        $result = parent::get(self::A_PARENT_REF);
        return $result;
    }

    public function getPath()
    {
        $result = parent::get(self::A_PATH);
        return $result;
    }

    public static function getPrimaryKeyAttrs()
    {
        return [self::A_ID];
    }

    public function getPv()
    {
        $result = parent::get(self::A_PV);
        return $result;
    }

    public function getRankRef()
    {
        $result = parent::get(self::A_RANK_REF);
        return $result;
    }

    public function getTv()
    {
        $result = parent::get(self::A_TV);
        return $result;
    }

    /**
     * @deprecated
     */
    public function getUnqMonths()
    {
        $result = parent::get(self::A_UNQ_MONTHS);
        return $result;
    }

    public function setCalculationRef($data)
    {
        parent::set(self::A_CALC_REF, $data);
    }

    public function setCustomerRef($data)
    {
        parent::set(self::A_CUST_REF, $data);
    }

    public function setDepth($data)
    {
        parent::set(self::A_DEPTH, $data);
    }

    public function setId($data)
    {
        parent::set(self::A_ID, $data);
    }

    public function setOv($data)
    {
        parent::set(self::A_OV, $data);
    }

    public function setParentRef($data)
    {
        parent::set(self::A_PARENT_REF, $data);
    }

    public function setPath($data)
    {
        parent::set(self::A_PATH, $data);
    }

    public function setPv($data)
    {
        parent::set(self::A_PV, $data);
    }

    public function setRankRef($data)
    {
        parent::set(self::A_RANK_REF, $data);
    }

    public function setTv($data)
    {
        parent::set(self::A_TV, $data);
    }

    /**
     * @deprecated
     */
    public function setUnqMonths($data)
    {
        parent::set(self::A_UNQ_MONTHS, $data);
    }

}