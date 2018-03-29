<?php

namespace Praxigento\BonusHybrid\Repo\Data\Registry;

/**
 * Registry for Sign Up Volume Bonus participants.
 *
 * User: Alex Gusev <alex@flancer64.com>
 */
class SignUpDebit
    extends \Praxigento\Core\App\Repo\Data\Entity\Base
{
    const A_CALC_REF = 'calc_ref';
    /*
     * @var string A_CUSTOMER_REF
     */
    const A_CUST_REF = 'cust_ref';
    /*
     * @var string A_SALE_ORDER_REF
     */
    const A_SALE_REF = 'sale_ref';
    const ENTITY_NAME = 'prxgt_bon_hyb_reg_signup';

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
     * @return integer
     */
    public function getSaleOrderRer()
    {
        $result = parent::get(self::A_SALE_REF);
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

    /**
     * @param integer $data
     */
    public function setSaleOrderRer($data)
    {
        parent::set(self::A_SALE_REF, $data);
    }
}