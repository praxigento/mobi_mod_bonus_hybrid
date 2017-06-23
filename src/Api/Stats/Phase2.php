<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Stats;

use Praxigento\BonusHybrid\Api\Stats\Base\Query\GetLastCalc as QGetLastCalc;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Defaults as Def;

class Phase2
    extends \Praxigento\BonusHybrid\Api\Stats\Base
    implements \Praxigento\BonusHybrid\Api\Stats\Phase2Interface
{

    const BIND_CALC_REF = 'calcRef';

    /** @var \Praxigento\Downline\Repo\Entity\ICustomer */
    protected $repoCust;
    /** @var \Praxigento\BonusHybrid\Tool\IScheme */
    protected $toolScheme;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\BonusHybrid\Repo\Query\Stats\Phase2\Builder $qbldPhase2,
        \Praxigento\Core\Helper\Config $hlpCfg,
        \Praxigento\Core\Api\IAuthenticator $authenticator,
        \Praxigento\Core\Tool\IPeriod $toolPeriod,
        \Praxigento\Downline\Repo\Entity\ISnap $repoSnap,
        \Praxigento\BonusHybrid\Api\Stats\Base\Query\GetLastCalc $qPeriodCalc,
        \Praxigento\BonusHybrid\Tool\IScheme $toolScheme,
        \Praxigento\Downline\Repo\Entity\ICustomer $repoCust

    ) {
        parent::__construct(
            $manObj,
            $qbldPhase2,
            $hlpCfg,
            $authenticator,
            $toolPeriod,
            $repoSnap,
            $qPeriodCalc
        );
        $this->toolScheme = $toolScheme;
        $this->repoCust = $repoCust;
    }

    public function exec(\Praxigento\BonusHybrid\Api\Stats\Phase2\Request $data)
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
        $where = \Praxigento\BonusHybrid\Repo\Query\Stats\Phase2\Builder::AS_TREE . '.' .
            \Praxigento\BonusHybrid\Repo\Data\Entity\Retro\Downline\Compressed\Phase1::ATTR_PATH . ' LIKE :' . self::BIND_PATH;
        $path = $rootCustPath . $rootCustId . Cfg::DTPS . '%';
        $query->where($where);
        $bind->set(self::BIND_PATH, $path);

        /* filter data by max depth in downline tree */
        if (!is_null($maxDepth)) {
            /* depth started from 0, add +1 to start from root */
            $depth = $rootCustDepth + 1 + $maxDepth;
            $where = \Praxigento\BonusHybrid\Repo\Query\Stats\Phase2\Builder::AS_TREE . '.' .
                \Praxigento\BonusHybrid\Repo\Data\Entity\Retro\Downline\Compressed\Phase1::ATTR_DEPTH . ' < :' . self::BIND_MAX_DEPTH;
            $query->where($where);
            $bind->set(self::BIND_MAX_DEPTH, $depth);
        }

        /* filter data by calcId */
        $where = \Praxigento\BonusHybrid\Repo\Query\Stats\Phase2\Builder::AS_TREE . '.' .
            \Praxigento\BonusHybrid\Repo\Data\Entity\Retro\Downline\Compressed\Phase1::ATTR_CALC_ID . ' = :' . self::BIND_CALC_REF;
        $query->where($where);
        $bind->set(self::BIND_CALC_REF, $calcRef);
    }

    protected function prepareCalcRefData(\Flancer32\Lib\Data $ctx)
    {
        /* get working vars from context */
        /** @var \Flancer32\Lib\Data $vars */
        $vars = $ctx->get(self::CTX_VARS);

        /* query parameters */
        $rootCustId = $vars->get(self::VAR_CUST_ID);
        $dateEnd = $vars->get(self::VAR_ON_DATE);

        /* get customer scheme for root customer */
        $dwnl = $this->repoCust->getById($rootCustId);
        $scheme = $this->toolScheme->getSchemeByCustomer($dwnl);

        $calcCode = ($scheme == Def::SCHEMA_EU)
            ? Cfg::CODE_TYPE_CALC_COMPRESS_FOR_OI_EU
            : Cfg::CODE_TYPE_CALC_COMPRESS_FOR_OI_DEF;
        $opts = new \Flancer32\Lib\Data([
            QGetLastCalc::OPT_DATE_END => $dateEnd,
            QGetLastCalc::OPT_CALC_TYPE_CODE => $calcCode
        ]);
        $qres = $this->qPeriodCalc->exec($opts);
        $calcRef = $qres->get(QGetLastCalc::A_CALC_REF);
        $onDate = $qres->get(QGetLastCalc::A_DS_END);

        /* save working variables into execution context */
        $vars->set(self::VAR_CALC_REF, $calcRef);
        $vars->set(self::VAR_ON_DATE, $onDate);
    }


}