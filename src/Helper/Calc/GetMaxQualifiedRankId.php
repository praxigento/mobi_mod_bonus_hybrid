<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Helper\Calc;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Entity\Cfg\Param as CfgParam;
use Praxigento\BonusHybrid\Repo\Data\Entity\Compression\Oi as OiCompress;

class GetMaxQualifiedRankId
{
    const OPT_CFG_PARAMS = 'cfgParams';
    const OPT_COMPRESS_OI_ENTRY = 'compressOiEntry';
    const OPT_SCHEME = 'scheme';

    /** @var  \Praxigento\BonusHybrid\Tool\IScheme */
    protected $toolScheme;

    public function __construct(
        \Praxigento\BonusHybrid\Tool\IScheme $toolScheme
    ) {
        $this->toolScheme = $toolScheme;
    }

    public function exec($opts)
    {
        /* parse options */
        $compressOiEntry = $opts[self::OPT_COMPRESS_OI_ENTRY];
        $cfgParam = $opts[self::OPT_CFG_PARAMS]; // array ATTENTION: $cfgParam must be ordered by scheme then by rank DESC!!!
        $scheme = $opts[self::OPT_SCHEME];

        /* perform action */
        $result = null;
        $custId = $compressOiEntry[OiCompress::ATTR_CUSTOMER_ID];
        $forcedRankId = $this->toolScheme->getForcedQualificationRank($custId, $scheme);
        if (is_null($forcedRankId)) {
            /* qualification params: PV & TV */
            $pv = $compressOiEntry[OiCompress::ATTR_PV];
            $tv = $compressOiEntry[OiCompress::ATTR_TV];
            /* qualification params:  legs */
            $legMax = $compressOiEntry[OiCompress::ATTR_OV_LEG_MAX];
            $legSecond = $compressOiEntry[OiCompress::ATTR_OV_LEG_SECOND];
            $legSummary = $compressOiEntry[OiCompress::ATTR_OV_LEG_OTHERS];
            /* sort legs values to use in 3-legs qualification */
            $sorted = [$legMax, $legSecond, $legSummary];
            sort($sorted);
            $sortedMax = $sorted[2];
            $sortedMedium = $sorted[1];
            $sortedMin = $sorted[0];
            /* lookup for the max qualified rank */
            $ranks = $cfgParam[$scheme];
            foreach ($ranks as $rank) {
                /* rank legs values */
                $qpv = $rank[CfgParam::ATTR_QUALIFY_PV];
                $qtv = $rank[CfgParam::ATTR_QUALIFY_TV];
                $ovMax = $rank[CfgParam::ATTR_LEG_MAX];
                $ovMedium = $rank[CfgParam::ATTR_LEG_MEDIUM];
                $ovMin = $rank[CfgParam::ATTR_LEG_MIN];
                if (
                    ($pv >= $qpv) &&
                    ($tv >= $qtv)
                ) {

                    if (($ovMax > Cfg::DEF_ZERO) && ($ovMedium > Cfg::DEF_ZERO) && ($ovMin > Cfg::DEF_ZERO)) {
                        /* use all 3 legs to qualification, compare sorted data */
                        if (($sortedMax >= $ovMax) && ($sortedMedium >= $ovMedium) && ($sortedMin >= $ovMin)) {
                            $result = $rank[CfgParam::ATTR_RANK_ID];
                            break;
                        }
                    } elseif (($ovMax > Cfg::DEF_ZERO) && ($ovMedium > Cfg::DEF_ZERO)) {
                        /* use 2 legs to qualification, compare original data */
                        if (($legMax >= $ovMax) && ($legSecond >= $ovMedium)) {
                            $result = $rank[CfgParam::ATTR_RANK_ID];
                            break;
                        }
                    } elseif ($ovMax > Cfg::DEF_ZERO) {
                        /* use 1 leg to qualification, compare original data */
                        if ($legMax >= $ovMax) {
                            $result = $rank[CfgParam::ATTR_RANK_ID];
                            break;
                        }
                    } elseif (
                        ($ovMax <= Cfg::DEF_ZERO) &&
                        ($ovMedium <= Cfg::DEF_ZERO) &&
                        ($ovMin <= Cfg::DEF_ZERO)
                    ) {
                        /* is qualified by TV & PV only */
                        $result = $rank[CfgParam::ATTR_RANK_ID];
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