<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Data\Entity\Retro\Downline;

/**
 * Retrospective data for plain downline reports (updated periodically).
 *
 * User: Alex Gusev <alex@flancer64.com>
 */
class Plain
    extends \Praxigento\Core\Data\Entity\Base
{
    const ATTR_CALC_REF = 'calc_ref';
    const ATTR_CUSTOMER_REF = 'cust_ref';
    const ATTR_OV = 'ov';
    const ATTR_PARENT_REF = 'parent_ref';
    const ATTR_PV = 'pv';
    const ATTR_TV = 'tv';
    const ENTITY_NAME = 'prxgt_bon_hyb_retro_dwnl_plain';

    public function getPrimaryKeyAttrs()
    {
        $result = [self::ATTR_CALC_REF, self::ATTR_CUSTOMER_REF];
        return $result;
    }
}