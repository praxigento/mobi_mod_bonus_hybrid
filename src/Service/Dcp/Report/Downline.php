<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Dcp\Report;

use Praxigento\BonusBase\Repo\Query\Period\Calcs\GetLast\ByCalcTypeCode\Builder as QBLastCalc;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Builder as QBDownline;

class Downline
    extends \Praxigento\Core\App\Api\Web\Processor\WithQuery
    implements \Praxigento\BonusHybrid\Api\Dcp\Report\DownlineInterface
{
    /**
     * Types of the requested report.
     */
    const REPORT_TYPE_COMPLETE = 'complete';
    const REPORT_TYPE_COMPRESSED = 'compressed';

    /**
     * Name of the local context variables.
     */
    const VAR_CALC_ID = 'calcId';
    const VAR_CUST_ID = 'custId';
    const VAR_CUST_PATH = 'path';

    /** @var \Praxigento\Core\App\Api\Web\IAuthenticator */
    private $authenticator;
    /** @var \Praxigento\Core\Tool\IPeriod */
    private $hlpPeriod;
    /** @var \Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Builder */
    private $qbDownline;
    /** @var \Praxigento\BonusBase\Repo\Query\Period\Calcs\GetLast\ByCalcTypeCode\Builder */
    private $qbLastCalc;
    /** @var \Praxigento\Downline\Repo\Entity\Snap */
    private $repoSnap;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\Core\App\Api\Web\IAuthenticator $authenticator,
        \Praxigento\Core\Helper\Config $hlpCfg,
        \Praxigento\Core\Tool\IPeriod $hlpPeriod,
        \Praxigento\Downline\Repo\Entity\Snap $repoSnap,
        \Praxigento\BonusBase\Repo\Query\Period\Calcs\GetLast\ByCalcTypeCode\Builder $qbLastCalc,
        \Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Builder $qbDownline
    )
    {
        /* don't pass query builder to the parent - we have 4 builders in the operation, not one */
        parent::__construct($manObj, null, $hlpCfg);
        $this->authenticator = $authenticator;
        $this->hlpPeriod = $hlpPeriod;
        $this->repoSnap = $repoSnap;
        $this->qbDownline = $qbDownline;
        $this->qbLastCalc = $qbLastCalc;
    }

    protected function authorize(\Praxigento\Core\Data $ctx)
    {
        /* do nothing - in Production Mode current customer's ID is used as root customer ID */
    }

    protected function createQuerySelect(\Praxigento\Core\Data $ctx)
    {
        $query = $this->qbDownline->build();
        $ctx->set(self::CTX_QUERY, $query);
    }

    public function exec(\Praxigento\BonusHybrid\Api\Dcp\Report\Downline\Request $data)
    {
        $result = parent::process($data);
        return $result;
    }

    /**
     * Get complete calculation ID for given date by calculation type code.
     *
     * @param $calcTypeCode
     * @param $dateEnd
     * @return mixed
     */
    private function getCalcId($calcTypeCode, $dateEnd)
    {
        $query = $this->qbLastCalc->build();
        $bind = [
            QBLastCalc::BND_CODE => $calcTypeCode,
            QBLastCalc::BND_DATE => $dateEnd,
            QBLastCalc::BND_STATE => Cfg::CALC_STATE_COMPLETE
        ];

        /* fetch & parse data */
        $conn = $query->getConnection();
        $rs = $conn->fetchRow($query, $bind);
        $result = $rs[QBLastCalc::A_CALC_ID];
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
        $rootCustId = $vars->get(self::VAR_CUST_ID);
        $rootPath = $vars->get(self::VAR_CUST_PATH);
        $calcRef = $vars->get(self::VAR_CALC_ID);
        $path = $rootPath . $rootCustId . Cfg::DTPS . '%';

        /* bind values for query parameters */
        $bind->set(QBDownline::BND_CALC_ID, $calcRef);
        $bind->set(QBDownline::BND_PATH, $path);
        $bind->set(QBDownline::BND_CUST_ID, $rootCustId);
    }


    protected function prepareQueryParameters(\Praxigento\Core\Data $ctx)
    {
        /* get working vars from context */
        /** @var \Praxigento\Core\Data $vars */
        $vars = $ctx->get(self::CTX_VARS);
        /** @var \Praxigento\BonusHybrid\Api\Dcp\Report\Downline\Request $req */
        $req = $ctx->get(self::CTX_REQ);

        /* extract HTTP request parameters */
        $period = $req->getPeriod();
        $rootCustId = $req->getRootCustId();
        $reqType = $req->getType();

        /**
         * Define period.
         */
        if (!$period) {
            /* CAUTION: this code will be failed after 2999 year. Please, call to the author in this case. */
            $period = '2999';
        }
        $period = $this->hlpPeriod->getPeriodLastDate($period);

        /**
         * Define root customer & path to the root customer on the date.
         */
        $isLiveMode = !$this->hlpCfg->getApiAuthenticationEnabledDevMode();
        if (is_null($rootCustId) || $isLiveMode) {
            $rootCustId = $this->authenticator->getCurrentCustomerId();
        }
        $customerRoot = $this->repoSnap->getByCustomerIdOnDate($rootCustId, $period);
        $path = $customerRoot->getPath();

        /**
         * Define calculation ID to get downline data.
         */
        $calcTypeCode = null;
        $onDate = $this->hlpPeriod->getPeriodLastDate($period);
        $current = $this->hlpPeriod->getPeriodCurrent();
        if ($onDate >= $current) {
            /* use forecast downlines */
            $calcTypeCode = Cfg::CODE_TYPE_CALC_FORECAST_PLAIN;
            if ($reqType == self::REPORT_TYPE_COMPRESSED) {
                $calcTypeCode = Cfg::CODE_TYPE_CALC_FORECAST_PHASE1;
            }
        } else {
            /* use historical downlines */
            $calcTypeCode = Cfg::CODE_TYPE_CALC_PV_WRITE_OFF;
            if ($reqType == self::REPORT_TYPE_COMPRESSED) {
                $calcTypeCode = Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1;
            }
        }
        $calcId = $this->getCalcId($calcTypeCode, $onDate);

        /* save working variables into execution context */
        $vars->set(self::VAR_CUST_ID, $rootCustId);
        $vars->set(self::VAR_CUST_PATH, $path);
        $vars->set(self::VAR_CALC_ID, $calcId);
    }
}