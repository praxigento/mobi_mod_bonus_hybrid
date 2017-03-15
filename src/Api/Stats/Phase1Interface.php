<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Api\Stats;

/**
 * Get bonus related statistics (PV/TV/OV) for phase 1 compressed tree.
 */
interface Phase1Interface
{
    /**
     * @param \Praxigento\BonusHybrid\Api\Stats\Phase1\Request $data
     * @return \Praxigento\BonusHybrid\Api\Stats\Phase1\Response
     */
    public function exec(\Praxigento\BonusHybrid\Api\Stats\Phase1\Request $data);
}