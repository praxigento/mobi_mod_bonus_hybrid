<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Api\Stats;

/**
 * Get bonus related statistics (PV/TV/OV/Ranks) for phase 2 compressed tree.
 */
interface Phase2Interface
{
    /**
     * @param \Praxigento\BonusHybrid\Api\Stats\Phase2\Request $data
     * @return \Praxigento\BonusHybrid\Api\Stats\Phase2\Response
     */
    public function exec(\Praxigento\BonusHybrid\Api\Stats\Phase2\Request $data);
}