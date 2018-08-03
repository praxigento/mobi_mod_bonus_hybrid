<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast\Compress;

use Praxigento\BonusBase\Repo\Query\Period\Calcs\GetLast\ByCalcTypeCode\Builder as QBCalcGetLast;
use Praxigento\BonusHybrid\Config as Cfg;

/**
 * Get plain downline for forecast calculation and all customers with none-zero PV.
 */
class GetPlainData
    implements \Praxigento\Core\Api\App\Service\Process
{
    const IN_PERIOD = 'period';
    /** \Praxigento\BonusHybrid\Repo\Data\Downline[] */
    const OUT_DWNL = 'downline';
    /** Array [$custId => $pv] */
    const OUT_PV = 'pv';
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $daoBonDwnl;
    /** @var \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var \Praxigento\BonusBase\Repo\Query\Period\Calcs\GetLast\ByCalcTypeCode\Builder */
    private $qbCalcGetLast;

    public function __construct(
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoBonDwnl,
        \Praxigento\BonusBase\Repo\Query\Period\Calcs\GetLast\ByCalcTypeCode\Builder $qbCalcGetLast,
        \Praxigento\Core\Api\Helper\Period $hlpPeriod
    )
    {
        $this->daoBonDwnl = $daoBonDwnl;
        $this->qbCalcGetLast = $qbCalcGetLast;
        $this->hlpPeriod = $hlpPeriod;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        $period = $ctx->get(self::IN_PERIOD);
        /* prepare result vars */
        $result = new \Praxigento\Core\Data();
        $outPv = [];
        /**
         * perform processing
         */
        $calcIdPlain = $this->getPlainCalcId($period);
        /** @var \Praxigento\BonusHybrid\Repo\Data\Downline[] $downline */
        $downline = $this->daoBonDwnl->getByCalcId($calcIdPlain);
        foreach ($downline as $item) {
            $custId = $item->getCustomerRef();
            $pv = $item->getPv();
            if (abs($pv) > Cfg::DEF_ZERO) {
                $outPv[$custId] = $pv;
            }

        }
        /* put result data into output */
        $result->set(self::OUT_PV, $outPv);
        $result->set(self::OUT_DWNL, $downline);
        return $result;
    }

    /**
     * Get ID for the last complete forecast plain calculation.
     *
     * @param string $period YYYYMM
     * @return int
     */
    private function getPlainCalcId($period)
    {
        if ($period) {
            $dsMax = $this->hlpPeriod->getPeriodLastDate($period);
        } else {
            $dsMax = Cfg::DEF_MAX_DATESTAMP;
        }
        /* prepare query */
        $query = $this->qbCalcGetLast->build();
        $bind = [
            QBCalcGetLast::BND_CODE => Cfg::CODE_TYPE_CALC_FORECAST_PLAIN,
            QBCalcGetLast::BND_DATE => $dsMax,
            QBCalcGetLast::BND_STATE => Cfg::CALC_STATE_COMPLETE
        ];

        /* fetch & parse data */
        $conn = $query->getConnection();
        $rs = $conn->fetchRow($query, $bind);
        $result = $rs[QBCalcGetLast::A_CALC_ID];
        return $result;
    }

}