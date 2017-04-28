<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc;

class Forecast
    extends \Praxigento\Core\Service\Base\Call
    implements \Praxigento\BonusHybrid\Service\Calc\IForecast
{
    /** @var  \Praxigento\Core\Tool\IPeriod */
    protected $toolPeriod;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\Core\Tool\IPeriod $toolPeriod
    ) {
        parent::__construct($logger, $manObj);
        $this->toolPeriod = $toolPeriod;

    }

    public function exec(\Praxigento\BonusHybrid\Service\Calc\Forecast\Request $req)
    {
        $result = new \Praxigento\BonusHybrid\Service\Calc\Forecast\Response();
        $this->_logger->info("'Forecast' calculation is started.");

        $this->_logMemoryUsage();
        $this->_logger->info("'Forecast' calculation is completed.");
        return $result;
    }
}