<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2020
 */

namespace Praxigento\BonusHybrid\Repo\Data\Registry;

/**
 * Registry for customers been downgraded.
 */
class Downgrade
    extends \Praxigento\Core\App\Repo\Data\Entity\Base
{
    const A_CALC_REF = 'calc_ref';
    const A_CUST_REF = 'cust_ref';
    const ENTITY_NAME = 'prxgt_bon_hyb_reg_downgrade';

    /**
     * @return integer
     */
    public function getCalcRef()
    {
        $result = parent::get(self::A_CALC_REF);
        return $result;
    }

    /**
     * @return integer
     */
    public function getCustomerRef()
    {
        $result = parent::get(self::A_CUST_REF);
        return $result;
    }

    public static function getPrimaryKeyAttrs()
    {
        $result = [self::A_CALC_REF, self::A_CUST_REF];
        return $result;
    }


    /**
     * @param integer $data
     */
    public function setCalcRef($data)
    {
        parent::set(self::A_CALC_REF, $data);
    }

    /**
     * @param integer $data
     */
    public function setCustomerRef($data)
    {
        parent::set(self::A_CUST_REF, $data);
    }

}