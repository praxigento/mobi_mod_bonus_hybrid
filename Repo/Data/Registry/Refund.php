<?php

namespace Praxigento\BonusHybrid\Repo\Data\Registry;

/**
 * Registry for refund links (bonus amount will be transferred from wallet to wallet).
 *
 * User: Alex Gusev <alex@flancer64.com>
 */
class Refund
    extends \Praxigento\Core\App\Repo\Data\Entity\Base
{
    const A_CUST_FROM_REF = 'cust_from_ref';
    const A_CUST_TO_REF = 'cust_to_ref';

    const ENTITY_NAME = 'prxgt_bon_hyb_reg_refund';

    /**
     * @return integer
     */
    public function getCustToRef()
    {
        $result = parent::get(self::A_CUST_TO_REF);
        return $result;
    }

    /**
     * @return integer
     */
    public function getCustomerFromRef()
    {
        $result = parent::get(self::A_CUST_FROM_REF);
        return $result;
    }

    public static function getPrimaryKeyAttrs()
    {
        $result = [self::A_CUST_FROM_REF, self::A_CUST_TO_REF];
        return $result;
    }

    /**
     * @param integer $data
     */
    public function setCustFromRef($data)
    {
        parent::set(self::A_CUST_FROM_REF, $data);
    }

    /**
     * @param integer $data
     */
    public function setCustToRef($data)
    {
        parent::set(self::A_CUST_TO_REF, $data);
    }

}