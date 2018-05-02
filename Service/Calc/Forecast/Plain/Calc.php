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
    const CTX_DWNL_TREE = 'dwnlTree';
    const KEY_TREE_DEPTH = \Praxigento\BonusHybrid\Repo\Data\Downline::A_DEPTH;
    const KEY_TREE_ENTITY = \Praxigento\BonusHybrid\Repo\Data\Downline::A_CUST_REF;
    const KEY_TREE_PARENT = \Praxigento\BonusHybrid\Repo\Data\Downline::A_PARENT_REF;

    /** @var \Praxigento\Downline\Api\Helper\Tree */
    private $hlpDwnlTree;

    public function __construct(
        \Praxigento\Downline\Api\Helper\Tree $hlpDwnlTree
    )
    {
        $this->hlpDwnlTree = $hlpDwnlTree;
    }

    /**
     * @param \Praxigento\Core\Data $ctx
     */
    public function exec(\Praxigento\Core\Data $ctx = null)
    {
        /** @var \Praxigento\BonusHybrid\Repo\Data\Downline[] $dwnlTree */
        $dwnlTree = $ctx->get(self::CTX_DWNL_TREE);
        /* prepare working data: tree maps, etc.*/
        $mapByDepth = $this->hlpDwnlTree->mapByTreeDepthDesc($dwnlTree, self::KEY_TREE_ENTITY, self::KEY_TREE_DEPTH);
        $mapByTeam = $this->hlpDwnlTree->mapByTeams($dwnlTree, self::KEY_TREE_ENTITY, self::KEY_TREE_PARENT);
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