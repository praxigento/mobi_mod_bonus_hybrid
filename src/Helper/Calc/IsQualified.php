<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Helper\Calc;


use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Cfg\Param as CfgParam;

class IsQualified
{
    const OPT_CFG_PARAMS = 'cfgParams';
    const OPT_CUST_ID = 'custId';
    const OPT_PV = 'pv';
    const OPT_SCHEME = 'scheme';
    const OPT_TV = 'tv';
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
        $custId = $opts[self::OPT_CUST_ID];
        $pv = $opts[self::OPT_PV];
        $tv = $opts[self::OPT_TV];
        $scheme = $opts[self::OPT_SCHEME];
        $cfgParams = $opts[self::OPT_CFG_PARAMS];

        /* perform action */
        $result = false;
        if (
            ($pv > Cfg::DEF_ZERO) &&
            ($tv > Cfg::DEF_ZERO)
        ) {
            $params = $cfgParams[$scheme];
            foreach ($params as $param) {
                $qpv = $param[CfgParam::ATTR_QUALIFY_PV];
                $qtv = $param[CfgParam::ATTR_QUALIFY_TV];
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
            $rankId = $this->toolScheme->getForcedQualificationRank($custId, $scheme);
            $result = ($rankId > 0);
        }
        return $result;
    }
}