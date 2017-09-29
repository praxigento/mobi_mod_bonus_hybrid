<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Helper\Calc;


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
    /** @var  \Praxigento\BonusHybrid\Helper\IScheme */
    protected $hlpScheme;

    public function __construct(
        \Praxigento\BonusHybrid\Helper\IScheme $hlpScheme
    ) {
        $this->hlpScheme = $hlpScheme;
    }

    public function exec(\Praxigento\BonusHybrid\Helper\Calc\IsQualified\Context $ctx)
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
            /** @var \Praxigento\BonusHybrid\Repo\Entity\Data\Cfg\Param $param */
            foreach ($params as $param) {
                $qpv = $param->getQualifyPv();
                $qtv = $param->getQualifyTv();
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