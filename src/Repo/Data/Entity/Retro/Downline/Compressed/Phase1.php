<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Data\Entity\Retro\Downline\Compressed;

/**
 * Retrospective Downline Tree that is Compressed in Phase 1
 */
class Phase1
    extends \Praxigento\Core\Data\Entity\Base
{
    const ATTR_CALC_ID = 'calc_id';
    const ATTR_CUSTOMER_ID = 'customer_ref';
    const ATTR_DEPTH = 'depth';
    const ATTR_OV = 'ov';
    const ATTR_PARENT_ID = 'parent_ref';
    const ATTR_PATH = 'path';
    const ATTR_PV = 'pv';
    const ATTR_TV = 'tv';
    const ENTITY_NAME = 'prxgt_bon_hyb_retro_cmprs_phase1';

    /**
     * @return integer
     */
    public function getCalcId()
    {
        $result = parent::get(self::ATTR_CALC_ID);
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
     * @return integer
     */
    public function getCustomerId()
    {
        $result = parent::get(self::ATTR_CUSTOMER_ID);
        return $result;
    }

    /**
     * @param integer $data
     */
    public function setCustomerId($data)
    {
        parent::set(self::ATTR_CUSTOMER_ID, $data);
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
     * @param integer $data
     */
    public function setDepth($data)
    {
        parent::set(self::ATTR_DEPTH, $data);
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
    public function getParentId()
    {
        $result = parent::get(self::ATTR_PARENT_ID);
        return $result;
    }

    /**
     * @param integer $data
     */
    public function setParentId($data)
    {
        parent::set(self::ATTR_PARENT_ID, $data);
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
     * @param string $data
     */
    public function setPath($data)
    {
        parent::set(self::ATTR_PATH, $data);
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

    public static function getPrimaryKeyAttrs()
    {
        $result = [self::ATTR_CALC_ID, self::ATTR_CUSTOMER_ID];
        return $result;
    }
}