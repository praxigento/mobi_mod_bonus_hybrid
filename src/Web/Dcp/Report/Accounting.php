<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Web\Dcp\Report;

use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting\Request as ARequest;
use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting\Response as AResponse;
use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting\Response\Data as DRespData;
use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting\Response\Data\Balance as DRespBalance;
use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting\Response\Data\Customer as DRespCust;
use Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting\Response\Data\Trans as DRespTrans;
use Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Accounting\Trans\Builder as QBAccTrans;
use Praxigento\BonusHybrid\Web\Dcp\Report\Accounting\Repo\Query\GetBalance\Builder as QBBal;
use Praxigento\Core\Tool\IPeriod as HPeriod;
use Praxigento\Downline\Repo\Query\Customer\Get as QBCust;

class Accounting
    extends \Praxigento\Core\App\Api\Web\Processor\WithQuery
    implements \Praxigento\BonusHybrid\Api\Web\Dcp\Report\AccountingInterface
{
    /**
     * Parameter names for local customizations of the queries.
     */
    const BND_CUST_ID = 'custId'; // to get asset balances
    const CTX_QUERY_BAL = 'queryBalance';
    const CTX_QUERY_CUST = 'queryCustomer';
    /**
     * Name of the local context variables.
     */
    const VAR_CUST_ID = 'custId';
    /** @deprecated remove it if not used */
    const VAR_CUST_PATH = 'path';
    const VAR_DATE_CLOSE = 'dateClose';
    const VAR_DATE_FROM = 'dateTo'; // date before period start
    const VAR_DATE_OPEN = 'dateOpen'; // the last date for period
    const VAR_DATE_TO = 'dateFrom';
    /** @var \Praxigento\Core\App\Api\Web\IAuthenticator */
    private $authenticator;
    /** @var \Praxigento\Core\Tool\IPeriod */
    private $hlpPeriod;
    /** @var \Praxigento\BonusHybrid\Web\Dcp\Report\Accounting\Repo\Query\GetBalance\Builder */
    private $qbBalance;
    /** @var \Praxigento\Downline\Repo\Query\Customer\Get */
    private $qbCust;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\Core\Tool\IPeriod $hlpPeriod,
        \Praxigento\Core\App\Api\Web\Authenticator\Front $authenticator,
        \Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Accounting\Trans\Builder $qbDcpTrans,
        \Praxigento\BonusHybrid\Web\Dcp\Report\Accounting\Repo\Query\GetBalance\Builder $qbBalance,
        \Praxigento\Downline\Repo\Query\Customer\Get $qbCust
    ) {
        parent::__construct($manObj, $qbDcpTrans);
        $this->authenticator = $authenticator;
        $this->hlpPeriod = $hlpPeriod;
        $this->qbBalance = $qbBalance;
        $this->qbCust = $qbCust;
    }

    protected function authorize(\Praxigento\Core\Data $ctx) {
        /* do nothing - in Production Mode current customer's ID is used as root customer ID */
    }

    protected function createQuerySelect(\Praxigento\Core\Data $ctx) {
        parent::createQuerySelect($ctx);
        /* add more query builders */
        $query = $this->qbBalance->build();
        $ctx->set(self::CTX_QUERY_BAL, $query);
        $query = $this->qbCust->build();
        $ctx->set(self::CTX_QUERY_CUST, $query);

    }

    public function exec(ARequest $request): AResponse {
        $data = parent::process($request);
        $result = new AResponse($data);
        return $result;
    }

    protected function performQuery(\Praxigento\Core\Data $ctx) {
        /* get working vars from context */
        $var = $ctx->get(self::CTX_VARS);
        $custId = $var->get(self::VAR_CUST_ID);
        $dsOpen = $var->get(self::VAR_DATE_OPEN);
        $dsClose = $var->get(self::VAR_DATE_CLOSE);

        /* get transactions as primary query (sorted, filtered, etc.)*/
        $trans = $this->queryTrans($ctx);

        /** get balances */
        /** @var \Magento\Framework\DB\Select $queryBal */
        $queryBal = $ctx->get(self::CTX_QUERY_BAL);
        $bindBal = [
            QBBal::BIND_MAX_DATE => $dsOpen,
            self::BND_CUST_ID => $custId
        ];
        $balOpen = $this->queryBalances($queryBal, $bindBal);
        $bindBal [QBBal::BIND_MAX_DATE] = $dsClose;
        $balClose = $this->queryBalances($queryBal, $bindBal);

        /** @var \Magento\Framework\DB\Select $queryCust */
        $queryCust = $ctx->get(self::CTX_QUERY_CUST);
        $bindCust = [
            self::BND_CUST_ID => $custId
        ];
        $cust = $this->queryCustomer($queryCust, $bindCust);

        /* re-assemble result data (there are more than 1 query in operation) */
        $result = new DRespData();
        $result->setTrans($trans);
        $result->setBalanceOpen($balOpen);
        $result->setBalanceClose($balClose);
        $result->setCustomer($cust);

        $ctx->set(self::CTX_RESULT, $result);
    }

    protected function populateQuery(\Praxigento\Core\Data $ctx) {
        /* get working vars from context */
        /** @var \Praxigento\Core\Data $bind */
        $bind = $ctx->get(self::CTX_BIND);
        /** @var \Praxigento\Core\Data $vars */
        $vars = $ctx->get(self::CTX_VARS);

        /* get working vars */
        $custId = $vars->get(self::VAR_CUST_ID);
        $dateFrom = $vars->get(self::VAR_DATE_FROM);
        $dateTo = $vars->get(self::VAR_DATE_TO);

        /* bind values for query parameters */
        $bind->set(QBAccTrans::BND_CUST_ID, $custId);
        $bind->set(QBAccTrans::BND_DATE_FROM, $dateFrom);
        $bind->set(QBAccTrans::BND_DATE_TO, $dateTo);

    }

    protected function prepareQueryParameters(\Praxigento\Core\Data $ctx) {
        /* get working vars from context */
        /** @var \Praxigento\Core\Data $vars */
        $vars = $ctx->get(self::CTX_VARS);
        /** @var \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting\Request $req */
        $req = $ctx->get(self::CTX_REQ);
        $reqData = $req->getData();
        $reqDev = $req->getDev();

        /* extract HTTP request parameters */
        $period = $reqData->getPeriod();
        $devCustId = $reqDev->getCustId();

        /**
         * Define period.
         */
        if (!$period) {
            $period = $this->hlpPeriod->getPeriodCurrent(null, 0, HPeriod::TYPE_MONTH);
        }
        /* apply dates for transactions */
        $dateFrom = $this->hlpPeriod->getTimestampFrom($period, HPeriod::TYPE_MONTH);
        $dateTo = $this->hlpPeriod->getTimestampTo($period, HPeriod::TYPE_MONTH);
        /* dates for balances */
        $dateFirst = $this->hlpPeriod->getPeriodFirstDate($period, HPeriod::TYPE_MONTH);
        $dsOpen = $this->hlpPeriod->getPeriodPrev($dateFirst);
        $dsClose = $this->hlpPeriod->getPeriodLastDate($period, HPeriod::TYPE_MONTH);


        /**
         * Define root customer & path to the root customer on the date.
         */
        /* TODO: add authorization */
        $request = new \Praxigento\Core\App\Api\Web\Request();
        $dev = new \Praxigento\Core\App\Api\Web\Request\Dev();
        $dev->setCustId($devCustId);
        $request->setDev($dev);
        $custId = $this->authenticator->getCurrentUserId($request);

        /* save working variables into execution context */
        $vars->set(self::VAR_CUST_ID, $custId);
        $vars->set(self::VAR_DATE_FROM, $dateFrom);
        $vars->set(self::VAR_DATE_TO, $dateTo);
        $vars->set(self::VAR_DATE_OPEN, $dsOpen);
        $vars->set(self::VAR_DATE_CLOSE, $dsClose);
    }

    /**
     * Perform 'get balance' query (for open/close balance) and compose API compatible result.
     *
     * @param \Magento\Framework\DB\Select $query
     * @param $bind
     * @return DRespBalance[]
     */
    private function queryBalances(\Magento\Framework\DB\Select $query, $bind) {
        $result = [];
        $conn = $query->getConnection();
        $rs = $conn->fetchAll($query, $bind);
        foreach ($rs as $one) {
            $asset = $one[QBBal::A_ASSET];
            $value = $one[QBBal::A_BALANCE];
            $item = new DRespBalance();
            $item->setAsset($asset);
            $item->setValue($value);
            $result[] = $item;
        }
        return $result;
    }

    /**
     * @param \Magento\Framework\DB\Select $query
     * @param $bind
     * @return \Praxigento\BonusHybrid\Api\Web\Dcp\Report\Accounting\Response\Data\Customer
     */
    private function queryCustomer(\Magento\Framework\DB\Select $query, $bind) {
        /* perform query and collect data */
        $conn = $query->getConnection();
        $rs = $conn->fetchRow($query, $bind);
        $id = $rs[QBCust::A_ID];
        $mlmId = $rs[QBCust::A_MLM_ID];
        $nameFirst = $rs[QBCust::A_NAME_FIRST];
        $nameLast = $rs[QBCust::A_NAME_LAST];

        /* assemble result */
        $result = new DRespCust();
        $result->setId($id);
        $result->setMlmId($mlmId);
        $result->setNameFirst($nameFirst);
        $result->setNameLast($nameLast);
        return $result;
    }

    /**
     * @param \Praxigento\Core\Data $ctx
     * @return DRespTrans[]
     */
    private function queryTrans(\Praxigento\Core\Data $ctx) {
        $result = [];
        /* get transactions details */
        parent::performQuery($ctx);
        $rs = $ctx->get(self::CTX_RESULT);
        foreach ($rs as $tran) {
            /* parse query entry */
            $asset = $tran[QBAccTrans::A_ASSET];
            $date = $tran[QBAccTrans::A_DATE];
            $details = $tran[QBAccTrans::A_DETAILS];
            $itemId = $tran[QBAccTrans::A_ITEM_ID];
            $otherCustId = $tran[QBAccTrans::A_OTHER_CUST_ID];
            $type = $tran[QBAccTrans::A_TYPE];
            $value = $tran[QBAccTrans::A_VALUE];
            /* compose API entry */
            $item = new DRespTrans();
            $item->setAsset($asset);
            $item->setCustomerId($otherCustId);
            $item->setDate($date);
            $item->setDetails($details);
            $item->setTransId($itemId);
            $item->setType($type);
            $item->setValue($value);
            $result[] = $item;
        }
        return $result;
    }

}