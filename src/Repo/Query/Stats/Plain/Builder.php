<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Repo\Query\Stats\Plain;

use Praxigento\BonusHybrid\Repo\Entity\Data\Retro\Downline\Plain as RegPto;
use Praxigento\Downline\Repo\Entity\Data\Snap as Snap;

/**
 * Build query to get plain PV/TV/OV statistics for the given calculation.
 */
class Builder
    extends \Praxigento\Core\Repo\Query\Def\Builder
{
    /** Tables aliases */
    const AS_REG_PTO = 'regPto';

    /** Columns aliases */
    const A_CUST_ID = \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder::A_CUST_ID;
    const A_DEPTH = \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder::A_DEPTH;
    const A_OV = RegPto::ATTR_OV;
    const A_PARENT_ID = \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder::A_PARENT_ID;
    const A_PATH = \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder::A_PATH;
    const A_PV = RegPto::ATTR_PV;
    const A_TV = RegPto::ATTR_TV;

    /** Bound variables names */
    const BIND_CALC_REF = 'calcRef';
    const BIND_ON_DATE = \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder::BIND_ON_DATE;

    /** @var \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder */
    protected $qbldDwnlSnap;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder $qbldDwnlSnap
    ) {
        parent::__construct($resource);
        $this->qbldDwnlSnap = $qbldDwnlSnap;
    }

    public function getSelectQuery(\Praxigento\Core\Repo\Query\IBuilder $qbuild = null)
    {
        $result = is_null($qbuild) ? $this->qbldDwnlSnap->getSelectQuery() : $qbuild->getSelectQuery();
        /* define tables aliases */
        $asReg = self::AS_REG_PTO;
        $asSnap = \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder::AS_DWNL_SNAP;

        /* LEFT JOIN prxgt_bon_hyb_reg_pto */
        $tbl = $this->resource->getTableName(RegPto::ENTITY_NAME);
        $cols = [
            self::A_PV => RegPto::ATTR_PV,
            self::A_TV => RegPto::ATTR_TV,
            self::A_OV => RegPto::ATTR_OV
        ];
        $onCustId = $asReg . '.' . RegPto::ATTR_CUST_REF . '=' . $asSnap . '.' . Snap::ATTR_CUSTOMER_ID;
        $onCalcRef = $asReg . '.' . RegPto::ATTR_CALC_REF . '=:' . self::BIND_CALC_REF;
        $on = "($onCustId) AND ($onCalcRef)";
        $result->joinLeft([$asReg => $tbl], $on, $cols);
        return $result;
    }

}