<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc;

use Praxigento\BonusHybrid\Config as Cfg;

class Forecast
    extends \Praxigento\Core\Service\Base\Call
    implements \Praxigento\BonusHybrid\Service\Calc\IForecast
{
    protected $callBalanceGetTurnover;
    /** @var  \Praxigento\Core\Tool\IPeriod */
    protected $toolPeriod;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Praxigento\Core\Tool\IPeriod $toolPeriod,
        \Praxigento\Accounting\Service\Balance\Get\ITurnover $callBalanceGetTurnover
    ) {
        parent::__construct($logger, $manObj);
        $this->toolPeriod = $toolPeriod;
        $this->callBalanceGetTurnover = $callBalanceGetTurnover;
    }

    public function exec(\Praxigento\BonusHybrid\Service\Calc\Forecast\Request $req)
    {
        $result = new \Praxigento\BonusHybrid\Service\Calc\Forecast\Response();
        $this->_logger->info("'Forecast' calculation is started.");

        /* get calculation period (begin, end dates) */
        list($dateFrom, $dateTo) = $this->getPeriod();

        /* get PV turnover for period */
        $reqTurnover = new \Praxigento\Accounting\Service\Balance\Get\Turnover\Request ();
        $reqTurnover->assetTypeCode = Cfg::CODE_TYPE_ASSET_PV;
        $reqTurnover->dateFrom = $dateFrom;
        $reqTurnover->dateTo = $dateTo;
        $respTurnover = $this->callBalanceGetTurnover->exec($reqTurnover);

        /* extract only positive turnovers */
        $entries = $respTurnover->entries;
        $positiveTurnover = [];
        /** @var \Praxigento\Accounting\Service\Balance\Get\Turnover\Data\Entry $entry */
        foreach ($entries as $entry) {
            $turnover = $entry->turnover;
            $customerId = $entry->customerId;
            if ($turnover > Cfg::DEF_ZERO) {
                $positiveTurnover[$customerId] = $entry;
            }
        }

        /* perform calculation */

        /* replace actual data in repository */

        $this->_logMemoryUsage();
        $this->_logger->info("'Forecast' calculation is completed.");
        return $result;
    }

    /**
     * Return 2 dates (period being/end): first day of the month and yesterday.
     * @return array
     */
    protected function getPeriod()
    {
        $today = $this->toolPeriod->getPeriodCurrent();
        $yesterday = $this->toolPeriod->getPeriodPrev($today);
        $month = $this->toolPeriod->getPeriodCurrent(null, 0, \Praxigento\Core\Tool\IPeriod::TYPE_MONTH);
        $begin = $this->toolPeriod->getPeriodFirstDate($month);
        $end = $yesterday;

        /* TODO: remove it */
        $begin = '20170301';
        $end = '20170310';

        $result = [$begin, $end];
        return $result;
    }
}