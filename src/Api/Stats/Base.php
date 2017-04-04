<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Api\Stats;

use Praxigento\BonusHybrid\Config as Cfg;

abstract class Base
    extends \Praxigento\Core\Api\Processor\WithQuery
{

    const BIND_MAX_DEPTH = 'maxDepth';
    const BIND_PATH = 'path';

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
    /** @var \Praxigento\BonusHybrid\Repo\Query\Stats\Phase1\Builder */
    protected $qbld;
    /** @var \Praxigento\Downline\Repo\Entity\ISnap */
    protected $repoSnap;
    /** @var \Praxigento\Core\Tool\IPeriod */
    protected $toolPeriod;

    public function __construct(
        \Praxigento\Core\Repo\Query\Def\Builder $qbld,
        \Praxigento\Core\Api\IAuthenticator $authenticator,
        \Praxigento\Core\Tool\IPeriod $toolPeriod,
        \Praxigento\Downline\Repo\Entity\ISnap $repoSnap,
        \Praxigento\BonusHybrid\Api\Stats\Base\Query\GetLastCalc $qPeriodCalc

    ) {
        parent::__construct($qbld);
        $this->authenticator = $authenticator;
        $this->toolPeriod = $toolPeriod;
        $this->repoSnap = $repoSnap;
        $this->qPeriodCalc = $qPeriodCalc;
    }

    /**
     * Select ID of the last complete calculation for given calculation type.
     *
     * @param \Flancer32\Lib\Data $ctx
     */
    protected abstract function prepareCalcRefData(\Flancer32\Lib\Data $ctx);

    protected function prepareQueryParameters(\Flancer32\Lib\Data $ctx)
    {
        /* get working vars from context */
        /** @var \Flancer32\Lib\Data $vars */
        $vars = $ctx->get(self::CTX_VARS);
        /** @var \Praxigento\BonusHybrid\Api\Stats\Base\Request $req */
        $req = $ctx->get(self::CTX_REQ);

        /* extract common request parameters */
        $rootCustId = $req->getRootCustId();
        $period = $req->getPeriod();
        $maxDepth = $req->getMaxDepth();

        /* define requested root customer */
        if (is_null($rootCustId)) {
            $user = $this->authenticator->getCurrentCustomerData();
            $rootCustId = $user->get(Cfg::E_CUSTOMER_A_ENTITY_ID);
        }

        /* define requested period */
        if (!$period) {
            /* CAUTION: this code will be failed after 2999 year. Please, call to the author in this case. */
            $period = '2999';
        }
        $period = $this->toolPeriod->getPeriodLastDate($period);

        // get root customer data on requested date from snaps
        $customerRoot = $this->repoSnap->getByCustomerIdOnDate($rootCustId, $period);
        $depth = $customerRoot->getDepth();
        $path = $customerRoot->getPath();

        /* save working variables into execution context */
        $vars->set(self::VAR_CUST_ID, $rootCustId);
        $vars->set(self::VAR_CUST_DEPTH, $depth);
        $vars->set(self::VAR_CUST_PATH, $path);
        $vars->set(self::VAR_MAX_DEPTH, $maxDepth);

        /* Select ID of the last complete calculation for given calculation type. */
        $this->prepareCalcRefData($ctx);

    }
}