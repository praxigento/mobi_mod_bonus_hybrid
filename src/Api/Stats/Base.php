<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Stats;

use Praxigento\Downline\Config as Cfg;

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
    /** @var \Praxigento\Core\Repo\Query\IBuilder */
    protected $qbld;
    /** @var \Praxigento\Downline\Repo\Entity\Snap */
    protected $repoSnap;
    /** @var \Praxigento\Core\Tool\IPeriod */
    protected $toolPeriod;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\Core\Repo\Query\IBuilder $qbld = null,
        \Praxigento\Core\Helper\Config $hlpCfg,
        \Praxigento\Core\Api\IAuthenticator $authenticator,
        \Praxigento\Core\Tool\IPeriod $toolPeriod,
        \Praxigento\Downline\Repo\Entity\Snap $repoSnap,
        \Praxigento\BonusHybrid\Api\Stats\Base\Query\GetLastCalc $qPeriodCalc

    ) {
        parent::__construct($manObj, $qbld, $hlpCfg);
        $this->authenticator = $authenticator;
        $this->toolPeriod = $toolPeriod;
        $this->repoSnap = $repoSnap;
        $this->qPeriodCalc = $qPeriodCalc;
    }

    protected function authorize(\Flancer32\Lib\Data $ctx)
    {
        /* get working vars from context */
        $vars = $ctx->get(self::CTX_VARS);
        $rootCustId = $vars->get(self::VAR_CUST_ID);
        $rootCustPath = $vars->get(self::VAR_CUST_PATH);

        /* only currently logged in customer can get account statement */
        $currCustData = $this->authenticator->getCurrentCustomerData();
        $currCustId = $this->authenticator->getCurrentCustomerId();
        /** @var \Praxigento\Downline\Repo\Entity\Data\Customer $currDwnlData */
        $currDwnlData = $currCustData->get(\Praxigento\Downline\Infra\Api\Authenticator::A_DWNL_DATA);
        $currCustPath = $currDwnlData->getPath() . $currDwnlData->getCustomerId() . Cfg::DTPS;

        /* perform action */
        $isTheSameCusts = ($rootCustId == $currCustId);
        $isTheParent = !is_null($currCustId) && (substr($rootCustPath, 0, strlen($currCustPath)) == $currCustPath);
        $isInDevMode = $this->authenticator->isEnabledDevMode();
        if (($isTheSameCusts) || ($isTheParent) || $isInDevMode) {
            // do nothing
        } else {
            $msg = __('You are not authorized to perform this operation.');
            throw new \Magento\Framework\Exception\AuthorizationException($msg);
        }
    }

    /**
     * Select ID of the last complete calculation for given calculation type.
     *
     * @param \Flancer32\Lib\Data $ctx
     */
    protected abstract function prepareCalcRefData(\Flancer32\Lib\Data $ctx);

    /**
     * Extract parameters for base request (period, root customer, etc.).
     *
     * @inheritdoc
     */
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
        $isLiveMode = !$this->hlpCfg->getApiAuthenticationEnabledDevMode();
        if (is_null($rootCustId) || $isLiveMode) {
            $rootCustId = $this->authenticator->getCurrentCustomerId();
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
        $vars->set(self::VAR_ON_DATE, $period);

        /* Select ID of the last complete calculation for given calculation type. */
        $this->prepareCalcRefData($ctx);

    }
}