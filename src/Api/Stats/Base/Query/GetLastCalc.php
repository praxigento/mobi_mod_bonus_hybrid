<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Api\Stats\Base\Query;

use Praxigento\BonusBase\Repo\Entity\Data\Calculation as Calc;
use Praxigento\BonusBase\Repo\Entity\Data\Period;
use Praxigento\BonusBase\Repo\Entity\Data\Type\Calc as TypeCalc;
use Praxigento\BonusBase\Repo\Query\Period\Calcs\Builder as BldPeriod;
use Praxigento\BonusHybrid\Config as Cfg;

/**
 * Base query to select period and calculation data by filter (period last date and calculation type).
 *
 * @deprecated  use \Praxigento\BonusBase\Repo\Query\Period\Calcs\GetLast\ByCalcTypeCode\Builder
 */
class GetLastCalc
{
    const A_CALC_REF = 'calc_ref';
    const A_DS_END = 'ds_end';

    const BND_CODE = 'code';
    const BND_DATE = 'lastDate';
    const BND_STATE = 'state';

    const OPT_CALC_TYPE_CODE = 'calc_type_code';
    const OPT_DATE_END = 'ds_end';

    /** @var \Praxigento\BonusBase\Repo\Query\Period\Calcs\Builder */
    protected $qbldPeriod;

    public function __construct(
        \Praxigento\BonusBase\Repo\Query\Period\Calcs\Builder $qbldPeriod
    ) {
        $this->qbldPeriod = $qbldPeriod;
    }

    public function exec(\Flancer32\Lib\Data $opts)
    {
        $result = new \Flancer32\Lib\Data();
        $bind = [];
        /* parse input options */
        $dsEnd = $opts->get(self::OPT_DATE_END);
        $calcTypeCode = $opts->get(self::OPT_CALC_TYPE_CODE);

        /* get the last complete calculation */
        $query = $this->qbldPeriod->getSelectQuery();
        $whereType = BldPeriod::AS_CALC_TYPE . '.' . TypeCalc::ATTR_CODE . '=:' . self::BND_CODE;
        $whereState = BldPeriod::AS_CALC . '.' . Calc::ATTR_STATE . '=:' . self::BND_STATE;
        $query->where("$whereType AND $whereState");
        /* add filter by MAX date */
        if ($dsEnd) {
            $whereDate = BldPeriod::AS_PERIOD . '.' . Period::ATTR_DSTAMP_END . '<=:' . self::BND_DATE;
            $bind[self::BND_DATE] = $dsEnd;
            $query->where($whereDate);
        }
        /* sort desc and limit results */
        $query->order(BldPeriod::AS_PERIOD . '.' . Period::ATTR_DSTAMP_END . ' DESC');
        $query->limit(1);
        $bind[self::BND_CODE] = $calcTypeCode;
        $bind[self::BND_STATE] = Cfg::CALC_STATE_COMPLETE;
        /* get data and compose results */
        $row = $query->getConnection()->fetchRow($query, $bind);

        $result->set(self::A_CALC_REF, $row[BldPeriod::A_CALC_ID]);
        $result->set(self::A_DS_END, $row[BldPeriod::A_DS_END]);
        return $result;
    }
}