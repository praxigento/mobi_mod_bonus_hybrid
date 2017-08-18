<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast;

use Praxigento\BonusBase\Data\Entity\Calculation as ECalc;
use Praxigento\BonusBase\Data\Entity\Period as EPeriod;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Defaults as Def;

/**
 * Internal processor to clean calculation data for forecast calculations (plain & compressed).
 */
class CleanCalcData
{
    const CTX_IN_CALC_TYPE_CODE = 'calcTypeCode';
    const CTX_OUT_RESULT = 'result';

    /** @var \Praxigento\BonusBase\Repo\Entity\Calculation */
    protected $repoCalc;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    protected $repoDwnl;
    /** @var \Praxigento\BonusBase\Repo\Entity\Period */
    protected $repoPeriod;
    /** @var \Praxigento\BonusBase\Repo\Entity\Type\Calc */
    protected $repoTypeCalc;

    public function __construct(
        \Praxigento\BonusBase\Repo\Entity\Type\Calc $repoTypeCalc,
        \Praxigento\BonusBase\Repo\Entity\Period $repoPeriod,
        \Praxigento\BonusBase\Repo\Entity\Calculation $repoCalc,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoDwnl
    )
    {
        $this->repoTypeCalc = $repoTypeCalc;
        $this->repoPeriod = $repoPeriod;
        $this->repoCalc = $repoCalc;
        $this->repoDwnl = $repoDwnl;
    }

    public function exec(\Flancer32\Lib\Data $ctx)
    {
        /* get working data from context */
        $calcTypeCode = $ctx->get(self::CTX_IN_CALC_TYPE_CODE);

        /* get calculation type ID by code */
        $calcTypeId = $this->repoTypeCalc->getIdByCode($calcTypeCode);
        /* get periods by calculation type */
        $where = EPeriod::ATTR_CALC_TYPE_ID . '=' . (int)$calcTypeId;
        $periods = $this->repoPeriod->get($where);
        if (is_array($periods)) {
            /** @var \Praxigento\BonusBase\Data\Entity\Period $period */
            foreach ($periods as $period) {
                /* get calculations by period */
                $periodId = $period->getId();
                $whereCalc = ECalc::ATTR_PERIOD_ID . '=' . (int)$periodId;
                $calcs = $this->repoCalc->get($whereCalc);
                if (is_array($calcs)) {
                    /** @var \Praxigento\BonusBase\Data\Entity\Calculation $calc */
                    foreach ($calcs as $calc) {
                        $calcId = $calc->getId();
                        /* delete all downline trees for the calculation */
                        $whereDwnl = \Praxigento\BonusHybrid\Repo\Entity\Data\Downline::ATTR_CALC_REF . '=' . (int)$calcId;
                        $this->repoDwnl->delete($whereDwnl);
                        /* delete calculation itself */
                        $this->repoCalc->deleteById($calcId);
                    }
                }
                /* delete period itself */
                $this->repoPeriod->deleteById($periodId);
            }
        }
    }
}