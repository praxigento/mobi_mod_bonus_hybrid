<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Helper\Calc;

use Praxigento\BonusHybrid\Config as Cfg;

class GetMaxQualifiedRankId
{
    const OPT_CFG_PARAMS = 'cfgParams';
    const OPT_COMPRESS_OI_ENTRY = 'compressOiEntry';
    const OPT_SCHEME = 'scheme';

    /** @var  \Praxigento\BonusHybrid\Tool\IScheme */
    protected $hlpScheme;

    public function __construct(
        \Praxigento\BonusHybrid\Tool\IScheme $hlpScheme
    ) {
        $this->hlpScheme = $hlpScheme;
    }

    public function exec(\Praxigento\BonusHybrid\Helper\Calc\GetMaxQualifiedRankId\Context $ctx)
    {
        /* parse options */
        $dwnlEntry = $ctx->getDownlineEntry();
        $legsEntry = $ctx->getLegsEntry();
        $cfgParam = $ctx->getCfgParams(); // TODO: array ATTENTION: $cfgParam must be ordered by scheme then by rank DESC!!!
        $scheme = $ctx->getScheme();

        /* perform action */
        $result = null;
        $custId = $dwnlEntry->getCustomerRef();
        $forcedRankId = $this->hlpScheme->getForcedQualificationRank($custId, $scheme);
        if (is_null($forcedRankId)) {
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
                            $result = $rank->getRankId();
                            break;
                        }
                    } elseif (($ovMax > Cfg::DEF_ZERO) && ($ovMedium > Cfg::DEF_ZERO)) {
                        /* use 2 legs to qualification, compare original data */
                        if (($legMax >= $ovMax) && ($legSecond >= $ovMedium)) {
                            $result = $rank->getRankId();
                            break;
                        }
                    } elseif ($ovMax > Cfg::DEF_ZERO) {
                        /* use 1 leg to qualification, compare original data */
                        if ($legMax >= $ovMax) {
                            $result = $rank->getRankId();
                            break;
                        }
                    } elseif (
                        ($ovMax <= Cfg::DEF_ZERO) &&
                        ($ovMedium <= Cfg::DEF_ZERO) &&
                        ($ovMin <= Cfg::DEF_ZERO)
                    ) {
                        /* is qualified by TV & PV only */
                        $result = $rank->getRankId();
                        break;
                    }
                }
            }
        } else {
            $result = $forcedRankId;
        }
        return $result;
    }
}