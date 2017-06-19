<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Data\Agg\Dcp\Report\Downline;

use Praxigento\BonusHybrid\Entity\Cache\Downline\Plain as Plain;

/**
 * Common aggregate for DCP Downline report queries.
 */
class Entry
    extends \Flancer32\Lib\Data
{
    /**
     * Attribute names are the same as names in the "\Praxigento\BonusHybrid\Entity\Cache\Downline\Plain" entity.
     *
     * TODO: should we revert relation "agg=>entity" to "entity=>agg"???
     */
    const A_CUSTOMER_REF = Plain::ATTR_CUSTOMER_REF;
    const A_DEPTH = Plain::ATTR_DEPTH;
    const A_EMAIL = Plain::ATTR_EMAIL;
    const A_MLM_ID = Plain::ATTR_MLM_ID;
    const A_NAME = Plain::ATTR_NAME;
    const A_OV = Plain::ATTR_OV;
    const A_PARENT_REF = Plain::ATTR_PARENT_REF;
    const A_PATH = Plain::ATTR_PATH;
    const A_PV = Plain::ATTR_PV;
    const A_RANK_CODE = Plain::ATTR_RANK_CODE;
    const A_TV = Plain::ATTR_TV;
    const A_UNQ_MONTHS = Plain::ATTR_UNQ_MONTHS;
}