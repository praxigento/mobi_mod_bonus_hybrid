<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\A\Proc;

use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;

/**
 * Calculate TV on the compressed downline tree.
 */
class Tv
    implements \Praxigento\Core\App\Service\IProcess
{
    /** \Praxigento\BonusHybrid\Repo\Data\Downline[] downline with PV */
    const IN_DWNL = 'downline';
    /** \Praxigento\BonusHybrid\Repo\Data\Downline[] updated downline with TV*/
    const OUT_DWNL = 'downline';

    /** @var \Praxigento\Downline\Helper\Tree */
    private $hlpDwnlTree;

    public function __construct(
        \Praxigento\Downline\Helper\Tree $hlpDwnlTree
    )
    {
        $this->hlpDwnlTree = $hlpDwnlTree;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /* get working data from input */
        $dwnlBonus = $ctx->get(self::IN_DWNL);

        /* define local working data */
        $mapById = $this->hlpDwnlTree->mapById($dwnlBonus, EBonDwnl::ATTR_CUST_REF);
        $mapTeams = $this->hlpDwnlTree->mapByTeams($dwnlBonus, EBonDwnl::ATTR_CUST_REF, EBonDwnl::ATTR_PARENT_REF);

        /* prepare output vars */
        $updated = [];

        /**
         * perform processing
         */
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
            $updated[$custId] = $cust;
        }
        /* put result data into output */
        $result = new \Praxigento\Core\Data();
        $result->set(self::OUT_DWNL, $updated);
        return $result;
    }
}