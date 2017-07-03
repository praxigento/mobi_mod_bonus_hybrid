<?php

namespace Praxigento\BonusHybrid\Repo\Data\Entity\Registry;

/**
 * Registry for Sign Up Volume Bonus participants.
 *
 * User: Alex Gusev <alex@flancer64.com>
 */
class SignupDebit
    extends \Praxigento\Core\Data\Entity\Base
{
    const ATTR_CALC_REF = 'calc_ref';
    const ATTR_CUSTOMER_REF = 'cust_ref';
    const ATTR_SALE_ORDER_REF = 'sale_ref';
    const ENTITY_NAME = 'prxgt_bon_hyb_reg_signup';

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
        $result = parent::get(self::ATTR_CUSTOMER_REF);
        return $result;
    }

    /**
     * @param integer $data
     */
    public function setCustomerRef($data)
    {
        parent::set(self::ATTR_CUSTOMER_REF, $data);
    }

    /**
     * @return integer
     */
    public function getSaleOrderRer()
    {
        $result = parent::get(self::ATTR_SALE_ORDER_REF);
        return $result;
    }

    /**
     * @param integer $data
     */
    public function setSaleOrderRer($data)
    {
        parent::set(self::ATTR_SALE_ORDER_REF, $data);
    }

    public function getPrimaryKeyAttrs()
    {
        $result = [self::ATTR_CALC_REF, self::ATTR_CUSTOMER_REF];
        return $result;
    }
}