<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast;

/**
 * Calculate TV/OV for plain downline report.
 */
class Calc
{

    const CTX_PLAIN_TREE = 'plainTree';

    public function __construct()
    {
    }

    /**
     * @param \Flancer32\Lib\Data $ctx
     */
    public function exec(\Flancer32\Lib\Data $ctx = null)
    {
        $result = [];
        /** @var \Praxigento\BonusHybrid\Entity\Cache\Downline\Plain[] $plainTree */
        $plainTree = $ctx->get(self::CTX_PLAIN_TREE);

        return $result;
    }
}