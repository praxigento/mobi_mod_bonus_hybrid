<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Stats;

use Praxigento\BonusHybrid\Api\Stats\Base\Query\GetLastCalc as QGetLastCalc;
use Praxigento\BonusHybrid\Config as Cfg;

class Phase1
    extends \Praxigento\BonusHybrid\Api\Stats\Base
    implements \Praxigento\BonusHybrid\Api\Stats\Phase1Interface
{

    const BIND_CALC_REF = 'calcRef';

    const VAR_ROOT_CUSTOMER_ID = 'root_cust_id';


    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\BonusHybrid\Repo\Query\Stats\Phase1\Builder $qbldPhase1,
        \Praxigento\Core\Helper\Config $hlpCfg,
        \Praxigento\Core\Api\IAuthenticator $authenticator,
        \Praxigento\Core\Tool\IPeriod $toolPeriod,
        \Praxigento\Downline\Repo\Entity\ISnap $repoSnap,
        \Praxigento\BonusHybrid\Api\Stats\Base\Query\GetLastCalc $qPeriodCalc


    ) {
        /* define query builder type */
        parent::__construct(
            $manObj,
            $qbldPhase1,
            $hlpCfg,
            $authenticator,
            $toolPeriod,
            $repoSnap,
            $qPeriodCalc
        );
    }

    public function exec(\Praxigento\BonusHybrid\Api\Stats\Phase1\Request $data)
    {
        $result = parent::process($data);
        return $result;
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

        /* collect important parameters to bind to query */
        $calcRef = $vars->get(self::VAR_CALC_REF);
        $rootCustId = $vars->get(self::VAR_CUST_ID);
        $rootCustDepth = $vars->get(self::VAR_CUST_DEPTH);
        $rootCustPath = $vars->get(self::VAR_CUST_PATH);
        $maxDepth = $vars->get(self::VAR_MAX_DEPTH);

        /* filter data by root customer's path */
        $where = \Praxigento\BonusHybrid\Repo\Query\Stats\Phase1\Builder::AS_TREE . '.' .
            \Praxigento\BonusHybrid\Entity\Compression\Ptc::ATTR_PATH . ' LIKE :' . self::BIND_PATH;
        $path = $rootCustPath . $rootCustId . Cfg::DTPS . '%';
        $query->where($where);
        $bind->set(self::BIND_PATH, $path);

        /* filter data by max depth in downline tree */
        if (!is_null($maxDepth)) {
            /* depth started from 0, add +1 to start from root */
            $depth = $rootCustDepth + 1 + $maxDepth;
            $where = \Praxigento\BonusHybrid\Repo\Query\Stats\Phase1\Builder::AS_TREE . '.' .
                \Praxigento\BonusHybrid\Entity\Compression\Ptc::ATTR_DEPTH . ' < :' . self::BIND_MAX_DEPTH;
            $query->where($where);
            $bind->set(self::BIND_MAX_DEPTH, $depth);
        }

        /* filter data by calcId */
        $where = \Praxigento\BonusHybrid\Repo\Query\Stats\Phase1\Builder::AS_TREE . '.' .
            \Praxigento\BonusHybrid\Entity\Compression\Ptc::ATTR_CALC_ID . ' = :' . self::BIND_CALC_REF;
        $query->where($where);
        $bind->set(self::BIND_CALC_REF, $calcRef);
    }

    protected function prepareCalcRefData(\Flancer32\Lib\Data $ctx)
    {
        /* get working vars from context */
        /** @var \Flancer32\Lib\Data $vars */
        $vars = $ctx->get(self::CTX_VARS);

        /* query parameters */
        $dateEnd = $vars->get(self::VAR_ON_DATE);
        $opts = new \Flancer32\Lib\Data([
            QGetLastCalc::OPT_DATE_END => $dateEnd,
            QGetLastCalc::OPT_CALC_TYPE_CODE => Cfg::CODE_TYPE_CALC_COMPRESS_FOR_PTC
        ]);
        $qres = $this->qPeriodCalc->exec($opts);
        $calcRef = $qres->get(QGetLastCalc::A_CALC_REF);
        $onDate = $qres->get(QGetLastCalc::A_DS_END);

        /* save working variables into execution context */
        $vars->set(self::VAR_CALC_REF, $calcRef);
        $vars->set(self::VAR_ON_DATE, $onDate);
    }

}