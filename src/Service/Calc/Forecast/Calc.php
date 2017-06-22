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

    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\Traits\TMap {
        mapById as protected;
        mapByTeams as protected;
        mapByTreeDepthDesc as protected;
    }

    const CTX_PLAIN_TREE = 'plainTree';
    const KEY_TREE_ENTITY = \Praxigento\BonusHybrid\Repo\Data\Entity\Actual\Downline\Plain::ATTR_CUSTOMER_REF;
    const KEY_TREE_DEPTH = \Praxigento\BonusHybrid\Repo\Data\Entity\Actual\Downline\Plain::ATTR_DEPTH;
    const KEY_TREE_PARENT = \Praxigento\BonusHybrid\Repo\Data\Entity\Actual\Downline\Plain::ATTR_PARENT_REF;

    public function __construct()
    {
    }


    /**
     * @param \Flancer32\Lib\Data $ctx
     */
    public function exec(\Flancer32\Lib\Data $ctx = null)
    {
        $result = [];
        /** @var \Praxigento\BonusHybrid\Repo\Data\Entity\Actual\Downline\Plain[] $plainTree */
        $plainTree = $ctx->get(self::CTX_PLAIN_TREE);
        /* prepare working data: tree maps, etc.*/
        $mapByDepth = $this->mapByTreeDepthDesc($plainTree, self::KEY_TREE_ENTITY, self::KEY_TREE_DEPTH);
        $mapByTeam = $this->mapByTeams($plainTree, self::KEY_TREE_ENTITY, self::KEY_TREE_PARENT);
        /* go through the levels and collect PV to TV/OV */
        foreach ($mapByDepth as $level) {
            foreach ($level as $custId) {
                $plainItem = $plainTree[$custId];
                $pv = $plainItem->getPv();
                /* collect TV & OV */
                $ov = $tv = $pv;
                if (isset($mapByTeam[$custId])) {
                    $teamMembers = $mapByTeam[$custId];
                    foreach ($teamMembers as $teamMemberId) {
                        $teamMember = $plainTree[$teamMemberId];
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
        return $result;
    }
}