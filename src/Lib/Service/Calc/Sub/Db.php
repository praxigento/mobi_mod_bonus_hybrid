<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib\Service\Calc\Sub;

use Praxigento\Accounting\Data\Entity\Account;
use Praxigento\Accounting\Data\Entity\Operation;
use Praxigento\Accounting\Data\Entity\Transaction;
use Praxigento\Accounting\Lib\Service\Account\Request\Get as AccountGetRequest;
use Praxigento\Accounting\Lib\Service\Account\Request\GetRepresentative as AccountGetRepresentativeRequest;
use Praxigento\Accounting\Lib\Service\Operation\Request\Add as OperationAddRequest;
use Praxigento\Accounting\Lib\Service\Type\Asset\Request\GetByCode as TypeAssetGetByCodeRequest;
use Praxigento\Accounting\Lib\Service\Type\Operation\Request\GetByCode as TypeOperGetByCodeRequest;
use Praxigento\Bonus\Base\Lib\Entity\Calculation;
use Praxigento\Bonus\Base\Lib\Entity\Level;
use Praxigento\Bonus\Base\Lib\Entity\Log\Customers as LogCustomers;
use Praxigento\Bonus\Base\Lib\Entity\Log\Opers as LogOpers;
use Praxigento\Bonus\Base\Lib\Entity\Log\Sales as LogSales;
use Praxigento\Bonus\Base\Lib\Entity\Period;
use Praxigento\Bonus\Base\Lib\Service\Type\Calc\Request\GetByCode as TypeCalcGetByCodeRequest;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\Bonus\Hybrid\Lib\Entity\Cfg\Override as CfgOverride;
use Praxigento\Bonus\Hybrid\Lib\Entity\Cfg\Param as CfgParam;
use Praxigento\Bonus\Hybrid\Lib\Entity\Compression\Oi as OiCompress;
use Praxigento\Bonus\Hybrid\Lib\Entity\Compression\Ptc as PtcCompress;
use Praxigento\Core\Lib\Service\Repo\Request\AddEntity as RepoAddEntityRequest;
use Praxigento\Core\Lib\Service\Repo\Request\GetEntities as RepoGetEntitiesRequest;
use Praxigento\Core\Lib\Service\Repo\Request\UpdateEntity as RepoUpdateEntityRequest;
use Praxigento\Downline\Data\Entity\Customer;
use Praxigento\Downline\Lib\Service\Snap\Request\GetStateOnDate as DownlineSnapGetStateOnDateRequest;
use Praxigento\Pv\Data\Entity\Sale as PvSale;

class Db extends \Praxigento\Core\Lib\Service\Base\Sub\Db {

