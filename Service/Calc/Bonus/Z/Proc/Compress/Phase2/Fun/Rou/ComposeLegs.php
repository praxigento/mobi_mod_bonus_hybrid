<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Compress\Phase2\Fun\Rou;

use Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Compress\Phase2\Data\Legs as DLegs;

/**
 * Routine to compare plain tree legs with compressed tree legs and compose results for qualification.
 */
class ComposeLegs
{

    public function exec(DLegs $plain, DLegs $compress): DLegs
    {
        $maxP = $plain->getMaxOv();
        $secondP = $plain->getSecondOv();
        $othersP = $plain->getOthersOv();

        $maxC = $compress->getMaxOv();
        $secondC = $compress->getSecondOv();
        $othersC = $compress->getOthersOv();

        $custMax = $plain->getMaxCustId();
        $custSecond = $plain->getSecondCustId();

        $second = $others = 0;
        if ($maxP && !$secondP && !$othersP) {
            /* there is one only leg, use plain data */
            $max = $maxP;
        } elseif ($maxP && $secondP && !$othersP) {
            /* there are 2 legs, also use plain data */
            $max = $maxP;
            $second = $secondP;
        } else {
            /* there are 2 legs (use plain) & others (use delta) */
            $max = $maxP;
            $second = $secondP;
            $others = $maxC + $secondC + $othersC - ($max + $second);
        }

        /* compose results */
        $result = new DLegs();
        $result->setMaxOv($max);
        $result->setSecondOv($second);
        $result->setOthersOv($others);
        $result->setMaxCustId($custMax);
        $result->setSecondCustId($custSecond);
        return $result;
    }
}