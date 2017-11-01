<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response\Body\Sections\QualLegs;


class Qualification
    extends \Praxigento\Core\Data
{
    const A_MAX_LEG_CUST_ID = 'max_leg_cust_id';
    const A_MAX_LEG_OV = 'max_leg_ov';
    const A_MAX_LEG_QUAL = 'max_leg_qual';
    const A_OTHER_LEGS_OV = 'other_legs_ov';
    const A_OTHER_LEGS_QUAL = 'other_legs_qual';
    const A_SECOND_LEG_CUST_ID = 'second_leg_cust_id';
    const A_SECOND_LEG_OV = 'second_leg_ov';
    const A_SECOND_LEG_QUAL = 'second_leg_qual';

    /**
     * @return int
     */
    public function getMaxLegCustId()
    {
        $result = parent::get(self::A_MAX_LEG_CUST_ID);
        return $result;
    }

    /**
     * @return float
     */
    public function getMaxLegOv()
    {
        $result = parent::get(self::A_MAX_LEG_OV);
        return $result;
    }

    /**
     * @return float
     */
    public function getMaxLegQual()
    {
        $result = parent::get(self::A_MAX_LEG_QUAL);
        return $result;
    }

    /**
     * @return float
     */
    public function getOtherLegsOv()
    {
        $result = parent::get(self::A_OTHER_LEGS_OV);
        return $result;
    }

    /**
     * @return float
     */
    public function getOtherLegsQual()
    {
        $result = parent::get(self::A_OTHER_LEGS_QUAL);
        return $result;
    }

    /**
     * @return int
     */
    public function getSecondLegCust()
    {
        $result = parent::get(self::A_SECOND_LEG_CUST_ID);
        return $result;
    }

    /**
     * @return float
     */
    public function getSecondLegOv()
    {
        $result = parent::get(self::A_SECOND_LEG_OV);
        return $result;
    }

    /**
     * @return float
     */
    public function getSecondLegQual()
    {
        $result = parent::get(self::A_SECOND_LEG_QUAL);
        return $result;
    }

    public function setMaxLegCust($data)
    {
        parent::set(self::A_MAX_LEG_CUST_ID, $data);
    }

    public function setMaxLegOv($data)
    {
        parent::set(self::A_MAX_LEG_OV, $data);
    }

    public function setMaxLegQual($data)
    {
        parent::set(self::A_MAX_LEG_QUAL, $data);
    }

    public function setOtherLegsOv($data)
    {
        parent::set(self::A_OTHER_LEGS_OV, $data);
    }

    public function setOtherLegsQual($data)
    {
        parent::set(self::A_OTHER_LEGS_QUAL, $data);
    }

    public function setSecondLegCust($data)
    {
        parent::set(self::A_SECOND_LEG_CUST_ID, $data);
    }

    public function setSecondLegOv($data)
    {
        parent::set(self::A_SECOND_LEG_OV, $data);
    }

    public function setSecondLegQual($data)
    {
        parent::set(self::A_SECOND_LEG_QUAL, $data);
    }

}