    /** @var  \Praxigento\Accounting\Lib\Service\IAccount */
    private $_callAccount;
    /** @var  \Praxigento\Downline\Lib\Service\ISnap */
    private $_callDownlineSnap;
    /** @var  \Praxigento\Accounting\Lib\Service\IOperation */
    private $_callOper;
    /** @var  \Praxigento\Accounting\Lib\Service\ITypeAsset */
    private $_callTypeAsset;
    /** @var \Praxigento\Bonus\Base\Lib\Service\ITypeCalc */
    private $_callTypeCalc;
    /** @var  \Praxigento\Accounting\Lib\Service\ITypeOperation */
    private $_callTypeOper;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Praxigento\Core\Lib\Context\IDbAdapter $dba,
        \Praxigento\Core\Lib\IToolbox $toolbox,
        \Praxigento\Core\Lib\Service\IRepo $callRepo,
        \Praxigento\Accounting\Lib\Service\IAccount $callAccount,
        \Praxigento\Accounting\Lib\Service\IOperation $callOper,
        \Praxigento\Accounting\Lib\Service\ITypeAsset $callTypeAsset,
        \Praxigento\Bonus\Base\Lib\Service\ITypeCalc $callTypeCalc,
        \Praxigento\Accounting\Lib\Service\ITypeOperation $callTypeOper,
        \Praxigento\Downline\Lib\Service\ISnap $callDownlineSnap
    ) {
        parent::__construct($logger, $dba, $toolbox, $callRepo);
        $this->_callAccount = $callAccount;
        $this->_callDownlineSnap = $callDownlineSnap;
        $this->_callOper = $callOper;
        $this->_callTypeAsset = $callTypeAsset;
        $this->_callTypeCalc = $callTypeCalc;
        $this->_callTypeOper = $callTypeOper;
    }

    /**
     * Get bonus levels by calculation type code to calculate bonus fee.
     *
     * @param $calcTypeCode
     *
     * @return array [ level => percent, ... ]
     */
    public function getBonusLevels($calcTypeCode) {
        $result = [ ];
        $respCalcType = $this->_callTypeCalc->getByCode(new TypeCalcGetByCodeRequest($calcTypeCode));
        $calcTypeId = $respCalcType->getId();
        $where = Level::ATTR_CALC_TYPE_ID . '=' . $calcTypeId;
        $order = Level::ATTR_LEVEL . ' ASC';
        $req = new RepoGetEntitiesRequest(Level::ENTITY_NAME, $where, null, $order);
        $resp = $this->_callRepo->getEntities($req);
        $data = $resp->getData();
        foreach($data as $one) {
            $result[$one[Level::ATTR_LEVEL]] = $one[Level::ATTR_PERCENT];
        }
        return $result;
    }

    public function getCfgOverride() {
        $result = [ ];
        $req = new RepoGetEntitiesRequest(CfgOverride::ENTITY_NAME);
        $resp = $this->_callRepo->getEntities($req);
        $data = $resp->getData();
        foreach($data as $one) {
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
    public function getCfgParams() {
        $result = [ ];
        $order = [
            CfgParam::ATTR_SCHEME . ' ASC',
            CfgParam::ATTR_LEG_MAX . ' DESC',
            CfgParam::ATTR_LEG_MEDIUM . ' DESC',
            CfgParam::ATTR_LEG_MIN . ' DESC'
        ];
        $req = new RepoGetEntitiesRequest(CfgParam::ENTITY_NAME, null, null, $order);
        $resp = $this->_callRepo->getEntities($req);
        $data = $resp->getData();
        foreach($data as $one) {
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
    public function getCompressedOiData($calcId) {
        /* aliases and tables */
        $asCompress = 'cmp';
        $asCust = 'cust';
        $tblCompress = $this->_getTableName(OiCompress::ENTITY_NAME);
        $tblCust = $this->_getTableName(Customer::ENTITY_NAME);
        // FROM prxgt_bon_hyb_cmprs_ptc cmp
        $query = $this->_getConn()->select();
        $query->from([ $asCompress => $tblCompress ]);
        // LEFT JOIN prxgt_dwnl_customer cust ON cmp.customer_id = cust.customer_id
        $on = "$asCompress." . OiCompress::ATTR_CUSTOMER_ID . "=$asCust." . Customer::ATTR_CUSTOMER_ID;
        $cols = [
            Customer::ATTR_HUMAN_REF,
            Customer::ATTR_COUNTRY_CODE
        ];
        $query->joinLeft([ $asCust => $tblCust ], $on, $cols);
        // where
        $where = OiCompress::ATTR_CALC_ID . '=' . (int)$calcId;
        $query->where($where);
        // $sql = (string)$query;
        $result = $this->_getConn()->fetchAll($query);
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
    public function getCompressedPtcData($calcId) {
        /* aliases and tables */
        $asCompress = 'cmp';
        $asCust = 'cust';
        $tblCompress = $this->_getTableName(PtcCompress::ENTITY_NAME);
        $tblCust = $this->_getTableName(Customer::ENTITY_NAME);
        // FROM prxgt_bon_hyb_cmprs_ptc cmp
        $query = $this->_getConn()->select();
        $query->from([ $asCompress => $tblCompress ]);
        // LEFT JOIN prxgt_dwnl_customer cust ON cmp.customer_id = cust.customer_id
        $on = "$asCompress." . PtcCompress::ATTR_CUSTOMER_ID . "=$asCust." . Customer::ATTR_CUSTOMER_ID;
        $cols = [
            Customer::ATTR_HUMAN_REF,
            Customer::ATTR_COUNTRY_CODE
        ];
        $query->joinLeft([ $asCust => $tblCust ], $on, $cols);
        // where
        $where = PtcCompress::ATTR_CALC_ID . '=' . (int)$calcId;
        $query->where($where);
        // $sql = (string)$query;
        $result = $this->_getConn()->fetchAll($query);
        return $result;
    }

    public function getDataForPvCompression($writeOffCalcId) {
        /* aliases and tables */
        $asLog = 'pbblo';
        $asOper = 'pao';
        $asTrans = 'pat';
        $asAcc = 'paa';
        $tblLog = $this->_getTableName(LogOpers::ENTITY_NAME);
        $tblOper = $this->_getTableName(Operation::ENTITY_NAME);
        $tblTrans = $this->_getTableName(Transaction::ENTITY_NAME);
        $tblAcc = $this->_getTableName(Account::ENTITY_NAME);
        /* IDs for codes */
        $respGetByCode = $this->_callTypeOper->getByCode(new TypeOperGetByCodeRequest(Cfg::CODE_TYPE_OPER_PV_WRITE_OFF));
        $operPvWriteOffId = $respGetByCode->getId();
        // SELECT FROM prxgt_bon_base_log_opers
        $query = $this->_getConn()->select();
        $cols = [ LogOpers::ATTR_OPER_ID ];
        $query->from([ $asLog => $tblLog ], $cols);
        // LEFT JOIN prxgt_acc_operation pao ON pbblo.oper_id = pao.id
        $on = "$asLog." . LogOpers::ATTR_OPER_ID . "=$asOper." . Operation::ATTR_ID;
        $cols = [
            Operation::ATTR_TYPE_ID
        ];
        $query->joinLeft([ $asOper => $tblOper ], $on, $cols);
        // LEFT JOIN prxgt_acc_transaction pat ON pao.id = pat.operation_id
        $on = "$asOper." . Operation::ATTR_ID . "=$asTrans." . Transaction::ATTR_OPERATION_ID;
        $cols = [
            Transaction::ATTR_VALUE
        ];
        $query->joinLeft([ $asTrans => $tblTrans ], $on, $cols);
        // LEFT JOIN prxgt_acc_account paa ON pat.debit_acc_id = paa.id
        $on = "$asTrans." . Transaction::ATTR_DEBIT_ACC_ID . "=$asAcc." . Account::ATTR_ID;
        $cols = [
            Account::ATTR_CUST_ID
        ];
        $query->joinLeft([ $asAcc => $tblAcc ], $on, $cols);
        // where
        $whereByCalcId = "($asLog." . LogOpers::ATTR_CALC_ID . "=$writeOffCalcId)";
        $whereByOperType = "$asOper." . Operation::ATTR_TYPE_ID . "=$operPvWriteOffId";
        $query->where("$whereByOperType AND $whereByCalcId");
        // $sql = (string)$query;
        $result = $this->_getConn()->fetchAll($query);
        return $result;
    }

    public function getDataForWriteOff($calcId, $tsFrom, $tsTo) {
        /* aliases and tables */
        $asOper = 'pao';
        $asTrans = 'pat';
        $asAcc = 'paa';
        $asLog = 'pbblo';
        $tblOper = $this->_getTableName(Operation::ENTITY_NAME);
        $tblTrans = $this->_getTableName(Transaction::ENTITY_NAME);
        $tblAcc = $this->_getTableName(Account::ENTITY_NAME);
        $tblLog = $this->_getTableName(LogOpers::ENTITY_NAME);
        /* IDs for codes */
        $respGetByCode = $this->_callTypeAsset->getByCode(new TypeAssetGetByCodeRequest(Cfg::CODE_TYPE_ASSET_PV));
        $assetId = $respGetByCode->getId();
        $respGetByCode = $this->_callTypeOper->getByCode(new TypeOperGetByCodeRequest(Cfg::CODE_TYPE_OPER_PV_WRITE_OFF));
        $operPvWriteOffId = $respGetByCode->getId();
        // SELECT FROM prxgt_acc_operation
        $query = $this->_getConn()->select();
        $cols = [ Operation::ATTR_ID ];
        $query->from([ $asOper => $tblOper ], $cols);
        // LEFT JOIN prxgt_acc_transaction pat ON pao.id = pat.operation_id
        $on = "$asOper." . Operation::ATTR_ID . "=$asTrans." . Transaction::ATTR_OPERATION_ID;
        $cols = [
            Transaction::ATTR_DEBIT_ACC_ID,
            Transaction::ATTR_CREDIT_ACC_ID,
            Transaction::ATTR_VALUE
        ];
        $query->joinLeft([ $asTrans => $tblTrans ], $on, $cols);
        // LEFT JOIN prxgt_acc_account paa ON pat.debit_acc_id = paa.id
        $on = "$asTrans." . Transaction::ATTR_DEBIT_ACC_ID . "=$asAcc." . Account::ATTR_ID;
        $query->joinLeft([ $asAcc => $tblAcc ], $on, null);
        // LEFT JOIN prxgt_bon_base_log_opers pbblo ON pao.id = pbblo.oper_id
        $on = "$asOper." . Operation::ATTR_ID . "=$asLog." . LogOpers::ATTR_OPER_ID;
        $query->joinLeft([ $asLog => $tblLog ], $on, null);
        // where
        $whereByAssetType = "$asAcc." . Account::ATTR_ASSET_TYPE_ID . "=$assetId";
        $whereDateFrom = "$asTrans." . Transaction::ATTR_DATE_APPLIED . ">=" . $this->_getConn()->quote($tsFrom);
        $whereDateTo = "$asTrans." . Transaction::ATTR_DATE_APPLIED . "<=" . $this->_getConn()->quote($tsTo);
        $whereByOperType = "$asOper." . Operation::ATTR_TYPE_ID . "<>$operPvWriteOffId";
        $whereByCalcId = "($asLog." . LogOpers::ATTR_CALC_ID . " IS NULL OR $asLog." . LogOpers::ATTR_CALC_ID . "<>$calcId)";
        $query->where("$whereByAssetType AND $whereDateFrom AND $whereDateTo AND $whereByOperType AND $whereByCalcId");
        // $sql = (string)$query;
        $result = $this->_getConn()->fetchAll($query);
        return $result;
    }

    /**
     * Get downline customers referential data (flat, without).
     *
     * @return array [ [customer_id, human_ref, country_code], ...]
     */
    public function getDownlineCustomersData() {
        $cols = [ Customer::ATTR_CUSTOMER_ID, Customer::ATTR_HUMAN_REF, Customer::ATTR_COUNTRY_CODE ];
        $req = new RepoGetEntitiesRequest(Customer::ENTITY_NAME, null, $cols);
        $resp = $this->_callRepo->getEntities($req);
        $result = $resp->getData();
        return $result;
    }

    /**
     * Get Downline Tree snapshot on the $datestamp. Result is an array [$customerId => [...], ...]
     *
     * @param $datestamp 'YYYYMMDD'
     *
     * @return array|null
     */
    public function getDownlineSnapshot($datestamp) {
        $req = new DownlineSnapGetStateOnDateRequest();
        $req->setDatestamp($datestamp);
        $resp = $this->_callDownlineSnap->getStateOnDate($req);
        $result = $resp->getData();
        return $result;
    }

    public function getLastCalculationIdForPeriod($calcTypeCode, $dsBegin, $dsEnd) {
        /* get calculation type ID for type code */
        $respCalcType = $this->_callTypeCalc->getByCode(new TypeCalcGetByCodeRequest($calcTypeCode));
        $calcTypeId = $respCalcType->getId();
        /* aliases and tables */
        $asPeriod = 'pbbp';
        $asCalc = 'pbbc';
        $tblPeriod = $this->_getTableName(Period::ENTITY_NAME);
        $tblCalc = $this->_getTableName(Calculation::ENTITY_NAME);
        // FROM prxgt_bon_base_period
        $query = $this->_getConn()->select();
        $cols = [ ];
        $query->from([ $asPeriod => $tblPeriod ], $cols);
        // LEFT JOIN prxgt_bon_base_calc pbbc ON pbbp.id = pbbc.period_id
        $on = "$asPeriod." . Period::ATTR_ID . "=$asCalc." . Calculation::ATTR_PERIOD_ID;
        $cols = [
            Calculation::ATTR_ID
        ];
        $query->joinLeft([ $asCalc => $tblCalc ], $on, $cols);
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
        $result = $this->_getConn()->fetchOne(
            $query,
            [
                'calcTypeId' => $calcTypeId,
                'dsBegin'    => $dsBegin,
                'dsEnd'      => $dsEnd
            ]
        );
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
    public function getSaleOrdersForRebate($dsBegin, $dsEnd) {
        $result = [ ];
        /* aliases and tables */
        $asPvSale = 'pps';
        $asMageSale = 'sfo';
        $asMageCust = 'ce';
        $tblPvSale = $this->_getTableName(PvSale::ENTITY_NAME);
        $tblMageSale = $this->_getTableName(Cfg::ENTITY_MAGE_SALES_ORDER);
        $tblMageCust = $this->_getTableName(Cfg::ENTITY_MAGE_CUSTOMER);
        // FROM prxgt_pv_sale pps
        $query = $this->_getConn()->select();
        $cols = [
            PvSale::ATTR_SALE_ID,
            PvSale::ATTR_DATE_PAID
        ];
        $query->from([ $asPvSale => $tblPvSale ], $cols);
        // LEFT JOIN sales_flat_order sfo ON pps.sale_id = sfo.entity_id
        $on = "$asPvSale." . PvSale::ATTR_SALE_ID . "=$asMageSale." . Cfg::E_COMMON_A_ENTITY_ID;
        $cols = [
            Cfg::E_SALE_ORDER_A_BASE_GRAND_TOTAL
        ];
        $query->joinLeft([ $asMageSale => $tblMageSale ], $on, $cols);
        // LEFT JOIN customer_entity ce ON sfo.customer_id = ce.entity_id
        $on = "$asMageSale." . Cfg::E_SALE_ORDER_A_CUSTOMER_ID . "=$asMageCust." . Cfg::E_CUSTOMER_A_ENTITY_ID;
        $cols = [
            Cfg::E_CUSTOMER_A_ENTITY_ID
        ];
        $query->joinLeft([ $asMageCust => $tblMageCust ], $on, $cols);
        // where
        $from = $this->_toolbox->getPeriod()->getTimestampFrom($dsBegin);
        $to = $this->_toolbox->getPeriod()->getTimestampTo($dsEnd);
        $whereFrom = PvSale::ATTR_DATE_PAID . '>=' . $this->_getConn()->quote($from);
        $whereTo = PvSale::ATTR_DATE_PAID . '<=' . $this->_getConn()->quote($to);
        $wherePv = PvSale::ATTR_TOTAL . ">0";
        $query->where("$whereFrom AND $whereTo AND $wherePv");
        // $sql = (string)$query;
        $data = $this->_getConn()->fetchAll($query);
        foreach($data as $item) {
            $custId = $item[Cfg::E_CUSTOMER_A_ENTITY_ID];
            $saleId = $item[PvSale::ATTR_SALE_ID];
            $amount = $item[Cfg::E_SALE_ORDER_A_BASE_GRAND_TOTAL];
            $result[$custId][$saleId] = $amount;
        }
        return $result;
    }

    public function markCalcComplete($calcId) {
        $tsEnded = $this->_toolbox->getDate()->getUtcNowForDb();
        $bind = [
            Calculation::ATTR_DATE_ENDED => $tsEnded,
            Calculation::ATTR_STATE      => Cfg::CALC_STATE_COMPLETE
        ];
        $where = Calculation::ATTR_ID . '=' . $calcId;
        $req = new RepoUpdateEntityRequest(Calculation::ENTITY_NAME, $bind, $where);
        $resp = $this->_callRepo->updateEntity($req);
        return $resp->isSucceed();
    }

    public function saveCompressedOi($data, $calcId) {
        $req = new RepoAddEntityRequest(OiCompress::ENTITY_NAME);
        foreach($data as $one) {
            $one[OiCompress::ATTR_CALC_ID] = $calcId;
            $req->setBind($one);
            $this->_callRepo->addEntity($req);
        }
    }

    public function saveCompressedPtc($data, $calcId) {
        $req = new RepoAddEntityRequest(PtcCompress::ENTITY_NAME);
        foreach($data as $one) {
            if(!isset($one[PtcCompress::ATTR_CUSTOMER_ID]) || !isset($one[PtcCompress::ATTR_PARENT_ID])) {
                $this->_logger->warning("There is no IDs in record: " . var_export($one, true));
                continue;
            }
            $one[PtcCompress::ATTR_CALC_ID] = $calcId;
            if(!isset($one[PtcCompress::ATTR_PV]) || is_null($one[PtcCompress::ATTR_PV])) {
                $one[PtcCompress::ATTR_PV] = 0;
            }
            $req->setBind($one);
            $this->_callRepo->addEntity($req);
        }
    }

    public function saveLogCustomers($updates, $transIds) {
        if(count($updates) != count($transIds)) {
            throw new \Exception("Cannot log transactions for the customers, sizes of the arrays are not equal.");
        }
        $req = new RepoAddEntityRequest(LogCustomers::ENTITY_NAME);
        foreach($updates as $i => $item) {
            $transId = $transIds[$i];
            $custId = $item[Calc::A_OTHER_ID];
            $req->setBind([
                LogCustomers::ATTR_TRANS_ID    => $transId,
                LogCustomers::ATTR_CUSTOMER_ID => $custId

            ]);
            $resp = $this->_callRepo->addEntity($req);
            if(!$resp->isSucceed()) {
                throw new \Exception("Cannot add new record () to log transactions for customers.");
            }
        }
    }

    public function saveLogOperations($operId, $calcId) {
        $req = new RepoAddEntityRequest(LogOpers::ENTITY_NAME);
        $req->setBind([
            LogOpers::ATTR_CALC_ID => $calcId,
            LogOpers::ATTR_OPER_ID => $operId
        ]);
        $this->_callRepo->addEntity($req);
    }

    public function saveLogPvWriteOff($data, $operIdWriteOff, $calcId) {
        /* log all PV related operations */
        foreach($data as $one) {
            $this->saveLogOperations($one[Operation::ATTR_ID], $calcId);
        }
        /* log PvWriteOff operation */
        $this->saveLogOperations($operIdWriteOff, $calcId);
    }

    public function saveLogSaleOrders($updates, $transIds) {
        if(count($updates) != count($transIds)) {
            throw new \Exception("Cannot log transactions for the sale orders, sizes of the arrays are not equal.");
        }
        $req = new RepoAddEntityRequest(LogSales::ENTITY_NAME);
        foreach($updates as $i => $item) {
            $transId = $transIds[$i];
            $saleId = $item[Calc::A_ORDR_ID];
            $req->setBind([
                LogSales::ATTR_TRANS_ID       => $transId,
                LogSales::ATTR_SALES_ORDER_ID => $saleId

            ]);
            $resp = $this->_callRepo->addEntity($req);
            if(!$resp->isSucceed()) {
                throw new \Exception("Cannot add new record () to log transactions for the sale orders.");
            }
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
    public function saveOperationPvWriteOff($updates, $datePerformed = null, $dateApplied = null) {
        /* prepare additional data */
        $datePerformed = is_null($datePerformed) ? $this->_toolbox->getDate()->getUtcNowForDb() : $datePerformed;
        $dateApplied = is_null($dateApplied) ? $datePerformed : $dateApplied;
        /* get asset type ID */
        $respTypeAsset = $this->_callTypeAsset->getByCode(new TypeAssetGetByCodeRequest(Cfg::CODE_TYPE_ASSET_PV));
        $assetTypeId = $respTypeAsset->getId();
        /* get representative account data */
        $reqAccRepres = new AccountGetRepresentativeRequest();
        $reqAccRepres->setAssetTypeId($assetTypeId);
        $respAccRepres = $this->_callAccount->getRepresentative($reqAccRepres);
        $represAccId = $respAccRepres->getData(Account::ATTR_ID);
        /* save operation */
        $req = new OperationAddRequest();
        $req->setOperationTypeCode(Cfg::CODE_TYPE_OPER_PV_WRITE_OFF);
        $req->setDatePerformed($datePerformed);
        $trans = [ ];
        $reqGetAccount = new AccountGetRequest();
        $reqGetAccount->setCreateNewAccountIfMissed();
        $reqGetAccount->setAssetTypeId($assetTypeId);
        foreach($updates as $accId => $value) {
            if($value > 0) {
                /* skip representative account */
                if($accId == $represAccId) {
                    continue;
                }
                $trans[] = [
                    Transaction::ATTR_DEBIT_ACC_ID  => $accId,
                    Transaction::ATTR_CREDIT_ACC_ID => $represAccId,
                    Transaction::ATTR_DATE_APPLIED  => $dateApplied,
                    Transaction::ATTR_VALUE         => $value
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
     * @param      $updates array [[Calc::A_CUST_ID, Calc::A_VALUE], ...]
     * @param      $operTypeCode
     * @param null $datePerformed
     * @param null $dateApplied
     *
     * @return \Praxigento\Accounting\Lib\Service\Operation\Response\Add
     */
    public function saveOperationWalletActive($updates, $operTypeCode, $datePerformed = null, $dateApplied = null) {
        /* prepare additional data */
        $datePerformed = is_null($datePerformed) ? $this->_toolbox->getDate()->getUtcNowForDb() : $datePerformed;
        $dateApplied = is_null($dateApplied) ? $datePerformed : $dateApplied;
        /* get asset type ID */
        $respTypeAsset = $this->_callTypeAsset->getByCode(new TypeAssetGetByCodeRequest(Cfg::CODE_TYPE_ASSET_WALLET_ACTIVE));
        $assetTypeId = $respTypeAsset->getId();
        /* get representative account data */
        $reqAccRepres = new AccountGetRepresentativeRequest();
        $reqAccRepres->setAssetTypeId($assetTypeId);
        $respAccRepres = $this->_callAccount->getRepresentative($reqAccRepres);
        $represAccId = $respAccRepres->getData(Account::ATTR_ID);;
        /* save operation */
        $req = new OperationAddRequest();
        $req->setOperationTypeCode($operTypeCode);
        $req->setDatePerformed($datePerformed);
        $trans = [ ];
        $reqGetAccount = new AccountGetRequest();
        $reqGetAccount->setCreateNewAccountIfMissed();
        $reqGetAccount->setAssetTypeId($assetTypeId);
        foreach($updates as $item) {
            $customerId = $item[Calc::A_CUST_ID];
            $value = $item[Calc::A_VALUE];
            if($value > 0) {
                /* get WALLET_ACTIVE account ID for customer */
                $reqGetAccount->setCustomerId($customerId);
                $respGetAccount = $this->_callAccount->get($reqGetAccount);
                $accId = $respGetAccount->getData(Account::ATTR_ID);
                /* skip representative account */
                if($accId == $represAccId) {
                    continue;
                }
                $trans[] = [
                    Transaction::ATTR_DEBIT_ACC_ID  => $represAccId,
                    Transaction::ATTR_CREDIT_ACC_ID => $accId,
                    Transaction::ATTR_DATE_APPLIED  => $dateApplied,
                    Transaction::ATTR_VALUE         => $value
                ];

                $this->_logger->debug("Transaction ($value) for customer #$customerId (acc #$accId) is added to operation '$operTypeCode'.");
            } else {
                $this->_logger->debug("Transaction for customer #$customerId is 0.00. Transaction is not included in operation '$operTypeCode'.");
            }
        }
        $req->setTransactions($trans);
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
    public function saveUpdatesOiCompress($updates, $calcId) {
        $req = new RepoUpdateEntityRequest(OiCompress::ENTITY_NAME);
        foreach($updates as $custId => $bind) {
            $req->setBind($bind);
            $whereByCalcId = OiCompress::ATTR_CALC_ID . '=' . $calcId;
            $whereByCustId = OiCompress::ATTR_CUSTOMER_ID . '=' . $custId;
            $req->setWhere("$whereByCalcId AND $whereByCustId");
            $this->_callRepo->updateEntity($req);
        }
    }

    /**
     * Update calculated Organizational Volumes in compressed data table.
     *
     * @param $data
     * @param $calcId
     */
    public function saveValueOv($data, $calcId) {
        $req = new RepoUpdateEntityRequest(PtcCompress::ENTITY_NAME);
        foreach($data as $custId => $ov) {
            $req->setBind([ PtcCompress::ATTR_OV => $ov ]);
            $whereByCalcId = PtcCompress::ATTR_CALC_ID . '=' . $calcId;
            $whereByCustId = PtcCompress::ATTR_CUSTOMER_ID . '=' . $custId;
            $req->setWhere("$whereByCalcId AND $whereByCustId");
            $this->_callRepo->updateEntity($req);
        }
    }

    /**
     * Update calculated Team Volumes in compressed data table.
     *
     * @param $data
     * @param $calcId
     */
    public function saveValueTv($data, $calcId) {
        $req = new RepoUpdateEntityRequest(PtcCompress::ENTITY_NAME);
        foreach($data as $custId => $tv) {
            $req->setBind([ PtcCompress::ATTR_TV => $tv ]);
            $whereByCalcId = PtcCompress::ATTR_CALC_ID . '=' . $calcId;
            $whereByCustId = PtcCompress::ATTR_CUSTOMER_ID . '=' . $custId;
            $req->setWhere("$whereByCalcId AND $whereByCustId");
            $this->_callRepo->updateEntity($req);
        }
    }

}