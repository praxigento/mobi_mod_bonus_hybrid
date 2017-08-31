<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\PvWriteOff\Query;

use Praxigento\Accounting\Repo\Entity\Data\Account as EAcc;
use Praxigento\Accounting\Repo\Entity\Data\Operation as EOper;
use Praxigento\Accounting\Repo\Entity\Data\Transaction as ETrans;
use Praxigento\BonusBase\Repo\Entity\Data\Log\Opers as ELogOpers;
use Praxigento\BonusHybrid\Config as Cfg;

/**
 * Get accounting data for 'PV Write Off' calculation.
 */
class GetData
{
    /** @var \Magento\Framework\DB\Adapter\AdapterInterface */
    protected $conn;
    /** @var  \Praxigento\Core\Tool\IPeriod */
    protected $hlpPeriod;
    /** @var  \Praxigento\Accounting\Repo\Entity\Type\Asset */
    protected $repoTypeAsset;
    /** @var  \Praxigento\Accounting\Repo\Entity\Type\Operation */
    protected $repoTypeOper;
    /** @var \Magento\Framework\App\ResourceConnection */
    protected $resource;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Core\Tool\IPeriod $hlpPeriod,
        \Praxigento\Accounting\Repo\Entity\Type\Asset $repoTypeAsset,
        \Praxigento\Accounting\Repo\Entity\Type\Operation $repoTypeOper
    )
    {
        $this->resource = $resource;
        $this->conn = $resource->getConnection();
        $this->hlpPeriod = $hlpPeriod;
        $this->repoTypeAsset = $repoTypeAsset;
        $this->repoTypeOper = $repoTypeOper;
    }


    public function exec($calcId, $tsFrom, $tsTo)
    {
        /* convert YYMMDD to YYYY-MM-DD HH:MM::SS */
        if (strlen($tsFrom) < 10) $tsFrom = $this->hlpPeriod->getTimestampFrom($tsFrom);
        if (strlen($tsTo) < 10) $tsTo = $this->hlpPeriod->getTimestampTo($tsTo);
        /* aliases and tables */
        $asOper = 'pao';
        $asTrans = 'pat';
        $asAcc = 'paa';
        $asLog = 'pbblo';
        $tblOper = $this->resource->getTableName(EOper::ENTITY_NAME);
        $tblTrans = $this->resource->getTableName(ETrans::ENTITY_NAME);
        $tblAcc = $this->resource->getTableName(EAcc::ENTITY_NAME);
        $tblLog = $this->resource->getTableName(ELogOpers::ENTITY_NAME);
        /* IDs for codes */
        $assetId = $this->repoTypeAsset->getIdByCode(Cfg::CODE_TYPE_ASSET_PV);
        $operPvWriteOffId = $this->repoTypeOper->getIdByCode(Cfg::CODE_TYPE_OPER_PV_WRITE_OFF);
        // SELECT FROM prxgt_acc_operation
        $query = $this->conn->select();
        $cols = [EOper::ATTR_ID];
        $query->from([$asOper => $tblOper], $cols);
        // LEFT JOIN prxgt_acc_transaction pat ON pao.id = pat.operation_id
        $on = "$asOper." . EOper::ATTR_ID . "=$asTrans." . ETrans::ATTR_OPERATION_ID;
        $cols = [
            ETrans::ATTR_DEBIT_ACC_ID,
            ETrans::ATTR_CREDIT_ACC_ID,
            ETrans::ATTR_VALUE
        ];
        $query->joinLeft([$asTrans => $tblTrans], $on, $cols);
        // LEFT JOIN prxgt_acc_account paa ON pat.debit_acc_id = paa.id
        $on = "$asTrans." . ETrans::ATTR_DEBIT_ACC_ID . "=$asAcc." . EAcc::ATTR_ID;
        $query->joinLeft([$asAcc => $tblAcc], $on, null);
        // LEFT JOIN prxgt_bon_base_log_opers pbblo ON pao.id = pbblo.oper_id
        $on = "$asOper." . EOper::ATTR_ID . "=$asLog." . ELogOpers::ATTR_OPER_ID;
        $query->joinLeft([$asLog => $tblLog], $on, null);
        // where
        $whereByAssetType = "$asAcc." . EAcc::ATTR_ASSET_TYPE_ID . "=$assetId";
        $whereDateFrom = "$asTrans." . ETrans::ATTR_DATE_APPLIED . ">=" . $this->conn->quote($tsFrom);
        $whereDateTo = "$asTrans." . ETrans::ATTR_DATE_APPLIED . "<=" . $this->conn->quote($tsTo);
        $whereByOperType = "$asOper." . EOper::ATTR_TYPE_ID . "<>$operPvWriteOffId";
        $whereByCalcId = "($asLog." . ELogOpers::ATTR_CALC_ID . " IS NULL OR $asLog." . ELogOpers::ATTR_CALC_ID . "<>$calcId)";
        $query->where("$whereByAssetType AND $whereDateFrom AND $whereDateTo AND $whereByOperType AND $whereByCalcId");
        // $sql = (string)$query;
        $result = $this->conn->fetchAll($query);
        return $result;
    }
}