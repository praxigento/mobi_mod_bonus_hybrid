<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase2\Fun\Rou;

use Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase2\Data\Legs as DLegs;

/**
 * Routine to calculate legs using team OVs.
 */
class CalcLegs
{
    /**
     * Run though first-line team members and collect OVs (plain or compressed).
     *
     * @param array $team Customers IDs for first-line team.
     * @param \Praxigento\BonusHybrid\Repo\Entity\Data\Downline[] $mapById Downline data (with OV) mapped by customer
     *     ID.
     * @return \Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase2\Data\Legs
     */
    public function exec($team, $mapById): DLegs
    {
        $legMax = $legSecond = $legOthers = 0;
        $custMax = $custSecond = null;
        foreach ($team as $memberId) {
            /** @var \Praxigento\BonusHybrid\Repo\Entity\Data\Downline $member */
            $member = $mapById[$memberId];
            $ovMember = $member->getOv();
            if ($ovMember > $legMax) {
                /* update MAX leg */
                $legOthers += $legSecond;
                $legSecond = $legMax;
                $custSecond = $custMax;
                $legMax = $ovMember;
                $custMax = $memberId;
            } elseif ($ovMember > $legSecond) {
                /* update second leg */
                $legOthers += $legSecond;
                $legSecond = $ovMember;
                $custSecond = $memberId;
            } else {
                $legOthers += $ovMember;
            }
        }

        /* compose results */
        $result = new DLegs();
        $result->setMaxOv($legMax);
        $result->setSecondOv($legSecond);
        $result->setOthersOv($legOthers);
        $result->setMaxCustId($custMax);
        $result->setSecondCustId($custSecond);
        return $result;
    }
}