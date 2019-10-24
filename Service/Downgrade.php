<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service;

use Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Request as AGetPeriodRequest;
use Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Response as AGetPeriodResponse;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Service\Downgrade\Request as ARequest;
use Praxigento\BonusHybrid\Service\Downgrade\Response as AResponse;

/**
 * Change status for unqualified customers and change downline tree.
 *
 * This is internal service (for this module only), so it has no own interface.
 */
class Downgrade
{
    /** @var \Praxigento\BonusHybrid\Service\Downgrade\A\Calc */
    private $aCalc;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $daoBonDwl;
    /** @var \Praxigento\BonusBase\Repo\Dao\Calculation */
    private $daoCalc;
    /** @var \Praxigento\BonusHybrid\Helper\Marker\Downgrade */
    private $hlpMarkDowngrade;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent */
    private $servPeriodGet;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\BonusBase\Repo\Dao\Calculation $daoCalc,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoBonDwl,
        \Praxigento\BonusHybrid\Helper\Marker\Downgrade $hlpMarkDowngrade,
        \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent $servPeriodGet,
        \Praxigento\BonusHybrid\Service\Downgrade\A\Calc $aCalc
    ) {
        $this->logger = $logger;
        $this->daoCalc = $daoCalc;
        $this->daoBonDwl = $daoBonDwl;
        $this->hlpMarkDowngrade = $hlpMarkDowngrade;
        $this->servPeriodGet = $servPeriodGet;
        $this->aCalc = $aCalc;
    }

    /**
     * @param ARequest $req
     * @return AResponse
     * @throws \Throwable
     */
    public function exec($req)
    {
        assert($req instanceof ARequest);
        $this->logger->info("Unqualified customers downgrade is started.");

        /**
         * perform processing
         */
        $this->hlpMarkDowngrade->setMark();
        /* get dependent calculation data */
        /**
         * @var \Praxigento\BonusBase\Repo\Data\Calculation $writeOffCalc
         * @var \Praxigento\BonusBase\Repo\Data\Calculation $processCalc
         * @var \Praxigento\BonusBase\Repo\Data\Period $processPeriod
         */
        list($writeOffCalc, $processCalc, $processPeriod) = $this->getCalcData();
        $writeOffCalcId = $writeOffCalc->getId();
        $treePlain = $this->daoBonDwl->getByCalcId($writeOffCalcId);
        $this->aCalc->exec($treePlain, $processPeriod);
        /* mark this calculation complete */
        $calcId = $processCalc->getId();
        $this->daoCalc->markComplete($calcId);
        $this->hlpMarkDowngrade->cleanMark();

        /* compose results  */
        $this->logger->info("Unqualified customers downgrade is completed.");
        $result = new AResponse();
        return $result;
    }

    /**
     * Get data for periods & calculations.
     *
     * @return array [$writeOffCalc, $writeOffPeriod, $processCalc]
     * @throws \Exception
     */
    private function getCalcData()
    {
        /**
         * Create Unqualified Process (Downgrade) calculation.
         */
        $req = new AGetPeriodRequest();
        $req->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $req->setDepCalcTypeCode(Cfg::CODE_TYPE_CALC_UNQUALIFIED_PROCESS);
        /** @var AGetPeriodResponse $resp */
        $resp = $this->servPeriodGet->exec($req);
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $writeOffCalc */
        $writeOffCalc = $resp->getBaseCalcData();
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $pwWriteOffCalc */
        $processCalc = $resp->getDepCalcData();
        /** @var \Praxigento\BonusBase\Repo\Data\Period $processPeriod */
        $processPeriod = $resp->getDepPeriodData();
        /**
         * Compose result.
         */
        $result = [$writeOffCalc, $processCalc, $processPeriod];
        return $result;
    }

}