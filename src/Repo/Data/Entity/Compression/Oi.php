<?php
namespace Praxigento\BonusHybrid\Repo\Data\Entity\Compression;
/**
 * Downline Tree for compressed data (phase 2) to calculate Override and Infinity bonuses.
 *
 * User: Alex Gusev <alex@flancer64.com>
 */
class Oi
    extends \Praxigento\Core\Data\Entity\Base
{
    const ATTR_CALC_ID = 'calc_id';
    const ATTR_CUSTOMER_ID = 'customer_ref';
    const ATTR_DEPTH = 'depth';
    const ATTR_OV_LEG_MAX = 'ov_leg_max';
    const ATTR_OV_LEG_SECOND = 'ov_leg_second';
    const ATTR_OV_LEG_OTHERS = 'ov_leg_others';
    const ATTR_PARENT_ID = 'parent_ref';
    const ATTR_PATH = 'path';
    const ATTR_PV = 'pv';
    const ATTR_PV_INF = 'pv_inf';
    const ATTR_RANK_ID = 'rank_id';
    const ATTR_SCHEME = 'scheme';
    const ATTR_TV = 'tv';
    const ENTITY_NAME = 'prxgt_bon_hyb_cmprs_oi';

    public function getPrimaryKeyAttrs()
    {
        return [self::ATTR_CALC_ID, self::ATTR_CUSTOMER_ID, self::ATTR_SCHEME];
    }
}