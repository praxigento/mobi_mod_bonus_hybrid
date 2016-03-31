<?php
namespace Praxigento\Bonus\Hybrid\Lib\Entity\Compression;
/**
 *  Downline Tree for compressed data to calculate Override and Infinity bonuses.
 * User: Alex Gusev <alex@flancer64.com>
 */
class Oi {
    const ATTR_CALC_ID = 'calc_id';
    const ATTR_CUSTOMER_ID = 'customer_id';
    const ATTR_OV_LEG_MAX = 'ov_leg_max';
    const ATTR_OV_LEG_SECOND = 'ov_leg_second';
    const ATTR_OV_LEG_SUMMARY = 'ov_leg_summary';
    const ATTR_PARENT_ID = 'parent_id';
    const ATTR_PV = 'pv';
    const ATTR_SCHEME = 'scheme';
    const ATTR_PV_INF = 'pv_inf';
    const ATTR_RANK_ID = 'rank_id';
    const ATTR_TV = 'tv';
    const ENTITY_NAME = 'prxgt_bon_hyb_cmprs_oi';
}