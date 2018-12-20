<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Ui\DataProvider\Grid;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Ui\DataProvider\Grid\Downline\A\Repo\Query\GetCalcId as QGetId;
use Praxigento\BonusHybrid\Ui\DataProvider\Grid\Downline\A\Repo\Query\Grid as QGrid;
use Praxigento\BonusHybrid\Ui\DataProvider\Options\TreeType as OptionTreeType;

/**
 * Data provider for Bonus Downline grid (with initial parameters for select).
 */
class Downline
    extends \Praxigento\Core\App\Ui\DataProvider\Grid\Base
{

    /**
     * See "Praxigento_BonusHybrid:view/adminhtml/web/js/form/provider/bonus_downline"
     */
    const REQ_PERIOD = 'period';
    const REQ_TREE_TYPE = 'type';

    /** @var  \Praxigento\Core\App\Ui\DataProvider\Grid\Query\IBuilder */
    private $gridQueryBuilder;
    /** @var \Praxigento\Downline\Api\Helper\Tree */
    private $hlpTree;
    /** @var \Praxigento\BonusHybrid\Ui\DataProvider\Grid\Downline\A\Repo\Query\GetCalcId */
    private $qGetId;

    public function __construct(
        $name,
        \Magento\Framework\Api\Search\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\App\RequestInterface $request,
        \Praxigento\BonusHybrid\Ui\DataProvider\Grid\Downline\A\Repo\Query\Grid $gridQueryBuilder,
        \Praxigento\BonusHybrid\Ui\DataProvider\Grid\Downline\A\Repo\Query\GetCalcId $qGetId,
        \Praxigento\Downline\Api\Helper\Tree $hlpTree,
        $primaryFieldName = 'primaryFieldName',
        $requestFieldName = 'requestFieldName',
        array $meta = [],
        array $data = []
    ) {
        parent::__construct(
            $name,
            $searchCriteriaBuilder,
            $request,
            $gridQueryBuilder,
            $primaryFieldName,
            $requestFieldName,
            $meta,
            $data
        );
        $this->gridQueryBuilder = $gridQueryBuilder;
        $this->qGetId = $qGetId;
        $this->hlpTree = $hlpTree;
    }

    /**
     * Analyze HTTP request, load data from DB to compose bind array with parameters for grid query.
     *
     * @return array
     */
    private function getBind()
    {
        $params = $this->request->getParams();
        $period = $params[self::REQ_PERIOD] ?? '';
        $treeType = $params[self::REQ_TREE_TYPE] ?? '';
        $calcId = $this->getCalcId($period, $treeType);
        $bind = [
            QGrid::BND_CALC_ID => $calcId
        ];
        return $bind;
    }

    private function getCalcId($period, $treeType)
    {
        $dsBegin = $period . '01';
        $codeRegular = $codeForecast = '';
        if ($treeType == OptionTreeType::VAL_PLAIN) {
            $codeRegular = Cfg::CODE_TYPE_CALC_PV_WRITE_OFF;
            $codeForecast = Cfg::CODE_TYPE_CALC_FORECAST_PLAIN;
        } elseif ($treeType == OptionTreeType::VAL_COMPRESS) {
            $codeRegular = Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1;
            $codeForecast = Cfg::CODE_TYPE_CALC_FORECAST_PHASE1;
        }
        $query = $this->qGetId->build();
        $conn = $query->getConnection();
        $bind = [
            QGetId::BND_DS_BEGIN => $dsBegin,
            QGetId::BND_TYPE_CODE_REGULAR => $codeRegular,
            QGetId::BND_TYPE_CODE_FORECAST => $codeForecast
        ];
        $result = $conn->fetchOne($query, $bind);
        return $result;
    }

    public function getData()
    {
        /* get Web UI search criteria */
        $search = $this->getSearchCriteria();
        /* analyze request and compose bind variables array */
        $bind = $this->getBind();
        /* get build queries and fetch data for total count and items */
        $total = $this->gridQueryBuilder->getTotal($search, $bind);
        $items = $this->gridQueryBuilder->getItems($search, $bind);
        $result = [
            static::JSON_A_TOTAL_RECORDS => $total,
            static::JSON_A_ITEMS => $items
        ];
        return $result;
    }
}