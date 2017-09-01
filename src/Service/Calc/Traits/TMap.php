<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Calc\Traits;


trait TMap
{
    /**
     * Convert array of data or data objects ([ 0 => [ 'id' => 321, ... ], ...])
     * to mapped array ([ 321 => [ 'id'=>321, ... ], ... ]).
     *
     * @param array|\Praxigento\Core\Data[] $data nested array or array of data objects.
     * @param string $keyId name of the 'id' attribute.
     *
     * @return array|\Praxigento\Core\Data[]
     */
    public function mapById($data, $keyId)
    {
        $result = [];
        foreach ($data as $one) {
            /* $one should be an array or a DataObject */
            $id = (is_array($one)) ? $one[$keyId] : $one->get($keyId);
            $result[$id] = $one;
        }
        return $result;
    }

    /**
     * Create map of the front team members (siblings) [$custId => [$memberId, ...], ...] from compressed or snapshot
     * data.
     *
     * @param array|\Praxigento\Core\Data[] $data nested array or array of data objects.
     * @param string $keyCustId name of the 'customer id' attribute.
     * @param string $keyParentId name of the 'parent id' attribute.
     *
     * @return array [$custId => [$memberId, ...], ...]
     */
    public function mapByTeams($data, $keyCustId, $keyParentId)
    {
        $result = [];
        foreach ($data as $one) {
            if (is_array($one)) {
                $customerId = $one[$keyCustId];
                $parentId = $one[$keyParentId];
            } else {
                /* this should be data object */
                $customerId = $one->get($keyCustId);
                $parentId = $one->get($keyParentId);
            }
            if ($customerId == $parentId) {
                /* skip root nodes, root node is not a member of a team. */
                continue;
            }
            if (!isset($result[$parentId])) {
                $result[$parentId] = [];
            }
            $result[$parentId][] = $customerId;
        }
        return $result;
    }

    /**
     * Get depth index for Downline Tree ordered by depth desc.
     *
     * @param array|\Praxigento\Core\Data[] $tree nested array or array of data objects.
     * @param string $keyCustId name of the 'customer id' attribute.
     * @param string $keyDepth name of the 'depth' attribute.
     *
     * @return array  [$depth => [$custId, ...]]
     */
    public function mapByTreeDepthDesc($tree, $keyCustId, $keyDepth)
    {
        $result = [];
        foreach ($tree as $one) {
            if (is_array($one)) {
                $customerId = $one[$keyCustId];
                $depth = $one[$keyDepth];
            } else {
                /* this should be data object */
                $customerId = $one->get($keyCustId);
                $depth = $one->get($keyDepth);
            }
            if (!isset($result[$depth])) {
                $result[$depth] = [];
            }
            $result[$depth][] = $customerId;
        }
        /* sort by depth desc */
        krsort($result);
        return $result;
    }

    /**
     * Map 'value' elements of the $data array on $id elements.
     *
     * @param array|\Praxigento\Core\Data[] $data associative array with 'id' elements & 'value' elements.
     * @param $keyId key for 'id' element
     * @param $keyValue key for 'value' element
     * @return array [id => value, ...]
     */
    public function mapValueById($data, $keyId, $keyValue)
    {
        $result = [];
        foreach ($data as $one) {
            /* $one should be an array or a DataObject */
            if (is_array($one)) {
                $id = $one[$keyId];
                $value = $one[$keyValue];
            } else {
                /* this should be data object */
                $id = $one->get($keyId);
                $value = $one->get($keyValue);
            }
            $result[$id] = $value;
        }
        return $result;
    }
}