<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Repo\Query\Compress\Phase1\GetPv;

use Praxigento\Accounting\Data\Entity\Account as EAcc;
use Praxigento\Accounting\Data\Entity\Operation as EOper;
use Praxigento\Accounting\Data\Entity\Transaction as ETrans;
use Praxigento\BonusBase\Data\Entity\Log\Opers as ELogOpers;
use Praxigento\BonusHybrid\Config as Cfg;

/**
 * Build query to get PV data for phase 1 compression.
 */
class Builder
    extends \Praxigento\Core\Repo\Query\Def\Builder
{
    /**
     * Tables aliases.
     */
    const AS_TBL_ACC = 'acc';
    const AS_TBL_LOG = 'log';
    const AS_TBL_OPER = 'oper';
    const AS_TBL_TRANS = 'trans';
    /**
     * Attributes aliases.
     */
    const A_CUST_ID = EAcc::ATTR_CUST_ID;
    const A_PV = ETrans::ATTR_VALUE;
//    const A_CUST_ID = 'custId';
//    const A_PV = 'pv';

    /**
     * Bound variables names
     */
    const BIND_CALC_ID = 'calcId';

    /** @var  \Praxigento\Accounting\Repo\Entity\Type\IOperation */
    protected $repoTypeOper;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Accounting\Repo\Entity\Type\IOperation $repoTypeOper
    ) {
        parent::__construct($resource);
        $this->repoTypeOper = $repoTypeOper;
    }

    /**
     * Get operation type id for PV Write Off operation.
     *
     * @return int
     */
    protected function getPvWriteOffOperTypeId()
    {
        $result = $this->repoTypeOper->getIdByCode(Cfg::CODE_TYPE_OPER_PV_WRITE_OFF);
        return $result;
    }

    public function getSelectQuery(\Praxigento\Core\Repo\Query\IBuilder $qbuild = null)
    {
        $result = $this->conn->select(); // this is root builder

        /* define tables aliases */
        $asAcc = self::AS_TBL_ACC;
        $asLog = self::AS_TBL_LOG;
        $asOper = self::AS_TBL_OPER;
        $asTrans = self::AS_TBL_TRANS;

        /* SELECT FROM prxgt_bon_base_log_opers */
        $tbl = $this->resource->getTableName(ELogOpers::ENTITY_NAME);
        $as = $asLog;
        $cols = [];
        $result->from([$as => $tbl], $cols);

        /* LEFT JOIN prxgt_acc_operation */
        $tbl = $this->resource->getTableName(EOper::ENTITY_NAME);
        $as = $asOper;
        $on = $as . '.' . EOper::ATTR_ID . '=' . $asLog . '.' . ELogOpers::ATTR_OPER_ID;
        $cols = [];
        $result->joinLeft([$as => $tbl], $on, $cols);

        /* LEFT JOIN prxgt_acc_transaction */
        $tbl = $this->resource->getTableName(ETrans::ENTITY_NAME);
        $as = $asTrans;
        $on = $as . '.' . ETrans::ATTR_OPERATION_ID . '=' . $asOper . '.' . EOper::ATTR_ID;
        $cols = [
            self::A_PV => ETrans::ATTR_VALUE
        ];
        $result->joinLeft([$as => $tbl], $on, $cols);

        /* LEFT JOIN prxgt_acc_account */
        $tbl = $this->resource->getTableName(EAcc::ENTITY_NAME);
        $as = $asAcc;
        $on = $as . '.' . EAcc::ATTR_ID . '=' . $asTrans . '.' . ETrans::ATTR_DEBIT_ACC_ID;
        $cols = [
            self::A_CUST_ID => EAcc::ATTR_CUST_ID
        ];
        $result->joinLeft([$as => $tbl], $on, $cols);

        // where
        $operTypeId = (int)$this->getPvWriteOffOperTypeId();
        $whereByCalcId = "($asLog." . ELogOpers::ATTR_CALC_ID . '=:' . self::BIND_CALC_ID . ')';
        $whereByOperType = "($asOper." . EOper::ATTR_TYPE_ID . "=$operTypeId)";
        $result->where("$whereByOperType AND $whereByCalcId");

        return $result;
    }
}