<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Api\Stats;

use Praxigento\BonusHybrid\Api\Stats\Base\Query\GetLastCalc as QGetLastCalc;
use Praxigento\BonusHybrid\Config as Cfg;

class Phase1
    implements \Praxigento\BonusHybrid\Api\Stats\Phase1Interface
{

    const BIND_CALC_REF = 'calcRef';
    const BIND_MAX_DEPTH = 'maxDepth';
    const BIND_PATH = 'path';

    const CTX_BIND = 'bind';
    const CTX_QUERY = 'query';
    const CTX_REQ = 'request';
    const CTX_RESULT = 'result';
    const CTX_VARS = 'vars';

    const VAR_ON_DATE = 'on_date';
    const VAR_ROOT_CUSTOMER_ID = 'root_cust_id';

    /** @var \Praxigento\Core\Api\IAuthenticator */
    protected $authenticator;
    /** @var  \Praxigento\BonusHybrid\Api\Stats\Base\Query\GetLastCalc */
    protected $qPeriodCalc;
    /** @var \Praxigento\BonusHybrid\Repo\Query\Stats\Phase1\Builder */
    protected $qbldStatsPhase1;
    /** @var \Praxigento\Downline\Repo\Entity\ISnap */
    protected $repoSnap;
    /** @var \Praxigento\Core\Tool\IPeriod */
    protected $toolPeriod;

    public function __construct(
        \Praxigento\Core\Api\IAuthenticator $authenticator,
        \Praxigento\Core\Tool\IPeriod $toolPeriod,
        \Praxigento\BonusHybrid\Repo\Query\Stats\Phase1\Builder $qbldStatsPhase1,
        \Praxigento\Downline\Repo\Entity\ISnap $repoSnap,
        \Praxigento\BonusHybrid\Api\Stats\Base\Query\GetLastCalc $qPeriodCalc

    ) {
        $this->authenticator = $authenticator;
        $this->toolPeriod = $toolPeriod;
        $this->qbldStatsPhase1 = $qbldStatsPhase1;
        $this->repoSnap = $repoSnap;
        $this->qPeriodCalc = $qPeriodCalc;
    }

    public function exec(\Praxigento\BonusHybrid\Api\Stats\Phase1\Request $data)
    {
        $result = new \Praxigento\BonusHybrid\Api\Stats\Phase1\Response();
        if ($data->getRequestReturn()) {
            $result->setRequest($data);
        }

        /* create context for request processing */
        $ctx = new \Flancer32\Lib\Data();
        $ctx->set(self::CTX_REQ, $data);
        $ctx->set(self::CTX_QUERY, null);
        $ctx->set(self::CTX_BIND, new \Flancer32\Lib\Data());
        $ctx->set(self::CTX_VARS, new \Flancer32\Lib\Data());
        $ctx->set(self::CTX_RESULT, null);

        /* parse request, prepare query and fetch data */
        $this->prepareQueryParameters($ctx);
        $this->getSelectQuery($ctx);
        $this->populateQuery($ctx);
        $this->performQuery($ctx);

        /* get query results from context and add to API response */
        $rs = $ctx->get(self::CTX_RESULT);
        $result->setData($rs);
        return $result;
    }

    /**
     * @param \Flancer32\Lib\Data $ctx
     */
    protected function getSelectQuery(\Flancer32\Lib\Data $ctx)
    {
        $query = $this->qbldStatsPhase1->getSelectQuery();
        $ctx->set(self::CTX_QUERY, $query);
    }

    protected function performQuery(\Flancer32\Lib\Data $ctx)
    {
        /* get working vars from context */
        $bind = $ctx->get(self::CTX_BIND);
        $query = $ctx->get(self::CTX_QUERY);

        $conn = $query->getConnection();
        $rs = $conn->fetchAll($query, (array)$bind->get());

        $ctx->set(self::CTX_RESULT, $rs);
    }

    /**
     * Populate query and bound parameters according to request data (from $bind).
     *
     * @param \Flancer32\Lib\Data $ctx
     */
    protected function populateQuery(\Flancer32\Lib\Data $ctx)
    {
        /* get working vars from context */
        /** @var \Flancer32\Lib\Data $bind */
        $bind = $ctx->get(self::CTX_BIND);
        /** @var \Flancer32\Lib\Data $vars */
        $vars = $ctx->get(self::CTX_VARS);
        /** @var \Magento\Framework\DB\Select $query */
        $query = $ctx->get(self::CTX_QUERY);
        /** @var \Praxigento\BonusHybrid\Api\Stats\Plain\Request $req */
        $req = $ctx->get(self::CTX_REQ);

        /* collect important parameters (request, vars & query) */
        $onDate = $vars->get(self::VAR_ON_DATE);
        $rootCustId = $vars->get(self::VAR_ROOT_CUSTOMER_ID);
        $maxDepth = $req->getMaxDepth();

        /* filter data by root customer's path (on the given date) */
        $customerRoot = $this->repoSnap->getByCustomerIdOnDate($rootCustId, $onDate);
        $idRoot = $customerRoot->getCustomerId();
        $pathRoot = $customerRoot->getPath();
        $where = \Praxigento\BonusHybrid\Repo\Query\Stats\Phase1\Builder::AS_TREE . '.' .
            \Praxigento\BonusHybrid\Entity\Compression\Ptc::ATTR_PATH . ' LIKE :' . self::BIND_PATH;
        $bind->set(self::BIND_PATH, $pathRoot . $idRoot . Cfg::DTPS . '%');
        $query->where($where);

        /* filter data by max depth in downline tree */
        if (!is_null($maxDepth)) {
            /* depth started from 0, add +1 to start from root */
            $filterDepth = $customerRoot->getDepth() + 1 + $maxDepth;
            $where = \Praxigento\BonusHybrid\Repo\Query\Stats\Phase1\Builder::AS_TREE . '.' .
                \Praxigento\BonusHybrid\Entity\Compression\Ptc::ATTR_DEPTH . ' < :' . self::BIND_MAX_DEPTH;
            $bind->set(self::BIND_MAX_DEPTH, $filterDepth);
            $query->where($where);
        }

        /* filter data by calcId */
        $where = \Praxigento\BonusHybrid\Repo\Query\Stats\Phase1\Builder::AS_TREE . '.' .
            \Praxigento\BonusHybrid\Entity\Compression\Ptc::ATTR_CALC_ID . ' = :' . self::BIND_CALC_REF;
        $query->where($where);
    }

    /**
     * Analyze request data, collect expected parameters and place its to execution context.
     *
     * @param \Flancer32\Lib\Data $ctx
     */
    protected function prepareQueryParameters(\Flancer32\Lib\Data $ctx)
    {
        /* get working vars from context */
        $bind = $ctx->get(self::CTX_BIND);
        $vars = $ctx->get(self::CTX_VARS);
        /** @var \Praxigento\BonusHybrid\Api\Stats\Plain\Request $req */
        $req = $ctx->get(self::CTX_REQ);

        /* root customer id */
        $rootCustId = $req->getRootCustId();
        if (is_null($rootCustId)) {
            $user = $this->authenticator->getCurrentUserData();
            $rootCustId = $user->get(Cfg::E_CUSTOMER_A_ENTITY_ID);
        }
        $vars->set(self::VAR_ROOT_CUSTOMER_ID, $rootCustId);

        /* analyze period and compose sub-query params to get last calculation data ($calcId & $lastDate) */
        $period = $req->getPeriod();
        if (!$period) {
            $period = '2999'; // CAUTION: this code will be failed after 2999 year.
        }
        $period = $this->toolPeriod->getPeriodLastDate($period);
        $opts = new \Flancer32\Lib\Data([
            QGetLastCalc::OPT_DATE_END => $period,
            QGetLastCalc::OPT_CALC_TYPE_CODE => Cfg::CODE_TYPE_CALC_COMPRESS_FOR_PTC
        ]);
        $qres = $this->qPeriodCalc->exec($opts);
        $calcId = $qres->get(QGetLastCalc::A_CALC_REF);
        $onDate = $qres->get(QGetLastCalc::A_DS_END);

        /* save to context */
        $bind->set(self::BIND_CALC_REF, $calcId);
        $vars->set(self::VAR_ON_DATE, $onDate);
        $ctx->set(self::CTX_BIND, $bind);
        $ctx->set(self::CTX_VARS, $vars);
    }
}