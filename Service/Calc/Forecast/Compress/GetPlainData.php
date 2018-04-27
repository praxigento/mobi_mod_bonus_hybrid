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
    /** \Praxigento\BonusHybrid\Repo\Data\Downline[] */
    const OUT_DWNL = 'downline';
    /** Array [$custId => $pv] */
    const OUT_PV = 'pv';

    /** @var \Praxigento\BonusBase\Repo\Query\Period\Calcs\GetLast\ByCalcTypeCode\Builder */
    private $qbCalcGetLast;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $daoBonDwnl;

    public function __construct(
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoBonDwnl,
        \Praxigento\BonusBase\Repo\Query\Period\Calcs\GetLast\ByCalcTypeCode\Builder $qbCalcGetLast
    )
    {
        $this->daoBonDwnl = $daoBonDwnl;
        $this->qbCalcGetLast = $qbCalcGetLast;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /* prepare result vars */
        $result = new \Praxigento\Core\Data();
        $outPv = [];
        /**
         * perform processing
         */
        $calcIdPlain = $this->getPlainCalcId();
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
     * @return int
     */
    private function getPlainCalcId()
    {
        /* prepare query */
        $query = $this->qbCalcGetLast->build();
        $bind = [
            QBCalcGetLast::BND_CODE => Cfg::CODE_TYPE_CALC_FORECAST_PLAIN,
            QBCalcGetLast::BND_DATE => Cfg::DEF_MAX_DATESTAMP,
            QBCalcGetLast::BND_STATE => Cfg::CALC_STATE_COMPLETE
        ];

        /* fetch & parse data */
        $conn = $query->getConnection();
        $rs = $conn->fetchRow($query, $bind);
        $result = $rs[QBCalcGetLast::A_CALC_ID];
        return $result;
    }

}