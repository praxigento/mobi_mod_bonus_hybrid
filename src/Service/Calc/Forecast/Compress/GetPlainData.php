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
    implements \Praxigento\Core\Service\IProcess
{
    /** \Praxigento\BonusHybrid\Repo\Entity\Data\Downline[] */
    const OUT_DWNL = 'downline';
    /** Array [$custId => $pv] */
    const OUT_PV = 'pv';

    /** @var \Praxigento\BonusBase\Repo\Query\Period\Calcs\GetLast\ByCalcTypeCode\Builder */
    private $qbCalcGetLast;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoBonDwnl;

    public function __construct(
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoBonDwnl,
        \Praxigento\BonusBase\Repo\Query\Period\Calcs\GetLast\ByCalcTypeCode\Builder $qbCalcGetLast
    )
    {
        $this->repoBonDwnl = $repoBonDwnl;
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
        /** @var \Praxigento\BonusHybrid\Repo\Entity\Data\Downline[] $downline */
        $downline = $this->repoBonDwnl->getByCalcId($calcIdPlain);
        foreach ($downline as $item) {
            $pv = $item->getPv();
            if (abs($pv) > Cfg::DEF_ZERO) {
                $custId = $item->getCustomerRef();
                $outPv[$custId] = $pv;
            }
        }
        /* compose result data */
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