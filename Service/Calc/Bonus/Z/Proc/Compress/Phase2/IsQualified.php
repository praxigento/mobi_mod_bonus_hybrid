<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Compress\Phase2;


use Praxigento\BonusHybrid\Config as Cfg;

/**
 * Customer qualification verifier.
 *
 * TODO: should we move this helper under Services namespace?
 */
class IsQualified
{
    const OPT_CFG_PARAMS = 'cfgParams';
    const OPT_CUST_ID = 'custId';
    const OPT_PV = 'pv';
    const OPT_SCHEME = 'scheme';
    const OPT_TV = 'tv';
    /** @var  \Praxigento\BonusHybrid\Api\Helper\Scheme */
    protected $hlpScheme;

    public function __construct(
        \Praxigento\BonusHybrid\Api\Helper\Scheme $hlpScheme
    ) {
        $this->hlpScheme = $hlpScheme;
    }

    public function exec(\Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Compress\Phase2\IsQualified\Context $ctx)
    {
        /* parse context vars */
        $custId = $ctx->getCustId();
        $pv = $ctx->getPv();
        $tv = $ctx->getTv();
        $scheme = $ctx->getScheme();
        $cfgParams = $ctx->getCfgParams();

        /* perform action */
        $result = false;
        if (
            ($pv > Cfg::DEF_ZERO) &&
            ($tv > Cfg::DEF_ZERO)
        ) {
            $params = $cfgParams[$scheme];
            /** @var \Praxigento\BonusHybrid\Repo\Data\Cfg\Param $param */
            foreach ($params as $param) {
                /* subtract ZERO to handle float equality (750 - 750 = - 0.0000... */
                $qpv = $param->getQualifyPv() - Cfg::DEF_ZERO;
                $qtv = $param->getQualifyTv() - Cfg::DEF_ZERO;
                if (
                    ($pv >= $qpv) &&
                    ($tv >= $qtv)
                ) {
                    /* this customer is qualified for the rank */
                    $result = true;
                    break;
                }
            }
        }
        if (!$result) {
            /* check forced qualification */
            $rankId = $this->hlpScheme->getForcedQualificationRank($custId, $scheme);
            $result = ($rankId > 0);
        }
        return $result;
    }
}
