<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Calc\Traits;


trait TMap
{
    /**
     * Convert array of data ([ 0 => [ 'id' => 321, ... ], ...]) to mapped array ([ 321 => [ 'id'=>321, ... ], ... ]).
     *
     * @param $data
     * @param $labelId
     *
     * @return array
     */
    public function mapById($data, $labelId)
    {
        $result = [];
        foreach ($data as $one) {
            $result[$one[$labelId]] = $one;
        }
        return $result;
    }

    /**
     * Create map of the front team members (siblings) [$custId => [$memberId, ...], ...] from compressed or snapshot
     * data.
     *
     * @param $data
     *
     * @return array [$custId => [$memberId, ...], ...]
     */
    public function mapByTeams($data, $labelCustId, $labelParentId)
    {
        $result = [];
        foreach ($data as $one) {
            $custId = $one[$labelCustId];
            $parentId = $one[$labelParentId];
            if ($custId == $parentId) {
                /* skip root nodes, root node is not a member of a team. */
                continue;
            }
            if (!isset($result[$parentId])) {
                $result[$parentId] = [];
            }
            $result[$parentId][] = $custId;
        }
        return $result;
    }

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