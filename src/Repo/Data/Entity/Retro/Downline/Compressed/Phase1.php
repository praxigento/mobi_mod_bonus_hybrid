<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Data\Entity\Retro\Downline\Compressed;

/**
 * Retrospective Downline Tree that is Compressed in Phase 1
 */
class Phase1
    extends \Praxigento\Core\Data\Entity\Base
{
    const ATTR_CALC_ID = 'calc_id';
    const ATTR_CUSTOMER_ID = 'customer_ref';
    const ATTR_DEPTH = 'depth';
    const ATTR_OV = 'ov';
    const ATTR_PARENT_ID = 'parent_ref';
    const ATTR_PATH = 'path';
    const ATTR_PV = 'pv';
    const ATTR_TV = 'tv';
    const ENTITY_NAME = 'prxgt_bon_hyb_retro_cmprs_phase1';

    public function getPrimaryKeyAttrs()
    {
        $result = [self::ATTR_CALC_ID, self::ATTR_CUSTOMER_ID];
        return $result;
    }
}