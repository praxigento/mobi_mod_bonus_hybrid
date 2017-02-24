<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Repo\Query\SignupDebit;

use Praxigento\BonusBase\Data\Entity\Calculation as Calc;
use Praxigento\BonusBase\Data\Entity\Period as Period;
use Praxigento\BonusBase\Data\Entity\Type\Calc as TypeCalc;
use Praxigento\BonusHybrid\Config as Cfg;

class GetLastCalcIdForPeriod
    extends \Praxigento\Core\Repo\Def\Db
{
    const BIND_CODE = 'calcTypeCode';
    const BIND_STATE = 'calcState';

    /**
     *
     * SELECT *
     * FROM prxgt_bon_base_type_calc pbbtc
     * LEFT JOIN prxgt_bon_base_period pbbp
     * ON pbbtc.id = pbbp.calc_type_id
     * LEFT JOIN prxgt_bon_base_calc pbbc
     * ON pbbp.id = pbbc.period_id
     * WHERE pbbtc.code = "HYBRID_BON_SIGNUP_DEBIT"
     * AND pbbc.state = "complete"
     * ORDER BY pbbc.id DESC LIMIT 1;
     *
     * @return string
     */
    public function exec()
    {
        /* aliases and tables */
        $asTypeCalc = 'ct';
        $asPeriod = 'p';
        $asCalc = 'c';
        /* SELECT FROM prxgt_bon_base_type_calc */
        $query = $this->conn->select();
        $tbl = $this->resource->getTableName(TypeCalc::ENTITY_NAME);
        $cols = [];
        $query->from([$asTypeCalc => $tbl], $cols);
        /*  LEFT JOIN prxgt_bon_base_period */
        $tbl = $this->resource->getTableName(Period::ENTITY_NAME);
        $on = $asPeriod . '.' . Period::ATTR_CALC_TYPE_ID . '=' . $asTypeCalc . '.' . TypeCalc::ATTR_ID;
        $cols = [];
        $query->joinLeft([$asPeriod => $tbl], $on, $cols);
        /* LEFT JOIN prxgt_bon_base_calc */
        $tbl = $this->resource->getTableName(Calc::ENTITY_NAME);
        $on = $asCalc . '.' . Calc::ATTR_PERIOD_ID . '=' . $asPeriod . '.' . Period::ATTR_ID;
        $cols = [Calc::ATTR_ID];
        $query->joinLeft([$asCalc => $tbl], $on, $cols);
        /* WHERE */
        $where = $asTypeCalc . '.' . TypeCalc::ATTR_CODE . '=:' . self::BIND_CODE;
        $where .= ' AND ' . $asCalc . '.' . Calc::ATTR_STATE . '=:' . self::BIND_STATE;
        $query->where($where);
        /* ORDER */
        $order = $asCalc . '.' . Calc::ATTR_ID . ' DESC';
        $query->order($order);
        /* LIMIT 1 */
        $query->limit(1);
        /* bind vars and fetch results */
        $bind = [
            self::BIND_CODE => Cfg::CODE_TYPE_CALC_BONUS_SIGNUP_DEBIT,
            self::BIND_STATE => Cfg::CALC_STATE_COMPLETE
        ];
        $result = $this->conn->fetchOne($query, $bind);
        return $result;
    }
}