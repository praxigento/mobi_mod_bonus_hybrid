<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report;

class Downline
    extends \Praxigento\BonusHybrid\Api\Stats\Base
    implements \Praxigento\BonusHybrid\Api\Dcp\Report\DownlineInterface
{
    const BIND_ON_DATE = \Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Retro\Plain\Builder::BIND_DATE;
    /**
     * Types of the requested report.
     */
    const TYPE_COMPLETE = 'complete';
    const TYPE_COMPRESSED = 'compressed';

    const VAR_ACTUAL_DATA_REQUESTED = 'isActualDataRequested';
    const VAR_TYPE = 'type';

    protected $qbldActCompressed;
    /** @var \Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Actual\Plain\Builder */
    protected $qbldActPlain;
    protected $qbldRetroCompressed;
    /** @var \Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Retro\Plain\Builder */
    protected $qbldRetroPlain;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Actual\Plain\Builder $qbldActPlain,
        \Praxigento\BonusHybrid\Repo\Query\Dcp\Report\Downline\Retro\Plain\Builder $qbldRetroPlain,
        \Praxigento\Core\Helper\Config $hlpCfg,
        \Praxigento\Core\Api\IAuthenticator $authenticator,
        \Praxigento\Core\Tool\IPeriod $toolPeriod,
        \Praxigento\Downline\Repo\Entity\ISnap $repoSnap,
        \Praxigento\BonusHybrid\Api\Stats\Base\Query\GetLastCalc $qPeriodCalc
    ) {
        /* don't pass query builder to the parent - we have 4 builders in the operation, not one */
        parent::__construct($manObj, null, $hlpCfg, $authenticator, $toolPeriod, $repoSnap, $qPeriodCalc);
        $this->qbldActPlain = $qbldActPlain;
        $this->qbldActCompressed = $qbldActPlain;
        $this->qbldRetroPlain = $qbldRetroPlain;
        $this->qbldRetroCompressed = $qbldActPlain;
    }

    protected function createQuerySelect(\Flancer32\Lib\Data $ctx)
    {
        /* get working vars from context */
        /** @var \Flancer32\Lib\Data $vars */
        $vars = $ctx->get(self::CTX_VARS);
        $type = $vars->get(self::VAR_TYPE);
        $isActualDataRequested = $vars->get(self::VAR_ACTUAL_DATA_REQUESTED);

        /* put appropriate query builder into the context */
        if ($isActualDataRequested) {
            if ($type == self::TYPE_COMPRESSED) {
                /* the last compressed tree */
                $query = $this->qbldActCompressed->build();
            } else {
                /* the last plain tree */
                $query = $this->qbldActPlain->build();
            }
        } else {
            if ($type == self::TYPE_COMPRESSED) {
                /* retrospective compressed tree */
                $query = $this->qbldRetroCompressed->build();
            } else {
                /* retrospective plain tree */
                $query = $this->qbldRetroPlain->build();
            }
        }
        $ctx->set(self::CTX_QUERY, $query);
    }

    public function exec(\Praxigento\BonusHybrid\Api\Dcp\Report\Downline\Request $data)
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

        $isActualDataRequested = $vars->get(self::VAR_ACTUAL_DATA_REQUESTED);

        if (!$isActualDataRequested) {
            $onDate = $vars->get(self::VAR_ON_DATE);
            $bind->set(self::BIND_ON_DATE, $onDate);
        }

    }

    protected function prepareCalcRefData(\Flancer32\Lib\Data $ctx)
    {
        // TODO: Implement prepareCalcRefData() method.
    }

    protected function prepareQueryParameters(\Flancer32\Lib\Data $ctx)
    {
        parent::prepareQueryParameters($ctx);

        /* get working vars from context */
        /** @var \Flancer32\Lib\Data $vars */
        $vars = $ctx->get(self::CTX_VARS);
        /** @var \Praxigento\BonusHybrid\Api\Dcp\Report\Downline\Request $req */
        $req = $ctx->get(self::CTX_REQ);

        /* extract this request parameters */
        $reqPeriod = $req->getPeriod();
        if ($reqPeriod) {
            $onDate = $this->toolPeriod->getPeriodLastDate($reqPeriod);
            $isActualDataRequested = false;
        } else {
            $onDate = $this->toolPeriod->getPeriodCurrent();
            $isActualDataRequested = true;
        }

        $reqType = $req->getType();
        if ($reqType == self::TYPE_COMPRESSED) {
            $type = self::TYPE_COMPRESSED;
        } else {
            $type = self::TYPE_COMPLETE;
        }

        /* save parsed values in context */
        $vars->set(self::VAR_ON_DATE, $onDate);
        $vars->set(self::VAR_ACTUAL_DATA_REQUESTED, $isActualDataRequested);
        $vars->set(self::VAR_TYPE, $type);
    }

}