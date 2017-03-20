<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Api\Stats;

use Praxigento\BonusHybrid\Api\Stats\Base\Query\GetLastCalc as QGetLastCalc;
use Praxigento\BonusHybrid\Config as Cfg;

class Plain
    extends \Praxigento\Core\Api\Processor\WithQuery
    implements \Praxigento\BonusHybrid\Api\Stats\PlainInterface
{

    const BIND_CALC_REF = \Praxigento\BonusHybrid\Repo\Query\Stats\Plain\Builder::BIND_CALC_REF;
    const BIND_MAX_DEPTH = 'maxDepth';
    const BIND_ON_DATE = \Praxigento\BonusHybrid\Repo\Query\Stats\Plain\Builder::BIND_ON_DATE;
    const BIND_PATH = 'path';
    const BIND_ROOT_CUSTOMER_ID = 'rootCustId';

    const VAR_CALC_REF = 'calc_ref';
    const VAR_CUST_DEPTH = 'depth';
    const VAR_CUST_ID = 'cust_id';
    const VAR_CUST_PATH = 'path';
    const VAR_MAX_DEPTH = 'max_depth';
    const VAR_ON_DATE = 'on_date';

    /** @var \Praxigento\Core\Api\IAuthenticator */
    protected $authenticator;
    /** @var  \Praxigento\BonusHybrid\Api\Stats\Base\Query\GetLastCalc */
    protected $qPeriodCalc;
    /** @var \Praxigento\BonusHybrid\Repo\Query\Stats\Plain\Builder */
    protected $qbldStatsPlain;
    /** @var \Praxigento\Downline\Repo\Entity\ISnap */
    protected $repoSnap;
    /** @var \Praxigento\Core\Tool\IPeriod */
    protected $toolPeriod;

    public function __construct(
        \Praxigento\Core\Api\IAuthenticator $authenticator,
        \Praxigento\Core\Tool\IPeriod $toolPeriod,
        \Praxigento\BonusHybrid\Repo\Query\Stats\Plain\Builder $qbldStatsPlain,
        \Praxigento\Downline\Repo\Entity\ISnap $repoSnap,
        \Praxigento\BonusHybrid\Api\Stats\Base\Query\GetLastCalc $qPeriodCalc

    ) {
        $this->authenticator = $authenticator;
        $this->toolPeriod = $toolPeriod;
        $this->qbldStatsPlain = $qbldStatsPlain;
        $this->repoSnap = $repoSnap;
        $this->qPeriodCalc = $qPeriodCalc;
    }

    public function exec(\Praxigento\BonusHybrid\Api\Stats\Plain\Request $data)
    {
        $result = parent::process($data);
        return $result;
    }

    protected function getSelectQuery(\Flancer32\Lib\Data $ctx)
    {
        $query = $this->qbldStatsPlain->getSelectQuery();
        $ctx->set(self::CTX_QUERY, $query);
    }

    protected function populateQuery(\Flancer32\Lib\Data $ctx)
    {
        /* get working vars from context */
        /** @var \Flancer32\Lib\Data $bind */
        $bind = $ctx->get(self::CTX_BIND);
        /** @var \Flancer32\Lib\Data $vars */
        $vars = $ctx->get(self::CTX_VARS);
        /** @var \Magento\Framework\DB\Select $query */
        $query = $ctx->get(self::CTX_QUERY);
        
        /* collect important parameters (request & query) */
        $onDate = $vars->get(self::VAR_ON_DATE);
        $calcRef = $vars->get(self::VAR_CALC_REF);
        $rootCustId = $vars->get(self::VAR_CUST_ID);
        $rootCustDepth = $vars->get(self::VAR_CUST_DEPTH);
        $rootCustPath = $vars->get(self::VAR_CUST_PATH);
        $maxDepth = $vars->get(self::VAR_MAX_DEPTH);

        /* filter snap data by date */
        $bind->set(self::BIND_ON_DATE, $onDate);

        /* filter stats data by calculation ref */
        $bind->set(self::BIND_CALC_REF, $calcRef);

        /* filter snap data by root customer path */
        $where = \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder::AS_DWNL_SNAP . '.' .
            \Praxigento\Downline\Data\Entity\Snap::ATTR_PATH . ' LIKE :' . self::BIND_PATH;
        $path = $rootCustPath . $rootCustId . Cfg::DTPS . '%';
        $bind->set(self::BIND_PATH, $path);
        $query->where($where);

        /* filter sanp data by max depth in downline tree */
        if (!is_null($maxDepth)) {
            /* depth started from 0, add +1 to start from root */
            $depth = $rootCustDepth + 1 + $maxDepth;
            $where = \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder::AS_DWNL_SNAP . '.' .
                \Praxigento\Downline\Data\Entity\Snap::ATTR_DEPTH . ' < :' . self::BIND_MAX_DEPTH;
            $bind->set(self::BIND_MAX_DEPTH, $depth);
            $query->where($where);
        }
        return $query;
    }

    protected function prepareQueryParameters(\Flancer32\Lib\Data $ctx)
    {
        /* get working vars from context */
        /** @var \Flancer32\Lib\Data $vars */
        $vars = $ctx->get(self::CTX_VARS);
        /** @var \Praxigento\BonusHybrid\Api\Stats\Plain\Request $req */
        $req = $ctx->get(self::CTX_REQ);

        /* extract request parameters */
        $rootCustId = $req->getRootCustId();
        $period = $req->getPeriod();
        $maxDepth = $req->getMaxDepth();

        /* define requested root customer */
        if (is_null($rootCustId)) {
            $user = $this->authenticator->getCurrentUserData();
            $rootCustId = $user->get(Cfg::E_CUSTOMER_A_ENTITY_ID);
        }

        /* define requested period */
        if ($period) {
            $period = $this->toolPeriod->getPeriodLastDate($period);
        } else {
            /* CAUTION: this code will be failed after 2999 year. Please, call to the author in this case. */
            $period = '29991231';
        }

        // get root customer data on requested date from snaps
        $customerRoot = $this->repoSnap->getByCustomerIdOnDate($rootCustId, $period);
        $depth = $customerRoot->getDepth();
        $path = $customerRoot->getPath();

        // get last calculation data ($calcId & $lastDate)
        $opts = new \Flancer32\Lib\Data([
            QGetLastCalc::OPT_DATE_END => $period,
            QGetLastCalc::OPT_CALC_TYPE_CODE => Cfg::CODE_TYPE_CALC_PV_WRITE_OFF
        ]);
        $qres = $this->qPeriodCalc->exec($opts);
        $calcRef = $qres->get(QGetLastCalc::A_CALC_REF);
        $onDate = $qres->get(QGetLastCalc::A_DS_END);

        /* save working variables into execution context */
        $vars->set(self::VAR_CUST_ID, $rootCustId);
        $vars->set(self::VAR_CUST_DEPTH, $depth);
        $vars->set(self::VAR_CUST_PATH, $path);
        $vars->set(self::VAR_MAX_DEPTH, $maxDepth);
        $vars->set(self::VAR_CALC_REF, $calcRef);
        $vars->set(self::VAR_ON_DATE, $onDate);
    }
}