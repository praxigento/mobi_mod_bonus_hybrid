<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Ui\DataProvider\Downline\Z;

use Praxigento\BonusHybrid\Ui\DataProvider\Options\TreeType as OptionTreeType;
use Praxigento\Core\Api\Helper\Period as HPeriod;

/**
 * Extract and validate query parameters (HTTP GET).
 */
class Input
{
    /**
     * See "Praxigento_BonusHybrid:view/adminhtml/web/js/form/provider/bonus_downline"
     */
    const REQ_PERIOD = 'period';
    const REQ_TREE_TYPE = 'type';

    /** @var \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var \Magento\Framework\App\RequestInterface */
    private $request;

    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Praxigento\Core\Api\Helper\Period $hlpPeriod
    ) {
        $this->request = $request;
        $this->hlpPeriod = $hlpPeriod;
    }

    /**
     * Extract and validate input parameters.
     *
     * @return array [$dsBegin, $treeType]
     */
    public function extractInput()
    {
        $params = $this->request->getParams();
        $period = $params[self::REQ_PERIOD] ?? '';
        if (empty($period)) {
            $period = $this->hlpPeriod->getPeriodCurrent(null, 0, HPeriod::TYPE_MONTH);
        } else {
            $period = $this->hlpPeriod->normalizePeriod($period, HPeriod::TYPE_MONTH);
        }
        $dsBegin = $this->hlpPeriod->getPeriodFirstDate($period);
        $treeType = $params[self::REQ_TREE_TYPE] ?? '';
        if ($treeType != OptionTreeType::VAL_PLAIN) {
            $treeType = OptionTreeType::VAL_COMPRESS; // 'compressed' by default
        }
        return [$dsBegin, $treeType];
    }
}