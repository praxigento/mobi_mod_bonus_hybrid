<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Service\Calc\Sub;

use Praxigento\Accounting\Repo\Entity\Data\Account;
use Praxigento\Accounting\Repo\Entity\Data\Operation;
use Praxigento\Accounting\Repo\Entity\Data\Transaction;
use Praxigento\Accounting\Service\Account\Request\Get as AccountGetRequest;
use Praxigento\Accounting\Service\Account\Request\GetRepresentative as AccountGetRepresentativeRequest;
use Praxigento\Accounting\Service\Operation\Request\Add as OperationAddRequest;
use Praxigento\BonusBase\Repo\Entity\Data\Calculation;
use Praxigento\BonusBase\Repo\Entity\Data\Level;
use Praxigento\BonusBase\Repo\Entity\Data\Log\Customers as LogCustomers;
use Praxigento\BonusBase\Repo\Entity\Data\Log\Opers as LogOpers;
use Praxigento\BonusBase\Repo\Entity\Data\Log\Sales as LogSales;
use Praxigento\BonusBase\Repo\Entity\Data\Period;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Cfg\Override as CfgOverride;
use Praxigento\BonusHybrid\Repo\Entity\Data\Cfg\Param as CfgParam;
use Praxigento\BonusHybrid\Repo\Entity\Data\Compression\Oi as OiCompress;
use Praxigento\BonusHybrid\Repo\Entity\Data\Retro\Downline\Compressed\Phase1 as CmprsPhase1;
use Praxigento\Downline\Repo\Entity\Data\Customer;
use Praxigento\Downline\Service\Snap\Request\GetStateOnDate as DownlineSnapGetStateOnDateRequest;
use Praxigento\Pv\Repo\Entity\Data\Sale as PvSale;

class Db
{

