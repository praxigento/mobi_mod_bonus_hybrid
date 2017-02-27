<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Calc\Traits;


trait TMap
{
    /**
     * Get depth index for Downline Tree ordered by depth desc.
     *
     * @param $tree
     * @param $labelCustId
     * @param $labelDepth
     *
     * @return array  [$depth => [$custId, ...]]
     */
    public function mapByTreeDepthDesc($tree, $labelCustId, $labelDepth)
    {
        $result = [];
        foreach ($tree as $one) {
            $customerId = $one[$labelCustId];
            $depth = $one[$labelDepth];
            if (!isset($result[$depth])) {
                $result[$depth] = [];
            }
            $result[$depth][] = $customerId;
        }
        /* sort by depth desc */
        krsort($result);
        return $result;
    }
}