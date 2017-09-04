<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report;

use Praxigento\BonusBase\Repo\Query\Period\Calcs\GetLast\ByCalcTypeCode\Builder as QBLastCalc;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Actual\Downline\Plain as EActPlain;
use Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Retro\Plain\Builder as QBRetroPlain;

class Downline
    extends \Praxigento\BonusHybrid\Api\Stats\Base
    implements \Praxigento\BonusHybrid\Api\Dcp\Report\DownlineInterface
{
    const BIND_CALC_REF = QBRetroPlain::BIND_CALC_ID;
    const BIND_CUST_ID = 'customerId';
    const BIND_ON_DATE = QBRetroPlain::BIND_DATE;
    /**
     * Types of the queries.
     */
    const QUERY_TYPE_ACT_COMPRESS = 'actual&compressed';
    const QUERY_TYPE_ACT_PLAIN = 'actual&plain';
    const QUERY_TYPE_RETRO_COMPRESS = 'retro&compressed';
    const QUERY_TYPE_RETRO_PLAIN = 'retro&plain';
    /**
     * Types of the requested report.
     */
    const REPORT_TYPE_COMPLETE = 'complete';
    const REPORT_TYPE_COMPRESSED = 'compressed';

    /**
     * Name of the local context variables.
     */
    const VAR_ACTUAL_DATA_REQUESTED = 'isActualDataRequested';
    const VAR_CALC_ID = 'calcId';
    const VAR_QUERY_TYPE = 'queryType';
    const VAR_REPORT_TYPE = 'reportType';

    protected $qbActCompressed;
    /** @var \Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Actual\Plain\Builder */
    protected $qbActPlain;
    /** @var \Praxigento\BonusBase\Repo\Query\Period\Calcs\GetLast\ByCalcTypeCode\Builder */
    protected $qbLastCalc;
    protected $qbRetroCompressed;
    /** @var \Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Retro\Plain\Builder */
    protected $qbRetroPlain;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Actual\Plain\Builder $qbActPlain,
        \Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Retro\Plain\Builder $qbRetroPlain,
        \Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Retro\Compress\Builder $qbRetroCompress,
        \Praxigento\BonusBase\Repo\Query\Period\Calcs\GetLast\ByCalcTypeCode\Builder $qbLastCalc,
        \Praxigento\Core\Helper\Config $hlpCfg,
        \Praxigento\Core\Api\IAuthenticator $authenticator,
        \Praxigento\Core\Tool\IPeriod $toolPeriod,
        \Praxigento\Downline\Repo\Entity\Snap $repoSnap,
        \Praxigento\BonusHybrid\Api\Stats\Base\Query\GetLastCalc $qPeriodCalc
    )
    {
        /* don't pass query builder to the parent - we have 4 builders in the operation, not one */
        parent::__construct($manObj, null, $hlpCfg, $authenticator, $toolPeriod, $repoSnap, $qPeriodCalc);
        $this->qbActPlain = $qbActPlain;
        $this->qbActCompressed = $qbActPlain;
        $this->qbRetroPlain = $qbRetroPlain;
        $this->qbRetroCompressed = $qbRetroCompress;
        $this->qbLastCalc = $qbLastCalc;
    }

    protected function createQuerySelect(\Praxigento\Core\Data $ctx)
    {
        /* get working vars from context */
        /** @var \Praxigento\Core\Data $vars */
        $vars = $ctx->get(self::CTX_VARS);
        $reportType = $vars->get(self::VAR_REPORT_TYPE);
        $isActualDataRequested = $vars->get(self::VAR_ACTUAL_DATA_REQUESTED);

        /* put appropriate query builder into the context */
        $queryType = null;
        if ($isActualDataRequested) {
            if ($reportType == self::REPORT_TYPE_COMPRESSED) {
                /* the last compressed tree */
                $query = $this->qbActCompressed->build();
                $queryType = self::QUERY_TYPE_ACT_COMPRESS;
            } else {
                /* the last plain tree */
                $query = $this->qbActPlain->build();
                $queryType = self::QUERY_TYPE_ACT_PLAIN;
            }
        } else {
            if ($reportType == self::REPORT_TYPE_COMPRESSED) {
                /* retrospective compressed tree */
                $query = $this->qbRetroCompressed->build();
                $queryType = self::QUERY_TYPE_RETRO_COMPRESS;
            } else {
                /* retrospective plain tree */
                $query = $this->qbRetroPlain->build();
                $queryType = self::QUERY_TYPE_RETRO_PLAIN;
            }
        }
        $ctx->set(self::CTX_QUERY, $query);
        $vars->set(self::VAR_QUERY_TYPE, $queryType);
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
    protected function getCalcId($calcTypeCode, $dateEnd)
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
        /** @var \Magento\Framework\DB\Select $query */
        $query = $ctx->get(self::CTX_QUERY);

        /* get working vars */
        $reportType = $vars->get(self::VAR_REPORT_TYPE);
        $queryType = $vars->get(self::VAR_QUERY_TYPE);
        $rootCustId = $vars->get(self::VAR_CUST_ID);
        $rootPath = $vars->get(self::VAR_CUST_PATH);
        $onDate = $vars->get(self::VAR_ON_DATE);
        $calcRef = $vars->get(self::VAR_CALC_REF);
        $path = $rootPath . $rootCustId . Cfg::DTPS . '%';

        /* add more conditions to query and bind parameters */
        switch ($queryType) {
            case  self::QUERY_TYPE_ACT_COMPRESS:
                /* TODO */
                $bind->set(self::BIND_ON_DATE, $onDate);
                $bind->set(self::BIND_CALC_REF, $calcRef);
                break;
            case self::QUERY_TYPE_ACT_PLAIN:
                /* TODO: should we move WHERE clause into the Query Builder? */
                $where = '(' . $this->qbActPlain::AS_DWNL_PLAIN . '.' . EActPlain::ATTR_PATH;
                $where .= ' LIKE :' . self::BIND_PATH . ')';
                $where .= " OR ";
                $where .= '(' . $this->qbActPlain::AS_DWNL_PLAIN . '.' . EActPlain::ATTR_CUSTOMER_REF;
                $where .= '=:' . self::BIND_CUST_ID . ')';
                $query->where($where);
                $bind->set(self::BIND_PATH, $path);
                $bind->set(self::BIND_CUST_ID, $rootCustId);
                break;
            case  self::QUERY_TYPE_RETRO_COMPRESS:
                $calcRef = $this->getCalcId(Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1, $onDate);
                $bind->set(self::BIND_CALC_REF, $calcRef);
                break;
            case  self::QUERY_TYPE_RETRO_PLAIN:
                /* TODO: should we move WHERE clause into the Query Builder? */
                $where = '(' . QBRetroPlain::AS_DWNL_SNAP . '.' . QBRetroPlain::A_PATH;
                $where .= ' LIKE :' . self::BIND_PATH . ')';
                $where .= " OR ";
                $where .= '(' . QBRetroPlain::AS_DWNL_SNAP . '.' . QBRetroPlain::A_CUST_REF;
                $where .= '=:' . self::BIND_CUST_ID . ')';
                $query->where($where);
                $bind->set(self::BIND_PATH, $path);
                $bind->set(self::BIND_CUST_ID, $rootCustId);
                $bind->set(self::BIND_ON_DATE, $onDate);
                $bind->set(self::BIND_CALC_REF, $calcRef);
                break;
        }
    }

    protected function prepareCalcRefData(\Praxigento\Core\Data $ctx)
    {
        /**
         * TODO: we need to change this method - it is legacy code from \Praxigento\BonusHybrid\Api\Stats\Base
         */
        /* get working vars from context */
        /** @var \Praxigento\Core\Data $vars */
        $vars = $ctx->get(self::CTX_VARS);

        /* 'the last calc' query parameters */
        $dateEnd = $vars->get(self::VAR_ON_DATE);
        $calcTypeCode = Cfg::CODE_TYPE_CALC_PV_WRITE_OFF;

        $calcRef = $this->getCalcId($calcTypeCode, $dateEnd);

        /* save working variables into execution context */
        $vars->set(self::VAR_CALC_REF, $calcRef);
    }

    protected function prepareQueryParameters(\Praxigento\Core\Data $ctx)
    {
        parent::prepareQueryParameters($ctx);

        /* get working vars from context */
        /** @var \Praxigento\Core\Data $vars */
        $vars = $ctx->get(self::CTX_VARS);
        /** @var \Praxigento\BonusHybrid\Api\Dcp\Report\Downline\Request $req */
        $req = $ctx->get(self::CTX_REQ);

        /* extract WEB request parameters */
        $reqPeriod = $req->getPeriod();
        if ($reqPeriod) {
            $onDate = $this->toolPeriod->getPeriodLastDate($reqPeriod);
            $current = $this->toolPeriod->getPeriodCurrent();
            if ($onDate >= $current) {
                $isActualDataRequested = true;
            } else {
                $isActualDataRequested = false;
            }
        } else {
            $onDate = $this->toolPeriod->getPeriodCurrent();
            $isActualDataRequested = true;
        }

        $reqType = $req->getType();
        if ($reqType == self::REPORT_TYPE_COMPRESSED) {
            $type = self::REPORT_TYPE_COMPRESSED;
        } else {
            $type = self::REPORT_TYPE_COMPLETE;
        }

        /* save parsed values in context */
        $vars->set(self::VAR_ON_DATE, $onDate);
        $vars->set(self::VAR_ACTUAL_DATA_REQUESTED, $isActualDataRequested);
        $vars->set(self::VAR_REPORT_TYPE, $type);
    }

}