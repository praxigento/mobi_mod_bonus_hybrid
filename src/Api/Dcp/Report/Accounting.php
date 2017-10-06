<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Accounting\Trans\Builder as QBAccTrans;
use Praxigento\Core\Tool\IPeriod as HPeriod;

class Accounting
    extends \Praxigento\Core\Api\Processor\WithQuery
    implements \Praxigento\BonusHybrid\Api\Dcp\Report\AccountingInterface
{
    /**
     * Name of the local context variables.
     */
    const VAR_CUST_ID = 'custId';
    /** @deprecated remove it if not used */
    const VAR_CUST_PATH = 'path';
    const VAR_DATE_FROM = 'dateTo';
    const VAR_DATE_TO = 'dateFrom';

    /** @var \Praxigento\Core\Api\IAuthenticator */
    private $authenticator;
    /** @var \Praxigento\Core\Tool\IPeriod */
    private $hlpPeriod;
    /** @var \Praxigento\Downline\Repo\Entity\Snap */
    private $repoSnap;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\Core\Tool\IPeriod $hlpPeriod,
        \Praxigento\Core\Helper\Config $hlpCfg,
        \Praxigento\Core\Api\IAuthenticator $authenticator,
        \Praxigento\Downline\Repo\Entity\Snap $repoSnap,
        \Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Accounting\Trans\Builder $qbld
    )
    {
        parent::__construct($manObj, $qbld, $hlpCfg);
        $this->authenticator = $authenticator;
        $this->hlpPeriod = $hlpPeriod;
        $this->repoSnap = $repoSnap;
    }

    protected function authorize(\Praxigento\Core\Data $ctx)
    {
        /* do nothing - in Production Mode current customer's ID is used as root customer ID */
    }

    public function exec(\Praxigento\BonusHybrid\Api\Dcp\Report\Accounting\Request $data)
    {
        $result = parent::process($data);
        return $result;
    }

    protected function populateQuery(\Praxigento\Core\Data $ctx)
    {
        /* get working vars from context */
        /** @var \Praxigento\Core\Data $bind */
        $bind = $ctx->get(self::CTX_BIND);
        /** @var \Praxigento\Core\Data $vars */
        $vars = $ctx->get(self::CTX_VARS);

        /* get working vars */
        $custId = $vars->get(self::VAR_CUST_ID);
        $rootPath = $vars->get(self::VAR_CUST_PATH);
        $dateFrom = $vars->get(self::VAR_DATE_FROM);
        $dateTo = $vars->get(self::VAR_DATE_TO);
        $path = $rootPath . $custId . Cfg::DTPS . '%';

        /* bind values for query parameters */
        $bind->set(QBAccTrans::BND_CUST_ID, $custId);
        $bind->set(QBAccTrans::BND_DATE_FROM, $dateFrom);
        $bind->set(QBAccTrans::BND_DATE_TO, $dateTo);

    }

    protected function prepareQueryParameters(\Praxigento\Core\Data $ctx)
    {
        /* get working vars from context */
        /** @var \Praxigento\Core\Data $vars */
        $vars = $ctx->get(self::CTX_VARS);
        /** @var \Praxigento\BonusHybrid\Api\Dcp\Report\Accounting\Request $req */
        $req = $ctx->get(self::CTX_REQ);

        /* extract HTTP request parameters */
        $period = $req->getPeriod();
        $custId = $req->getCustomerId();


        /**
         * Define period.
         */
        if (!$period) {
            /* CAUTION: this code will be failed after 2999 year. Please, call to the author in this case. */
            $period = '2999';
        }
        $dateFrom = $this->hlpPeriod->getTimestampFrom($period, HPeriod::TYPE_MONTH);
        $dateTo = $this->hlpPeriod->getTimestampTo($period, HPeriod::TYPE_MONTH);

        /**
         * Define root customer & path to the root customer on the date.
         */
        $isLiveMode = !$this->hlpCfg->getApiAuthenticationEnabledDevMode();
        if (is_null($custId) || $isLiveMode) {
            $custId = $this->authenticator->getCurrentCustomerId();
        }
        $customerRoot = $this->repoSnap->getByCustomerIdOnDate($custId, $period);
        $path = $customerRoot->getPath();


        /* save working variables into execution context */
        $vars->set(self::VAR_CUST_ID, $custId);
        $vars->set(self::VAR_CUST_PATH, $path);
        $vars->set(self::VAR_DATE_FROM, $dateFrom);
        $vars->set(self::VAR_DATE_TO, $dateTo);
    }

}