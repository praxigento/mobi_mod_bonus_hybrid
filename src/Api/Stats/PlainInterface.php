<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Api\Stats;

/**
 * Get bonus related statistics (PV/TV/OV) for plain (not compressed) tree.
 */
interface PlainInterface
{
    /**
     * @param \Praxigento\BonusHybrid\Api\Stats\Plain\Request $data
     * @return \Praxigento\BonusHybrid\Api\Stats\Plain\Response
     */
    public function exec(\Praxigento\BonusHybrid\Api\Stats\Plain\Request $data);
}