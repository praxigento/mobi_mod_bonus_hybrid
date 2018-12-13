<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast;


use Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Request as AGetPeriodRequest;
use Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Response as AGetPeriodResponse;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Calc\Forecast\Unqualified\Request as ARequest;
use Praxigento\BonusHybrid\Service\Calc\Forecast\Unqualified\Response as AResponse;

/**
 * Unqualified customers forecast calc for the given period (not closed).
 */
class Unqualified
{
    /** @var \Praxigento\Core\Api\Helper\Period */
    private $hlpPeriod;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent */
    private $servPeriodGet;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Core\Api\Helper\Period $hlpPeriod,
        \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent $servPeriodGet
    ) {
        $this->logger = $logger;
        $this->hlpPeriod = $hlpPeriod;
        $this->servPeriodGet = $servPeriodGet;
    }

    /**
     * @param ARequest $request
     * @return AResponse
     * @throws \Exception
     */
    public function exec($request)
    {
        assert($request instanceof ARequest);
        /** define local working data */
        $period = $request->getPeriod();
        $this->logger->info("Forecast Unqualified calculation is started for period $period.");

        /** perform processing */
        $dsLast = $this->hlpPeriod->getPeriodLastDate($period);
        list($plainPeriodData, $plainCalcData, $compressCalcData) = $this->getCalcData($dsLast);

        $this->logger->info("Forecast Unqualified calculation is completed.");

        $result = new AResponse();
        $result->markSucceed();
        return $result;
    }

    /**
     * @param string $periodEnd YYYYMMDD
     * @return array [$plainPeriodData, $plainCalcData, $compressCalcData]
     * @throws \Exception
     */
    private function getCalcData($periodEnd)
    {
        /**
         * Get period & calc data.
         */
        $req = new AGetPeriodRequest();
        $req->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_FORECAST_PLAIN);
        $req->setDepCalcTypeCode(Cfg::CODE_TYPE_CALC_FORECAST_PHASE1);
        $req->setPeriodEnd($periodEnd);
        $req->setDepIgnoreComplete(true);
        /** @var AGetPeriodResponse $resp */
        $resp = $this->servPeriodGet->exec($req);
        /** @var \Praxigento\BonusBase\Repo\Data\Period $plainPeriodData */
        $plainPeriodData = $resp->getBasePeriodData();
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $plainCalcData */
        $plainCalcData = $resp->getBaseCalcData();
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $compressCalcData */
        $compressCalcData = $resp->getDepCalcData();
        /**
         * Compose result.
         */
        $result = [$plainPeriodData, $plainCalcData, $compressCalcData];
        return $result;
    }
}