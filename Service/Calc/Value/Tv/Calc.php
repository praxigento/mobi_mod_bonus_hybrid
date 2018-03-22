<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Value\Tv;

use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;

/**
 * Calculate TV on the compressed downline tree.
 *
 * @deprecated use \Praxigento\BonusHybrid\Service\Calc\A\Proc\Tv
 */
class Calc
{
    /** @var \Praxigento\Downline\Helper\Tree */
    private $hlpDwnlTree;

    public function __construct(
        \Praxigento\Downline\Helper\Tree $hlpDwnlTree
    )
    {
        $this->hlpDwnlTree = $hlpDwnlTree;
    }

    /**
     * Calculate TV for the downline tree.
     *
     * @param \Praxigento\BonusHybrid\Repo\Data\Downline[] $dwnlBonus
     * @return \Praxigento\BonusHybrid\Repo\Data\Downline[] updated tree (with TV)
     */
    public function exec($dwnlBonus)
    {
        $result = [];
        $mapById = $this->hlpDwnlTree->mapById($dwnlBonus, EBonDwnl::ATTR_CUST_REF);
        $mapTeams = $this->hlpDwnlTree->mapByTeams($dwnlBonus, EBonDwnl::ATTR_CUST_REF, EBonDwnl::ATTR_PARENT_REF);
        /** @var \Praxigento\BonusHybrid\Repo\Data\Downline $one */
        foreach ($dwnlBonus as $one) {
            $custId = $one->getCustomerRef();
            /** @var \Praxigento\BonusHybrid\Repo\Data\Downline $cust */
            $cust = $mapById[$custId];
            /* initial TV equal to own PV */
            $tv = $cust->getPv();
            if (isset($mapTeams[$custId])) {
                /* add PV of the front line team (first generation) */
                $frontTeam = $mapTeams[$custId];
                foreach ($frontTeam as $teamMemberId) {
                    /** @var \Praxigento\BonusHybrid\Repo\Data\Downline $member */
                    $member = $mapById[$teamMemberId];
                    $memberPv = $member->getPv();
                    $tv += $memberPv;
                }
            }
            $cust->setTv($tv);
            $result[$custId] = $cust;
        }
        return $result;
    }
}