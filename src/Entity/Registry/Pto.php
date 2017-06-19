<?php
namespace Praxigento\BonusHybrid\Entity\Registry;

/**
 * Registry for Pv/Tv/Ov values for plain (not compressed) downline.
 *
 * User: Alex Gusev <alex@flancer64.com>
 */
class Pto
    extends \Praxigento\Core\Data\Entity\Base
{
    const ATTR_CALC_REF = 'calc_ref';
    const ATTR_CUSTOMER_REF = 'cust_ref';
    const ATTR_OV = 'ov';
    const ATTR_PARENT_REF = 'parent_ref';
    const ATTR_PV = 'pv';
    const ATTR_TV = 'tv';
    const ENTITY_NAME = 'prxgt_bon_hyb_reg_pto';

    public function getPrimaryKeyAttrs()
    {
        $result = [self::ATTR_CALC_REF, self::ATTR_CUSTOMER_REF];
        return $result;
    }
}