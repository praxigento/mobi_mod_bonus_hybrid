<?php

namespace Praxigento\BonusHybrid\Repo\Entity\Data\Registry;

/**
 * Registry for Sign Up Volume Bonus participants.
 *
 * User: Alex Gusev <alex@flancer64.com>
 */
class SignupDebit
    extends \Praxigento\Core\Data\Entity\Base
{
    const ATTR_CALC_REF = 'calc_ref';
    /*
     * @var string ATTR_CUSTOMER_REF
     */
    const ATTR_CUST_REF = 'cust_ref';
    /*
     * @var string ATTR_SALE_ORDER_REF
     */
    const ATTR_SALE_REF = 'sale_ref';
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
     * @return integer
     */
    public function getCustomerRef()
    {
        $result = parent::get(self::ATTR_CUST_REF);
        return $result;
    }

    public static function getPrimaryKeyAttrs()
    {
        $result = [self::ATTR_CALC_REF, self::ATTR_CUST_REF];
        return $result;
    }

    /**
     * @return integer
     */
    public function getSaleOrderRer()
    {
        $result = parent::get(self::ATTR_SALE_REF);
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
    public function setCustomerRef($data)
    {
        parent::set(self::ATTR_CUST_REF, $data);
    }

    /**
     * @param integer $data
     */
    public function setSaleOrderRer($data)
    {
        parent::set(self::ATTR_SALE_REF, $data);
    }
}