    /** @var  \Praxigento\Accounting\Service\IAccount */
    protected $_callAccount;
    /** @var  \Praxigento\Downline\Service\ISnap */
    protected $_callDownlineSnap;
    /** @var  \Praxigento\Accounting\Service\IOperation */
    protected $_callOper;
    /** @var \Magento\Framework\DB\Adapter\AdapterInterface */
    protected $_conn;
    /** @var \Psr\Log\LoggerInterface */
    protected $_logger;
    /** @var \Praxigento\Core\Repo\IGeneric */
    protected $_repoBasic;
    /** @var  \Praxigento\Accounting\Repo\Entity\Type\Asset */
    protected $_repoTypeAsset;
    /** @var \Praxigento\BonusBase\Repo\Entity\Type\Calc */
    protected $_repoTypeCalc;
    /** @var  \Praxigento\Accounting\Repo\Entity\Type\Operation */
    protected $_repoTypeOper;
    /** @var \Magento\Framework\App\ResourceConnection */
    protected $_resource;
    /** @var  \Praxigento\Core\Tool\IDate */
    protected $_toolDate;
    /** @var  \Praxigento\Core\Tool\IPeriod */
    protected $_toolPeriod;
    /** @var \Praxigento\BonusBase\Repo\Entity\Log\Customers */
    protected $repoLogCust;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Retro\Downline\Plain */
    protected $repoRegPto;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Core\Tool\IDate $toolDate,
        \Praxigento\Core\Tool\IPeriod $toolPeriod,
        \Praxigento\Accounting\Service\IAccount $callAccount,
        \Praxigento\Accounting\Service\IOperation $repoOper,
        \Praxigento\BonusBase\Repo\Entity\Type\Calc $repoTypeCalc,
        \Praxigento\BonusBase\Repo\Entity\Log\Customers $repoLogCust,
        \Praxigento\BonusHybrid\Repo\Entity\Retro\Downline\Plain $repoRegPto,
        \Praxigento\Downline\Service\ISnap $callDownlineSnap,
        \Praxigento\Core\Repo\IGeneric $repoBasic,
        \Praxigento\Accounting\Repo\Entity\Type\Asset $repoTypeAsset,
        \Praxigento\Accounting\Repo\Entity\Type\Operation $repoTypeOper
    ) {
        $this->_logger = $logger;
        $this->_resource = $resource;
        $this->_conn = $resource->getConnection();
        $this->_toolDate = $toolDate;
        $this->_toolPeriod = $toolPeriod;
        $this->_callAccount = $callAccount;
        $this->_callDownlineSnap = $callDownlineSnap;
        $this->_callOper = $repoOper;
        $this->_repoBasic = $repoBasic;
        $this->_repoTypeCalc = $repoTypeCalc;
        $this->repoLogCust = $repoLogCust;
        $this->repoRegPto = $repoRegPto;
        $this->_repoTypeAsset = $repoTypeAsset;
        $this->_repoTypeOper = $repoTypeOper;
    }

    /**
     * Get bonus levels by calculation type code to calculate bonus fee.
     *
     * @param $calcTypeCode
     *
     * @return array [ level => percent, ... ]
     */
    public function getBonusLevels($calcTypeCode)
    {
        $result = [];
        $calcTypeId = $this->_repoTypeCalc->getIdByCode($calcTypeCode);
        $where = Level::ATTR_CALC_TYPE_ID . '=' . $calcTypeId;
        $order = Level::ATTR_LEVEL . ' ASC';
        $data = $this->_repoBasic->getEntities(Level::ENTITY_NAME, null, $where, $order);
        foreach ($data as $one) {
            $result[$one[Level::ATTR_LEVEL]] = $one[Level::ATTR_PERCENT];
        }
        return $result;
    }

    public function getCfgOverride()
    {
        $result = [];
        $data = $this->_repoBasic->getEntities(CfgOverride::ENTITY_NAME);
        foreach ($data as $one) {
            $scheme = $one[CfgOverride::ATTR_SCHEME];
            $rankId = $one[CfgOverride::ATTR_RANK_ID];
            $gen = $one[CfgOverride::ATTR_GENERATION];
            $result[$scheme][$rankId][$gen] = $one;
        }
        return $result;
    }

    /**
     * Get configuration for Override & Infinity bonuses ordered by scheme and leg max/medium/min desc.
     *
     * @return array [$scheme=>[$rankId=>[...], ...], ...]
     */
    public function getCfgParams()
    {
        $result = [];
        $order = [
            CfgParam::ATTR_SCHEME . ' ASC',
            CfgParam::ATTR_LEG_MAX . ' DESC',
            CfgParam::ATTR_LEG_MEDIUM . ' DESC',
            CfgParam::ATTR_LEG_MIN . ' DESC'
        ];
        $data = $this->_repoBasic->getEntities(CfgParam::ENTITY_NAME, null, null, $order);
        foreach ($data as $one) {
            $scheme = $one[CfgParam::ATTR_SCHEME];
            $rankId = $one[CfgParam::ATTR_RANK_ID];
            $result[$scheme][$rankId] = $one;
        }
        return $result;
    }

    /**
     * SELECT
     * cmp.*,
     * cust.human_ref,
     * cust.country_code
     * FROM prxgt_bon_hyb_cmprs_oi cmp
     * LEFT JOIN prxgt_dwnl_customer cust
     * ON cmp.customer_id = cust.customer_id
     * WHERE cmp.calc_id = 2;
     *
     * @param $calcId
     *
     * @return array [ [PtcCompress::*, Customer::HUMAN_REF, Customer::COUNTRY_CODE], ...]
     */
    public function getCompressedOiData($calcId)
    {
        /* aliases and tables */
        $asCompress = 'cmp';
        $asCust = 'cust';
        $tblCompress = $this->_resource->getTableName(OiCompress::ENTITY_NAME);
        $tblCust = $this->_resource->getTableName(Customer::ENTITY_NAME);
        // FROM prxgt_bon_hyb_cmprs_ptc cmp
        $query = $this->_conn->select();
        $query->from([$asCompress => $tblCompress]);
        // LEFT JOIN prxgt_dwnl_customer cust ON cmp.customer_id = cust.customer_id
        $on = "$asCompress." . OiCompress::ATTR_CUSTOMER_REF . "=$asCust." . Customer::ATTR_CUSTOMER_ID;
        $cols = [
            Customer::ATTR_HUMAN_REF,
            Customer::ATTR_COUNTRY_CODE
        ];
        $query->joinLeft([$asCust => $tblCust], $on, $cols);
        // where
        $where = OiCompress::ATTR_CALC_ID . '=' . (int)$calcId;
        $query->where($where);
        // $sql = (string)$query;
        $result = $this->_conn->fetchAll($query);
        return $result;
    }

    /**
     * SELECT
     * cmp.*,
     * cust.human_ref,
     * cust.country_code
     * FROM prxgt_bon_hyb_cmprs_ptc cmp
     * LEFT JOIN prxgt_dwnl_customer cust
     * ON cmp.customer_id = cust.customer_id
     * WHERE cmp.calc_id = 2;
     *
     * @param $calcId
     *
     * @return array [ [PtcCompress::*, Customer::HUMAN_REF, Customer::COUNTRY_CODE], ...]
     */
    public function getCompressedPtcData($calcId)
    {
        /* aliases and tables */
        $asCompress = 'cmp';
        $asCust = 'cust';
        $tblCompress = $this->_resource->getTableName(CmprsPhase1::ENTITY_NAME);
        $tblCust = $this->_resource->getTableName(Customer::ENTITY_NAME);
        // FROM prxgt_bon_hyb_cmprs_ptc cmp
        $query = $this->_conn->select();
        $query->from([$asCompress => $tblCompress]);
        // LEFT JOIN prxgt_dwnl_customer cust ON cmp.customer_id = cust.customer_id
        $on = "$asCompress." . CmprsPhase1::ATTR_CUSTOMER_REF . "=$asCust." . Customer::ATTR_CUSTOMER_ID;
        $cols = [
            Customer::ATTR_HUMAN_REF,
            Customer::ATTR_COUNTRY_CODE
        ];
        $query->joinLeft([$asCust => $tblCust], $on, $cols);
        // where
        $where = CmprsPhase1::ATTR_CALC_ID . '=' . (int)$calcId;
        $query->where($where);
        // $sql = (string)$query;
        $result = $this->_conn->fetchAll($query);
        return $result;
    }

    public function getDataForPvCompression($writeOffCalcId)
    {
        /* aliases and tables */
        $asLog = 'pbblo';
        $asOper = 'pao';
        $asTrans = 'pat';
        $asAcc = 'paa';
        $tblLog = $this->_resource->getTableName(LogOpers::ENTITY_NAME);
        $tblOper = $this->_resource->getTableName(Operation::ENTITY_NAME);
        $tblTrans = $this->_resource->getTableName(Transaction::ENTITY_NAME);
        $tblAcc = $this->_resource->getTableName(Account::ENTITY_NAME);
        /* IDs for codes */
        $operPvWriteOffId = $this->_repoTypeOper->getIdByCode(Cfg::CODE_TYPE_OPER_PV_WRITE_OFF);
        // SELECT FROM prxgt_bon_base_log_opers
        $query = $this->_conn->select();
        $cols = [LogOpers::ATTR_OPER_ID];
        $query->from([$asLog => $tblLog], $cols);
        // LEFT JOIN prxgt_acc_operation pao ON pbblo.oper_id = pao.id
        $on = "$asLog." . LogOpers::ATTR_OPER_ID . "=$asOper." . Operation::ATTR_ID;
        $cols = [
            Operation::ATTR_TYPE_ID
        ];
        $query->joinLeft([$asOper => $tblOper], $on, $cols);
        // LEFT JOIN prxgt_acc_transaction pat ON pao.id = pat.operation_id
        $on = "$asOper." . Operation::ATTR_ID . "=$asTrans." . Transaction::ATTR_OPERATION_ID;
        $cols = [
            Transaction::ATTR_VALUE
        ];
        $query->joinLeft([$asTrans => $tblTrans], $on, $cols);
        // LEFT JOIN prxgt_acc_account paa ON pat.debit_acc_id = paa.id
        $on = "$asTrans." . Transaction::ATTR_DEBIT_ACC_ID . "=$asAcc." . Account::ATTR_ID;
        $cols = [
            Account::ATTR_CUST_ID
        ];
        $query->joinLeft([$asAcc => $tblAcc], $on, $cols);
        // where
        $whereByCalcId = "($asLog." . LogOpers::ATTR_CALC_ID . "=$writeOffCalcId)";
        $whereByOperType = "$asOper." . Operation::ATTR_TYPE_ID . "=$operPvWriteOffId";
        $query->where("$whereByOperType AND $whereByCalcId");
        // $sql = (string)$query;
        $result = $this->_conn->fetchAll($query);
        return $result;
    }

    public function getDataForWriteOff($calcId, $tsFrom, $tsTo)
    {
        /* convert YYMMDD to YYYY-MM-DD HH:MM::SS */
        if(strlen($tsFrom)<10) $tsFrom = $this->_toolPeriod->getTimestampFrom($tsFrom);
        if(strlen($tsTo)<10) $tsTo = $this->_toolPeriod->getTimestampTo($tsTo);
        /* aliases and tables */
        $asOper = 'pao';
        $asTrans = 'pat';
        $asAcc = 'paa';
        $asLog = 'pbblo';
        $tblOper = $this->_resource->getTableName(Operation::ENTITY_NAME);
        $tblTrans = $this->_resource->getTableName(Transaction::ENTITY_NAME);
        $tblAcc = $this->_resource->getTableName(Account::ENTITY_NAME);
        $tblLog = $this->_resource->getTableName(LogOpers::ENTITY_NAME);
        /* IDs for codes */
        $assetId = $this->_repoTypeAsset->getIdByCode(Cfg::CODE_TYPE_ASSET_PV);
        $operPvWriteOffId = $this->_repoTypeOper->getIdByCode(Cfg::CODE_TYPE_OPER_PV_WRITE_OFF);
        // SELECT FROM prxgt_acc_operation
        $query = $this->_conn->select();
        $cols = [Operation::ATTR_ID];
        $query->from([$asOper => $tblOper], $cols);
        // LEFT JOIN prxgt_acc_transaction pat ON pao.id = pat.operation_id
        $on = "$asOper." . Operation::ATTR_ID . "=$asTrans." . Transaction::ATTR_OPERATION_ID;
        $cols = [
            Transaction::ATTR_DEBIT_ACC_ID,
            Transaction::ATTR_CREDIT_ACC_ID,
            Transaction::ATTR_VALUE
        ];
        $query->joinLeft([$asTrans => $tblTrans], $on, $cols);
        // LEFT JOIN prxgt_acc_account paa ON pat.debit_acc_id = paa.id
        $on = "$asTrans." . Transaction::ATTR_DEBIT_ACC_ID . "=$asAcc." . Account::ATTR_ID;
        $query->joinLeft([$asAcc => $tblAcc], $on, null);
        // LEFT JOIN prxgt_bon_base_log_opers pbblo ON pao.id = pbblo.oper_id
        $on = "$asOper." . Operation::ATTR_ID . "=$asLog." . LogOpers::ATTR_OPER_ID;
        $query->joinLeft([$asLog => $tblLog], $on, null);
        // where
        $whereByAssetType = "$asAcc." . Account::ATTR_ASSET_TYPE_ID . "=$assetId";
        $whereDateFrom = "$asTrans." . Transaction::ATTR_DATE_APPLIED . ">=" . $this->_conn->quote($tsFrom);
        $whereDateTo = "$asTrans." . Transaction::ATTR_DATE_APPLIED . "<=" . $this->_conn->quote($tsTo);
        $whereByOperType = "$asOper." . Operation::ATTR_TYPE_ID . "<>$operPvWriteOffId";
        $whereByCalcId = "($asLog." . LogOpers::ATTR_CALC_ID . " IS NULL OR $asLog." . LogOpers::ATTR_CALC_ID . "<>$calcId)";
        $query->where("$whereByAssetType AND $whereDateFrom AND $whereDateTo AND $whereByOperType AND $whereByCalcId");
        // $sql = (string)$query;
        $result = $this->_conn->fetchAll($query);
        return $result;
    }

    /**
     * Get downline customers referential data (flat, without).
     *
     * @return array [ [customer_id, human_ref, country_code], ...]
     */
    public function getDownlineCustomersData()
    {
        $cols = [Customer::ATTR_CUSTOMER_ID, Customer::ATTR_HUMAN_REF, Customer::ATTR_COUNTRY_CODE];
        $result = $this->_repoBasic->getEntities(Customer::ENTITY_NAME, $cols);
        return $result;
    }

    /**
     * Get Downline Tree snapshot on the $datestamp. Result is an array [$customerId => [...], ...]
     *
     * @param $datestamp 'YYYYMMDD'
     *
     * @return array|null
     */
    public function getDownlineSnapshot($datestamp)
    {
        $req = new DownlineSnapGetStateOnDateRequest();
        $req->setDatestamp($datestamp);
        $resp = $this->_callDownlineSnap->getStateOnDate($req);
        $result = $resp->get();
        return $result;
    }

    public function getLastCalculationIdForPeriod($calcTypeCode, $dsBegin, $dsEnd)
    {
        /* get calculation type ID for type code */
        $calcTypeId = $this->_repoTypeCalc->getIdByCode($calcTypeCode);
        /* aliases and tables */
        $asPeriod = 'pbbp';
        $asCalc = 'pbbc';
        $tblPeriod = $this->_resource->getTableName(Period::ENTITY_NAME);
        $tblCalc = $this->_resource->getTableName(Calculation::ENTITY_NAME);
        // FROM prxgt_bon_base_period
        $query = $this->_conn->select();
        $cols = [];
        $query->from([$asPeriod => $tblPeriod], $cols);
        // LEFT JOIN prxgt_bon_base_calc pbbc ON pbbp.id = pbbc.period_id
        $on = "$asPeriod." . Period::ATTR_ID . "=$asCalc." . Calculation::ATTR_PERIOD_ID;
        $cols = [
            Calculation::ATTR_ID
        ];
        $query->joinLeft([$asCalc => $tblCalc], $on, $cols);
        // where
        $whereCalcType = "($asPeriod." . Period::ATTR_CALC_TYPE_ID . "=:calcTypeId)";
        $wherePeriodBegin = "($asPeriod." . Period::ATTR_DSTAMP_BEGIN . "=:dsBegin)";
        $wherePeriodEnd = "($asPeriod." . Period::ATTR_DSTAMP_END . "=:dsEnd)";
        $query->where("$whereCalcType AND $wherePeriodBegin AND $wherePeriodEnd");
        // order by calculation id desc
        $query->order(Calculation::ATTR_ID . ' DESC');
        // limit
        $query->limit(1);
        // $sql = (string)$query;
        $result = $this->_conn->fetchOne(
            $query,
            [
                'calcTypeId' => $calcTypeId,
                'dsBegin' => $dsBegin,
                'dsEnd' => $dsEnd
            ]
        );
        return $result;
    }

    /**
     * Get not-compressed treee with Pv/Tv/Ov values for given calculation id.
     *
     * @param int $calcId
     * @return  array
     */
    public function getPlainPtoData($calcId)
    {
        $result = [];
        $where = \Praxigento\BonusHybrid\Repo\Entity\Data\Retro\Downline\Plain::ATTR_CALC_REF . '=' . (int)$calcId;
        $rows = $this->repoRegPto->get($where);
        /* TODO: use as object not as array */
        foreach ($rows as $row) {
            $data = (array)$row->get();
            $result[] = $data;
        }

        return $result;
    }

    /**
     * SELECT
     * pps.sale_id,
     * pps.date_paid,
     * sfo.base_grand_total,
     * ce.entity_id
     * FROM prxgt_pv_sale pps
     * LEFT JOIN sales_flat_order sfo
     * ON pps.sale_id = sfo.entity_id
     * LEFT JOIN customer_entity ce
     * ON sfo.customer_id = ce.entity_id
     * WHERE pps.date_paid >= '2016-01-01 00:00:00'
     * AND pps.date_paid <= '2016-01-31 23:59:59'
     *
     * @param $dsBegin - '20160101'
     * @param $dsEnd - '20160131'
     *
     * @return array [ $custId => [$orderId=>[$amount], ... ], ... ]
     */
    public function getSaleOrdersForRebate($dsBegin, $dsEnd)
    {
        $result = [];
        /* aliases and tables */
        $asPvSale = 'pps';
        $asMageSale = 'sfo';
        $asMageCust = 'ce';
        $tblPvSale = $this->_resource->getTableName(PvSale::ENTITY_NAME);
        $tblMageSale = $this->_resource->getTableName(Cfg::ENTITY_MAGE_SALES_ORDER);
        $tblMageCust = $this->_resource->getTableName(Cfg::ENTITY_MAGE_CUSTOMER);
        // FROM prxgt_pv_sale pps
        $query = $this->_conn->select();
        $cols = [
            PvSale::ATTR_SALE_ID,
            PvSale::ATTR_DATE_PAID
        ];
        $query->from([$asPvSale => $tblPvSale], $cols);
        // LEFT JOIN sales_flat_order sfo ON pps.sale_id = sfo.entity_id
        $on = "$asPvSale." . PvSale::ATTR_SALE_ID . "=$asMageSale." . Cfg::E_COMMON_A_ENTITY_ID;
        $cols = [
            Cfg::E_SALE_ORDER_A_BASE_GRAND_TOTAL
        ];
        $query->joinLeft([$asMageSale => $tblMageSale], $on, $cols);
        // LEFT JOIN customer_entity ce ON sfo.customer_id = ce.entity_id
        $on = "$asMageSale." . Cfg::E_SALE_ORDER_A_CUSTOMER_ID . "=$asMageCust." . Cfg::E_CUSTOMER_A_ENTITY_ID;
        $cols = [
            Cfg::E_CUSTOMER_A_ENTITY_ID
        ];
        $query->joinLeft([$asMageCust => $tblMageCust], $on, $cols);
        // where
        $from = $this->_toolPeriod->getTimestampFrom($dsBegin);
        $to = $this->_toolPeriod->getTimestampTo($dsEnd);
        $whereFrom = PvSale::ATTR_DATE_PAID . '>=' . $this->_conn->quote($from);
        $whereTo = PvSale::ATTR_DATE_PAID . '<=' . $this->_conn->quote($to);
        $wherePv = PvSale::ATTR_TOTAL . ">0";
        $query->where("$whereFrom AND $whereTo AND $wherePv");
        // $sql = (string)$query;
        $data = $this->_conn->fetchAll($query);
        foreach ($data as $item) {
            $custId = $item[Cfg::E_CUSTOMER_A_ENTITY_ID];
            $saleId = $item[PvSale::ATTR_SALE_ID];
            $amount = $item[Cfg::E_SALE_ORDER_A_BASE_GRAND_TOTAL];
            $result[$custId][$saleId] = $amount;
        }
        return $result;
    }

    /**
     * @param $calcId
     * @return int
     *
     * @deprecated see \Praxigento\BonusBase\Repo\Entity\Calculation::markComplete
     */
    public function markCalcComplete($calcId)
    {
        $tsEnded = $this->_toolDate->getUtcNowForDb();
        $bind = [
            Calculation::ATTR_DATE_ENDED => $tsEnded,
            Calculation::ATTR_STATE => Cfg::CALC_STATE_COMPLETE
        ];
        $where = Calculation::ATTR_ID . '=' . $calcId;
        $result = $this->_repoBasic->updateEntity(Calculation::ENTITY_NAME, $bind, $where);
        return $result;
    }

    public function saveCompressedOi($data, $calcId)
    {
        foreach ($data as $one) {
            $one[OiCompress::ATTR_CALC_ID] = $calcId;
            $this->_repoBasic->addEntity(OiCompress::ENTITY_NAME, $one);
        }
    }

    /**
     * @param $data
     * @param $calcId
     *
     * @deprecated see \Praxigento\BonusHybrid\Service\Calc\CompressPhase1::saveBonusDownline
     */
    public function saveCompressedPtc($data, $calcId)
    {
        foreach ($data as $one) {
            if (!isset($one[CmprsPhase1::ATTR_CUSTOMER_REF]) || !isset($one[CmprsPhase1::ATTR_PARENT_REF])) {
                $this->_logger->warning("There is no IDs in record: " . var_export($one, true));
                continue;
            }
            $one[CmprsPhase1::ATTR_CALC_ID] = $calcId;
            if (!isset($one[CmprsPhase1::ATTR_PV]) || is_null($one[CmprsPhase1::ATTR_PV])) {
                $one[CmprsPhase1::ATTR_PV] = 0;
            }
            $this->_repoBasic->addEntity(CmprsPhase1::ENTITY_NAME, $one);
        }
    }

    public function saveLogCustomers($updates, $transIds)
    {
        if (count($updates) != count($transIds)) {
            throw new \Exception("Cannot log transactions for the customers, sizes of the arrays are not equal.");
        }
        foreach ($updates as $i => $item) {
            $transId = $transIds[$i];
            $custId = $item[Calc::A_OTHER_ID];
            $this->repoLogCust->create([
                LogCustomers::ATTR_TRANS_ID => $transId,
                LogCustomers::ATTR_CUSTOMER_ID => $custId

            ]);
        }
    }

    /**
     * Save customers log for Team bonus transactions (DEFAULT scheme).
     *
     * @param array $transIds [$transId => $custId]
     */
    public function saveLogCustomersTeam($transIds)
    {
        foreach ($transIds as $transId => $custId) {
            $this->repoLogCust->create([
                LogCustomers::ATTR_TRANS_ID => $transId,
                LogCustomers::ATTR_CUSTOMER_ID => $custId

            ]);
        }
    }

    public function saveLogOperations($operId, $calcId)
    {
        $this->_repoBasic->addEntity(
            LogOpers::ENTITY_NAME,
            [
                LogOpers::ATTR_CALC_ID => $calcId,
                LogOpers::ATTR_OPER_ID => $operId
            ]
        );
    }

    public function saveLogPvWriteOff($data, $operIdWriteOff, $calcId)
    {
        /* log all PV related operations */
        $uniqueOperIds = [];
        foreach ($data as $one) {
            /* MOBI-628 : some operations could consist of many transactions */
            $operId = $one[Operation::ATTR_ID];
            if (!isset($uniqueOperIds[$operId])) {
                $this->saveLogOperations($one[Operation::ATTR_ID], $calcId);
                $uniqueOperIds[$operId] = true;
            }
        }
        /* log PvWriteOff operation */
        $this->saveLogOperations($operIdWriteOff, $calcId);
    }

    public function saveLogSaleOrders($updates, $transIds)
    {
        if (count($updates) != count($transIds)) {
            throw new \Exception("Cannot log transactions for the sale orders, sizes of the arrays are not equal.");
        }
        foreach ($updates as $i => $item) {
            $transId = $transIds[$i];
            $saleId = $item[Calc::A_ORDR_ID];
            $bind = [
                LogSales::ATTR_TRANS_ID => $transId,
                LogSales::ATTR_SALE_ORDER_ID => $saleId

            ];
            $this->_repoBasic->addEntity(LogSales::ENTITY_NAME, $bind);
        }
    }

    /**
     * $updates contains PV Account IDs not Customer IDs.
     *
     * @param      $updates array [$accountId => $value, ...]
     * @param null $datePerformed
     * @param null $dateApplied
     *
     * @return array|null
     */
    public function saveOperationPvWriteOff($updates, $datePerformed = null, $dateApplied = null)
    {
        /* prepare additional data */
        $datePerformed = is_null($datePerformed) ? $this->_toolDate->getUtcNowForDb() : $datePerformed;
        $dateApplied = is_null($dateApplied) ? $datePerformed : $dateApplied;
        /* get asset type ID */
        $assetTypeId = $this->_repoTypeAsset->getIdByCode(Cfg::CODE_TYPE_ASSET_PV);
        /* get representative account data */
        $reqAccRepres = new AccountGetRepresentativeRequest();
        $reqAccRepres->setAssetTypeId($assetTypeId);
        $respAccRepres = $this->_callAccount->getRepresentative($reqAccRepres);
        $represAccId = $respAccRepres->get(Account::ATTR_ID);
        /* save operation */
        $req = new OperationAddRequest();
        $req->setOperationTypeCode(Cfg::CODE_TYPE_OPER_PV_WRITE_OFF);
        $req->setDatePerformed($datePerformed);
        $trans = [];
        $reqGetAccount = new AccountGetRequest();
        $reqGetAccount->setCreateNewAccountIfMissed();
        $reqGetAccount->setAssetTypeId($assetTypeId);
        foreach ($updates as $accId => $value) {
            if ($value > 0) {
                /* skip representative account */
                if ($accId == $represAccId) {
                    continue;
                }
                $trans[] = [
                    Transaction::ATTR_DEBIT_ACC_ID => $accId,
                    Transaction::ATTR_CREDIT_ACC_ID => $represAccId,
                    Transaction::ATTR_DATE_APPLIED => $dateApplied,
                    Transaction::ATTR_VALUE => $value
                ];

                $this->_logger->debug("Transaction ($value) for customer with account #$accId is added to"
                    . " operation '" . Cfg::CODE_TYPE_OPER_PV_WRITE_OFF . "'.");
            } else {
                $this->_logger->debug("Transaction for customer with account #$accId is 0.00. Transaction is not included"
                    . " in operation '" . Cfg::CODE_TYPE_OPER_PV_WRITE_OFF . "'.");
            }
        }
        $req->setTransactions($trans);
        $resp = $this->_callOper->add($req);
        $result = $resp->getOperationId();
        $this->_logger->debug("New '" . Cfg::CODE_TYPE_OPER_PV_WRITE_OFF . "' operation is added with id=$result.");
        return $result;
    }

    /**
     * @param array $updates array [[Calc::A_CUST_ID, Calc::A_VALUE], ...]
     * @param string $operTypeCode
     * @param string|null $datePerformed
     * @param string|null $dateApplied
     * @param string|null $transRef if set, this value will be used to bind transactions with $updates
     *
     * @return \Praxigento\Accounting\Service\Operation\Response\Add
     */
    public function saveOperationWalletActive(
        $updates,
        $operTypeCode,
        $datePerformed = null,
        $dateApplied = null,
        $transRef = null
    ) {
        /* prepare additional data */
        $datePerformed = is_null($datePerformed) ? $this->_toolDate->getUtcNowForDb() : $datePerformed;
        $dateApplied = is_null($dateApplied) ? $datePerformed : $dateApplied;
        /* get asset type ID */
        $assetTypeId = $this->_repoTypeAsset->getIdByCode(Cfg::CODE_TYPE_ASSET_WALLET_ACTIVE);
        /* get representative account data */
        $reqAccRepres = new AccountGetRepresentativeRequest();
        $reqAccRepres->setAssetTypeId($assetTypeId);
        $respAccRepres = $this->_callAccount->getRepresentative($reqAccRepres);
        $represAccId = $respAccRepres->get(Account::ATTR_ID);;
        /* save operation */
        $req = new OperationAddRequest();
        $req->setOperationTypeCode($operTypeCode);
        $req->setDatePerformed($datePerformed);
        $trans = [];
        $reqGetAccount = new AccountGetRequest();
        $reqGetAccount->setCreateNewAccountIfMissed();
        $reqGetAccount->setAssetTypeId($assetTypeId);
        foreach ($updates as $item) {
            $customerId = $item[Calc::A_CUST_ID];
            $value = $item[Calc::A_VALUE];
            if ($value > 0) {
                /* get WALLET_ACTIVE account ID for customer */
                $reqGetAccount->setCustomerId($customerId);
                $respGetAccount = $this->_callAccount->get($reqGetAccount);
                $accId = $respGetAccount->get(Account::ATTR_ID);
                /* skip representative account */
                if ($accId == $represAccId) continue;

                $one = [
                    Transaction::ATTR_DEBIT_ACC_ID => $represAccId,
                    Transaction::ATTR_CREDIT_ACC_ID => $accId,
                    Transaction::ATTR_DATE_APPLIED => $dateApplied,
                    Transaction::ATTR_VALUE => $value
                ];

                if (!is_null($transRef)) $one[$transRef] = $item[$transRef];
                $trans[] = $one;

                $this->_logger->debug("Transaction ($value) for customer #$customerId (acc #$accId) is added to operation '$operTypeCode'.");
            } else {
                $this->_logger->debug("Transaction for customer #$customerId is 0.00. Transaction is not included in operation '$operTypeCode'.");
            }
        }
        $req->setTransactions($trans);
        if (!is_null($transRef)) $req->setAsTransRef($transRef);
        $result = $this->_callOper->add($req);
        $operId = $result->getOperationId();
        $this->_logger->debug("New '$operTypeCode' operation is added with id=$operId.");
        return $result;
    }

    /**
     * Save post Override bonus updates in OI Compression data.
     *
     * @param $updates
     * @param $calcId
     */
    public function saveUpdatesOiCompress($updates, $calcId)
    {
        foreach ($updates as $custId => $bind) {
            $whereByCalcId = OiCompress::ATTR_CALC_ID . '=' . (int)$calcId;
            $whereByCustId = OiCompress::ATTR_CUSTOMER_REF . '=' . (int)$custId;
            $where = "$whereByCalcId AND $whereByCustId";
            $this->_repoBasic->updateEntity(OiCompress::ENTITY_NAME, $bind, $where);
        }
    }

    /**
     * Update calculated Organizational Volumes in compressed data table.
     *
     * @param $data
     * @param $calcId
     */
    public function saveValueOv($data, $calcId)
    {
        foreach ($data as $custId => $ov) {
            $whereByCalcId = CmprsPhase1::ATTR_CALC_ID . '=' . $calcId;
            $whereByCustId = CmprsPhase1::ATTR_CUSTOMER_REF . '=' . $custId;
            $this->_repoBasic->updateEntity(
                CmprsPhase1::ENTITY_NAME,
                [CmprsPhase1::ATTR_OV => $ov],
                "$whereByCalcId AND $whereByCustId"
            );
        }
    }

    /**
     * Update calculated Team Volumes in compressed data table.
     *
     * @param $data
     * @param $calcId
     */
    public function saveValueTv($data, $calcId)
    {
        foreach ($data as $custId => $tv) {
            $whereByCalcId = CmprsPhase1::ATTR_CALC_ID . '=' . $calcId;
            $whereByCustId = CmprsPhase1::ATTR_CUSTOMER_REF . '=' . $custId;
            $this->_repoBasic->updateEntity(
                CmprsPhase1::ENTITY_NAME,
                [CmprsPhase1::ATTR_TV => $tv],
                "$whereByCalcId AND $whereByCustId"
            );
        }
    }

}