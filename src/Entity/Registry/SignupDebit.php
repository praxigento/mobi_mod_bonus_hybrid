<?php
namespace Praxigento\BonusHybrid\Entity\Registry;

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
    const ENTITY_NAME = 'prxgt_bon_hyb_reg_signup';

    public function getPrimaryKeyAttrs()
    {
        $result = [self::ATTR_CALC_REF, self::ATTR_CUSTOMER_REF];
        return $result;
    }
}