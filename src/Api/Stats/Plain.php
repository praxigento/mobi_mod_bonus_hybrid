<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Api\Stats;

use Praxigento\BonusHybrid\Api\Stats\Plain\Query\GetLastCalc as QGetLastCalc;
use Praxigento\BonusHybrid\Config as Cfg;

class Plain
    implements \Praxigento\BonusHybrid\Api\Stats\PlainInterface
{

    const BIND_CALC_REF = \Praxigento\BonusHybrid\Repo\Query\Stats\Pto\Builder::BIND_CALC_REF;
    const BIND_MAX_DEPTH = 'maxDepth';
    const BIND_ON_DATE = \Praxigento\BonusHybrid\Repo\Query\Stats\Pto\Builder::BIND_ON_DATE;
    const BIND_PATH = 'path';
    const BIND_ROOT_CUSTOMER_ID = 'rootCustId';
    /** @var \Praxigento\Core\Api\IAuthenticator */
    protected $authenticator;
    /** @var \Praxigento\BonusBase\Service\IPeriod */
    protected $callPeriod;
    /** @var  \Magento\Framework\ObjectManagerInterface */
    protected $manObj;
    /** @var  \Praxigento\BonusHybrid\Api\Stats\Plain\Query\GetLastCalc */
    protected $qPeriodCalc;
    /** @var \Praxigento\BonusBase\Repo\Query\Period\Calcs\Builder */
    protected $qbldPeriod;
    /** @var \Praxigento\BonusHybrid\Repo\Query\Stats\Pto\Builder */
    protected $qbldStatsPto;
    /** @var \Praxigento\Downline\Repo\Entity\ISnap */
    protected $repoSnap;
    /** @var \Praxigento\Core\Tool\IPeriod */
    protected $toolPeriod;

    public function __construct(
        \Praxigento\Core\Api\IAuthenticator $authenticator,
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\Core\Tool\IPeriod $toolPeriod,
        \Praxigento\BonusBase\Repo\Query\Period\Calcs\Builder $qbldPeriod,
        \Praxigento\BonusHybrid\Repo\Query\Stats\Pto\Builder $qbldStatsPto,
        \Praxigento\Downline\Repo\Entity\ISnap $repoSnap,
        \Praxigento\BonusBase\Service\IPeriod $callPeriod,
        \Praxigento\BonusHybrid\Api\Stats\Plain\Query\GetLastCalc $qPeriodCalc

    ) {
        $this->authenticator = $authenticator;
        $this->manObj = $manObj;
        $this->toolPeriod = $toolPeriod;
        $this->qbldPeriod = $qbldPeriod;
        $this->qbldStatsPto = $qbldStatsPto;
        $this->repoSnap = $repoSnap;
        $this->callPeriod = $callPeriod;
        $this->qPeriodCalc = $qPeriodCalc;
    }

    public function exec(\Praxigento\BonusHybrid\Api\Stats\Plain\Request $data)
    {
        $result = new \Praxigento\BonusHybrid\Api\Stats\Plain\Response();
        if ($data->getRequestReturn()) {
            $result->setRequest($data);
        }
        /* parse request, prepare query and fetch data */
        $bind = $this->prepareQueryParameters($data);
        $query = $this->getSelectQuery($data, $bind);
        $query = $this->populateQuery($query, $data, $bind);
        $rs = $this->performQuery($query, $bind);
        $rsData = new \Flancer32\Lib\Data($rs);
        $result->setData($rsData->get());
        return $result;
    }

    protected function getSelectQuery(
        \Flancer32\Lib\Data $data = null,
        \Flancer32\Lib\Data $bind = null
    ) {
        $query = $this->qbldStatsPto->getSelectQuery();
        return $query;
    }

    protected function performQuery(\Magento\Framework\DB\Select $query, \Flancer32\Lib\Data $bind = null)
    {
        $conn = $query->getConnection();
        $rs = $conn->fetchAll($query, (array)$bind->get());
        return $rs;
    }

    /**
     * Populate query and bound parameters according to request data (from $bind).
     *
     * @param \Magento\Framework\DB\Select $query SQL query
     * @param \Flancer32\Lib\Data|null $data API request data
     * @param \Flancer32\Lib\Data|null $bind query parameters
     * @return \Magento\Framework\DB\Select
     */
    protected function populateQuery(
        \Magento\Framework\DB\Select $query,
        \Flancer32\Lib\Data $data = null,
        \Flancer32\Lib\Data $bind = null
    ) {
        /** @var \Praxigento\BonusHybrid\Api\Stats\Plain\Request $data */
        /* collect important parameters (request & query) */
        $rootCustId = $data->getRootCustId();
        $onDate = $bind->get(self::BIND_ON_DATE);
        $maxDepth = $bind->get(self::BIND_MAX_DEPTH);

        /* filter data by root customer's path  */
        if (is_null($rootCustId)) {
            $user = $this->authenticator->getCurrentUserData();
            $rootCustId = $user->get(Cfg::E_CUSTOMER_A_ENTITY_ID);
        }
        // get root customer from snaps
        $customerRoot = $this->repoSnap->getByCustomerIdOnDate($rootCustId, $onDate);
        $idRoot = $customerRoot->getCustomerId();
        $pathRoot = $customerRoot->getPath();
        $where = \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder::AS_DWNL_SNAP . '.' .
            \Praxigento\Downline\Data\Entity\Snap::ATTR_PATH . ' LIKE :' . self::BIND_PATH;
        $bind->set(self::BIND_PATH, $pathRoot . $idRoot . Cfg::DTPS . '%');
        $query->where($where);


        /* filter data by max depth in downline tree */
        if (!is_null($maxDepth)) {
            /* depth started from 0, add +1 to start from root */
            $filterDepth = $customerRoot->getDepth() + 1 + $maxDepth;
            $where = \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder::AS_DWNL_SNAP . '.' .
                \Praxigento\Downline\Data\Entity\Snap::ATTR_DEPTH . ' < :' . self::BIND_MAX_DEPTH;
            $bind->set(self::BIND_MAX_DEPTH, $filterDepth);
            $query->where($where);
        }
        return $query;
    }

    /**
     * Analyze request data and collect expected parameters.
     *
     * @param \Flancer32\Lib\Data $data
     * @return \Flancer32\Lib\Data
     */
    protected function prepareQueryParameters(\Flancer32\Lib\Data $data)
    {
        $result = new \Flancer32\Lib\Data();
        /* extract request parameters */
        /** @var \Praxigento\BonusHybrid\Api\Stats\Plain\Request $data */
        $period = $data->getPeriod();

        /* analyze request parameters and compose query parameters */
        // get last calculation data ($calcId & $lastDate)
        if ($period) {
            $period = $this->toolPeriod->getPeriodLastDate($period);
        }
        $opts = new \Flancer32\Lib\Data([QGetLastCalc::OPT_DATE_END => $period]);
        $qres = $this->qPeriodCalc->exec($opts);
        $result->set(self::BIND_CALC_REF, $qres->get(QGetLastCalc::A_CALC_REF));
        $result->set(self::BIND_ON_DATE, $qres->get(QGetLastCalc::A_DS_END));

        return $result;
    }
}