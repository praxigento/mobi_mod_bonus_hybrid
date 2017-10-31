<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase2\Fun\Act;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase2\Fun\Act\Qualify\Data\Request as ARequest;
use Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase2\Fun\Act\Qualify\Data\Response as AResponse;

class Qualify
{
    /** @var  \Praxigento\BonusHybrid\Helper\IScheme */
    protected $hlpScheme;

    public function __construct(
        \Praxigento\BonusHybrid\Helper\IScheme $hlpScheme
    ) {
        $this->hlpScheme = $hlpScheme;
    }

    public function exec(ARequest $req): AResponse
    {
        /* parse options */
        $dwnlEntry = $req->getDownlineEntry();
        $legsEntry = $req->getLegsEntry();
        $cfgParam = $req->getCfgParams(); // TODO: array ATTENTION: $cfgParam must be ordered by scheme then by rank DESC!!!
        $scheme = $req->getScheme();

        /* perform action */
        $rankId = null;
        $custId = $dwnlEntry->getCustomerRef();
        $rankId = $this->hlpScheme->getForcedQualificationRank($custId, $scheme);
        $ovMax = 0;
        $ovMedium = 0;
        $ovMin = 0;
        if (is_null($rankId)) {
            /* qualification params: PV & TV */
            $pv = $dwnlEntry->getPv();
            $tv = $dwnlEntry->getTv();
            /* qualification params:  legs */
            $legMax = $legsEntry->getLegMax();
            $legSecond = $legsEntry->getLegSecond();
            $legSummary = $legsEntry->getLegOthers();
            /* sort legs values to use in 3-legs qualification */
            $sorted = [$legMax, $legSecond, $legSummary];
            sort($sorted);
            $sortedMax = $sorted[2];
            $sortedMedium = $sorted[1];
            $sortedMin = $sorted[0];
            /* lookup for the max qualified rank */
            $ranks = $cfgParam[$scheme];
            /** @var \Praxigento\BonusHybrid\Repo\Entity\Data\Cfg\Param $rank */
            foreach ($ranks as $rank) {
                /* rank legs values */
                $qpv = $rank->getQualifyPv();
                $qtv = $rank->getQualifyTv();
                $ovMax = $rank->getLegMax();
                $ovMedium = $rank->getLegMedium();
                $ovMin = $rank->getLegMin();
                if (
                    ($pv >= $qpv) &&
                    ($tv >= $qtv)
                ) {

                    if (($ovMax > Cfg::DEF_ZERO) && ($ovMedium > Cfg::DEF_ZERO) && ($ovMin > Cfg::DEF_ZERO)) {
                        /* use all 3 legs to qualification, compare sorted data */
                        if (($sortedMax >= $ovMax) && ($sortedMedium >= $ovMedium) && ($sortedMin >= $ovMin)) {
                            $rankId = $rank->getRankId();
                            break;
                        }
                    } elseif (($ovMax > Cfg::DEF_ZERO) && ($ovMedium > Cfg::DEF_ZERO)) {
                        /* use 2 legs to qualification, compare original data */
                        if (($legMax >= $ovMax) && ($legSecond >= $ovMedium)) {
                            $rankId = $rank->getRankId();
                            break;
                        }
                    } elseif ($ovMax > Cfg::DEF_ZERO) {
                        /* use 1 leg to qualification, compare original data */
                        if ($legMax >= $ovMax) {
                            $rankId = $rank->getRankId();
                            break;
                        }
                    } elseif (
                        ($ovMax <= Cfg::DEF_ZERO) &&
                        ($ovMedium <= Cfg::DEF_ZERO) &&
                        ($ovMin <= Cfg::DEF_ZERO)
                    ) {
                        /* is qualified by TV & PV only */
                        $rankId = $rank->getRankId();
                        break;
                    }
                }
            }
        }

        /* compose results */
        $result = new AResponse();
        $result->setRankId($rankId);
        $legsOut = clone $legsEntry;
        $legsOut->setPvQualMax($ovMax);
        $legsOut->setPvQualSecond($ovMedium);
        $legsOut->setPvQualOther($ovMin);
        $result->setLegsEntry($legsOut);
        return $result;
    }
}