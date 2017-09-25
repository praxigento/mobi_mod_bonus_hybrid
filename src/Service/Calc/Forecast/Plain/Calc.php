<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast\Plain;

/**
 * Calculate TV/OV for plain downline report.
 */
class Calc
{
    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\A\Traits\TMap {
        mapById as protected;
        mapByTeams as protected;
        mapByTreeDepthDesc as protected;
    }

    const CTX_DWNL_TREE = 'dwnlTree';
    const KEY_TREE_DEPTH = \Praxigento\BonusHybrid\Repo\Entity\Data\Downline::ATTR_DEPTH;
    const KEY_TREE_ENTITY = \Praxigento\BonusHybrid\Repo\Entity\Data\Downline::ATTR_CUST_REF;
    const KEY_TREE_PARENT = \Praxigento\BonusHybrid\Repo\Entity\Data\Downline::ATTR_PARENT_REF;

    /**
     * @param \Praxigento\Core\Data $ctx
     */
    public function exec(\Praxigento\Core\Data $ctx = null)
    {
        /** @var \Praxigento\BonusHybrid\Repo\Entity\Data\Downline[] $dwnlTree */
        $dwnlTree = $ctx->get(self::CTX_DWNL_TREE);
        /* prepare working data: tree maps, etc.*/
        $mapByDepth = $this->mapByTreeDepthDesc($dwnlTree, self::KEY_TREE_ENTITY, self::KEY_TREE_DEPTH);
        $mapByTeam = $this->mapByTeams($dwnlTree, self::KEY_TREE_ENTITY, self::KEY_TREE_PARENT);
        /* go through the levels and collect PV to TV/OV */
        foreach ($mapByDepth as $level) {
            foreach ($level as $custId) {
                $plainItem = $dwnlTree[$custId];
                $pv = $plainItem->getPv();
                /* collect TV & OV */
                $ov = $tv = $pv;
                if (isset($mapByTeam[$custId])) {
                    $teamMembers = $mapByTeam[$custId];
                    foreach ($teamMembers as $teamMemberId) {
                        $teamMember = $dwnlTree[$teamMemberId];
                        $memberPv = $teamMember->getPv();
                        $memberOv = $teamMember->getOv();
                        $tv += $memberPv;
                        $ov += $memberOv;
                    }
                }
                $plainItem->setTv($tv);
                $plainItem->setOv($ov);
            }
        }
    }
}