<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid\Api\Stats;

use Praxigento\BonusHybrid\Api\Stats\Base\Query\GetLastCalc as QGetLastCalc;
use Praxigento\BonusHybrid\Config as Cfg;

class Plain
    extends \Praxigento\BonusHybrid\Api\Stats\Base
    implements \Praxigento\BonusHybrid\Api\Stats\PlainInterface
{

    const BIND_CALC_REF = \Praxigento\BonusHybrid\Repo\Query\Stats\Plain\Builder::BIND_CALC_REF;
    const BIND_ON_DATE = \Praxigento\BonusHybrid\Repo\Query\Stats\Plain\Builder::BIND_ON_DATE;

    /** @var \Praxigento\BonusHybrid\Repo\Query\Stats\Plain\Builder */
    protected $qbldStatsPlain;


    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\BonusHybrid\Repo\Query\Stats\Plain\Builder $qbldPlain,
        \Praxigento\Core\Helper\Config $hlpCfg,
        \Praxigento\Core\Api\IAuthenticator $authenticator,
        \Praxigento\Core\Tool\IPeriod $toolPeriod,
        \Praxigento\Downline\Repo\Entity\Snap $repoSnap,
        \Praxigento\BonusHybrid\Api\Stats\Base\Query\GetLastCalc $qPeriodCalc

    ) {
        /* define query builder type */
        parent::__construct(
            $manObj,
            $qbldPlain,
            $hlpCfg,
            $authenticator,
            $toolPeriod,
            $repoSnap,
            $qPeriodCalc
        );
    }

    public function exec(\Praxigento\BonusHybrid\Api\Stats\Plain\Request $data)
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
        $onDate = $vars->get(self::VAR_ON_DATE);
        $calcRef = $vars->get(self::VAR_CALC_REF);
        $rootCustId = $vars->get(self::VAR_CUST_ID);
        $rootCustDepth = $vars->get(self::VAR_CUST_DEPTH);
        $rootCustPath = $vars->get(self::VAR_CUST_PATH);
        $maxDepth = $vars->get(self::VAR_MAX_DEPTH);

        /* filter snap data by date and by calculation ref (base query) */
        $bind->set(self::BIND_ON_DATE, $onDate);
        $bind->set(self::BIND_CALC_REF, $calcRef);

        /* filter snap data by root customer path */
        $where = \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder::AS_DWNL_SNAP . '.' .
            \Praxigento\Downline\Data\Entity\Snap::ATTR_PATH . ' LIKE :' . self::BIND_PATH;
        $path = $rootCustPath . $rootCustId . Cfg::DTPS . '%';
        $query->where($where);
        $bind->set(self::BIND_PATH, $path);

        /* filter snap data by max depth in downline tree */
        if (!is_null($maxDepth)) {
            /* depth started from 0, add +1 to start from root */
            $depth = $rootCustDepth + 1 + $maxDepth;
            $where = \Praxigento\Downline\Repo\Query\Snap\OnDate\Builder::AS_DWNL_SNAP . '.' .
                \Praxigento\Downline\Data\Entity\Snap::ATTR_DEPTH . ' < :' . self::BIND_MAX_DEPTH;
            $query->where($where);
            $bind->set(self::BIND_MAX_DEPTH, $depth);
        }
    }

    protected function prepareCalcRefData(\Flancer32\Lib\Data $ctx)
    {
        /* get working vars from context */
        /** @var \Flancer32\Lib\Data $vars */
        $vars = $ctx->get(self::CTX_VARS);

        /* query parameters */
        $dateEnd = $vars->get(self::VAR_ON_DATE);

        // get last calculation data ($calcId & $lastDate)
        $opts = new \Flancer32\Lib\Data([
            QGetLastCalc::OPT_DATE_END => $dateEnd,
            QGetLastCalc::OPT_CALC_TYPE_CODE => Cfg::CODE_TYPE_CALC_PV_WRITE_OFF
        ]);
        $qres = $this->qPeriodCalc->exec($opts);
        $calcRef = $qres->get(QGetLastCalc::A_CALC_REF);
        $onDate = $qres->get(QGetLastCalc::A_DS_END);

        /* save working variables into execution context */
        $vars->set(self::VAR_CALC_REF, $calcRef);
        $vars->set(self::VAR_ON_DATE, $onDate);
    }


}