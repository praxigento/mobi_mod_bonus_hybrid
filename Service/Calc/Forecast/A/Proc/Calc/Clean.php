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
    implements \Praxigento\Core\Api\App\Service\Process
{
    const IN_CALC_TYPE_CODE = 'calcTypeCode';
    const IN_PERIOD = 'period';

    /** @var \Praxigento\BonusBase\Repo\Dao\Calculation */
    private $daoCalc;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $daoDwnl;
    /** @var \Praxigento\BonusBase\Repo\Dao\Period */
    private $daoPeriod;
    /** @var \Praxigento\BonusBase\Repo\Dao\Type\Calc */
    private $daoTypeCalc;
    /** @var \Praxigento\Core\Helper\Period */
    private $hlpPeriod;
    /** @var \Magento\Framework\App\ResourceConnection */
    private $resource;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\BonusBase\Repo\Dao\Type\Calc $daoTypeCalc,
        \Praxigento\BonusBase\Repo\Dao\Period $daoPeriod,
        \Praxigento\BonusBase\Repo\Dao\Calculation $daoCalc,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoDwnl,
        \Praxigento\Core\Helper\Period $hlpPeriod
    )
    {
        $this->resource = $resource;
        $this->daoTypeCalc = $daoTypeCalc;
        $this->daoPeriod = $daoPeriod;
        $this->daoCalc = $daoCalc;
        $this->daoDwnl = $daoDwnl;
        $this->hlpPeriod = $hlpPeriod;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /* get working data from context */
        $calcTypeCode = $ctx->get(self::IN_CALC_TYPE_CODE);
        $period = $ctx->get(self::IN_PERIOD);
        $dsBegin = $this->hlpPeriod->getPeriodFirstDate($period);
        $dsEnd = $this->hlpPeriod->getPeriodLastDate($period);
        $conn = $this->resource->getConnection();

        /* get calculation type ID by code */
        $calcTypeId = $this->daoTypeCalc->getIdByCode($calcTypeCode);
        /* get period by calculation type and begin-end dates */
        $byType = EPeriod::A_CALC_TYPE_ID . '=' . (int)$calcTypeId;
        $byBegin = EPeriod::A_DSTAMP_BEGIN . '=' . $conn->quote($dsBegin);
        $byEnd = EPeriod::A_DSTAMP_END . '=' . $conn->quote($dsEnd);
        $where = "($byType) AND ($byBegin) AND ($byEnd)";
        $periods = $this->daoPeriod->get($where);
        if (is_array($periods)) {
            /** @var \Praxigento\BonusBase\Repo\Data\Period $period */
            foreach ($periods as $period) {
                /* get calculations by period */
                $periodId = $period->getId();
                $whereCalc = ECalc::A_PERIOD_ID . '=' . (int)$periodId;
                $calcs = $this->daoCalc->get($whereCalc);
                if (is_array($calcs)) {
                    /** @var \Praxigento\BonusBase\Repo\Data\Calculation $calc */
                    foreach ($calcs as $calc) {
                        $calcId = $calc->getId();
                        /* delete all downline trees for the calculation */
                        $whereDwnl = \Praxigento\BonusHybrid\Repo\Data\Downline::A_CALC_REF . '=' . (int)$calcId;
                        $this->daoDwnl->delete($whereDwnl);
                        /* delete calculation itself */
                        $this->daoCalc->deleteById($calcId);
                    }
                }
                /* delete period itself */
                $this->daoPeriod->deleteById($periodId);
            }
        }
    }
}