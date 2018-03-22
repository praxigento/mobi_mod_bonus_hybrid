<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc;

use Praxigento\BonusBase\Repo\Data\Calculation as ECalc;
use Praxigento\BonusBase\Repo\Data\Period as EPeriod;

/**
 * Local process to clean calculation data for forecast calculations (plain & compressed).
 */
class Clean
    implements \Praxigento\Core\App\Service\IProcess
{
    const IN_CALC_TYPE_CODE = 'calcTypeCode';

    /** @var \Praxigento\BonusBase\Repo\Dao\Calculation */
    private $repoCalc;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoDwnl;
    /** @var \Praxigento\BonusBase\Repo\Dao\Period */
    private $repoPeriod;
    /** @var \Praxigento\BonusBase\Repo\Dao\Type\Calc */
    private $repoTypeCalc;

    public function __construct(
        \Praxigento\BonusBase\Repo\Dao\Type\Calc $repoTypeCalc,
        \Praxigento\BonusBase\Repo\Dao\Period $repoPeriod,
        \Praxigento\BonusBase\Repo\Dao\Calculation $repoCalc,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoDwnl
    )
    {
        $this->repoTypeCalc = $repoTypeCalc;
        $this->repoPeriod = $repoPeriod;
        $this->repoCalc = $repoCalc;
        $this->repoDwnl = $repoDwnl;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /* get working data from context */
        $calcTypeCode = $ctx->get(self::IN_CALC_TYPE_CODE);

        /* get calculation type ID by code */
        $calcTypeId = $this->repoTypeCalc->getIdByCode($calcTypeCode);
        /* get all periods by calculation type */
        $where = EPeriod::ATTR_CALC_TYPE_ID . '=' . (int)$calcTypeId;
        $periods = $this->repoPeriod->get($where);
        if (is_array($periods)) {
            /** @var \Praxigento\BonusBase\Repo\Data\Period $period */
            foreach ($periods as $period) {
                /* get calculations by period */
                $periodId = $period->getId();
                $whereCalc = ECalc::ATTR_PERIOD_ID . '=' . (int)$periodId;
                $calcs = $this->repoCalc->get($whereCalc);
                if (is_array($calcs)) {
                    /** @var \Praxigento\BonusBase\Repo\Data\Calculation $calc */
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