<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report;

class Downline
    extends \Praxigento\BonusHybrid\Api\Stats\Base
    implements \Praxigento\BonusHybrid\Api\Dcp\Report\DownlineInterface
{
    /**
     * Types of the requested report.
     */
    const TYPE_COMPLETE = 'complete';
    const TYPE_COMPRESSED = 'compressed';

    const VAR_TYPE = 'type';

    /** @var \Praxigento\BonusHybrid\Repo\Query\Cache\Dwnl\Plain\Get\Builder */
    protected $qbldComplete;

    protected $qbldCompressed;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\BonusHybrid\Repo\Query\Cache\Dwnl\Plain\Get\Builder $qbldPlain,
        \Praxigento\Core\Helper\Config $hlpCfg,
        \Praxigento\Core\Api\IAuthenticator $authenticator,
        \Praxigento\Core\Tool\IPeriod $toolPeriod,
        \Praxigento\Downline\Repo\Entity\ISnap $repoSnap,
        \Praxigento\BonusHybrid\Api\Stats\Base\Query\GetLastCalc $qPeriodCalc
    ) {
        parent::__construct($manObj, null, $hlpCfg, $authenticator, $toolPeriod, $repoSnap, $qPeriodCalc);
        $this->qbld = $qbldPlain;
        $this->qbldComplete = $qbldPlain;
    }

    protected function createQuerySelect(\Flancer32\Lib\Data $ctx)
    {
        /* get working vars from context */
        /** @var \Flancer32\Lib\Data $vars */
        $vars = $ctx->get(self::CTX_VARS);
        $type = $vars->get(self::VAR_TYPE);
        $query = $this->qbldComplete->getSelectQuery();
        if ($type == self::TYPE_COMPRESSED) {
            $query = $this->qbldCompressed->getSelectQuery();
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
        // TODO: Implement populateQuery() method.
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
        } else {
            $onDate = $this->toolPeriod->getPeriodCurrent();
        }

        $reqType = $req->getType();
        if ($reqType == self::TYPE_COMPRESSED) {
            $type = self::TYPE_COMPRESSED;
        } else {
            $type = self::TYPE_COMPLETE;
        }

        /* save parsed values in context */
        $vars->set(self::VAR_ON_DATE, $onDate);
        $vars->set(self::VAR_TYPE, $type);
    }

}