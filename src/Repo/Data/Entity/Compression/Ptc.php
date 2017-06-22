<?php
namespace Praxigento\BonusHybrid\Repo\Data\Entity\Compression;
/**
 * Downline Tree for compressed data to calculate Personal, Team and Courtesy bonuses.
 * User: Alex Gusev <alex@flancer64.com>
 */
class Ptc {
    const ATTR_CALC_ID = 'calc_id';
    const ATTR_CUSTOMER_ID = 'customer_id';
    const ATTR_DEPTH = 'depth';
    const ATTR_OV = 'ov';
    const ATTR_PARENT_ID = 'parent_id';
    const ATTR_PATH = 'path';
    const ATTR_PV = 'pv';
    const ATTR_TV = 'tv';
    const ENTITY_NAME = 'prxgt_bon_hyb_cmprs_ptc';
}