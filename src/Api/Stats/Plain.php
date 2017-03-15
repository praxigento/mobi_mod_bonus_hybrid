<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Api\Stats;

class Plain
    implements \Praxigento\BonusHybrid\Api\Stats\PlainInterface
{
    const BIND_ROOT_CUSTOMER_ID = 'rootCustId';
    /** @var \Praxigento\Core\Api\IAuthenticator */
    protected $authenticator;
    /** @var  \Magento\Framework\ObjectManagerInterface */
    protected $manObj;

    public function __construct(
        \Praxigento\Core\Api\IAuthenticator $authenticator,
        \Magento\Framework\ObjectManagerInterface $manObj
    ) {
        $this->authenticator = $authenticator;
        $this->manObj = $manObj;
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
        /** @var \Praxigento\BonusHybrid\Api\Stats\Plain\Request $data */
//        $maxDepth = $data->getMaxDepth();
//        $onDate = $data->getOnDate();
//        $rootCustId = $data->getRootCustId();
//        if (is_null($rootCustId)) {
//            $user = $this->authenticator->getCurrentUserData();
//            $rootCustId = $user->get(Cfg::E_CUSTOMER_A_ENTITY_ID);
//        }
//        $result->set(self::BIND_ROOT_CUSTOMER_ID, $rootCustId);
//        if ($maxDepth) $result->set(self::BIND_MAX_DEPTH, $maxDepth);
//        if ($onDate) {
//            /* convert YYYYMM to YYYYMMDD */
//            $lastDate = $this->toolPeriod->getPeriodLastDate($onDate);
//            $result->set(self::BIND_ON_DATE, $lastDate);
//        }
        return $result;
    }

    public function exec(\Praxigento\BonusHybrid\Api\Stats\Plain\Request $data)
    {
        $result = new \Praxigento\BonusHybrid\Api\Stats\Plain\Response();
        if ($data->getRequestReturn()) {
            $result->setRequest($data);
        }
        /* parse request, prepare query and fetch data */
        $bind = $this->prepareQueryParameters($data);

        /** @var \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder $qbuild */
        $qbuild = $this->manObj->get(\Praxigento\Downline\Repo\Query\Snap\OnDate\Builder::class);
        $query = $qbuild->getSelectQuery();

//        $query = $this->getSelectQuery($bind);
//        $query = $this->populateQuery($query, $bind);
//        $rs = $this->performQuery($query, $bind);
//        $rsData = new \Flancer32\Lib\Data($rs);
//        $result->setData($rsData->get());
        return $result;
    }


}