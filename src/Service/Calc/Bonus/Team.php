<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus;

use Praxigento\BonusHybrid\Config as Cfg;

/**
 * Calculate Team Bonus.
 */
class Team
    implements IPersonal
{


    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent */
    private $procPeriodGet;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet
    )
    {
        $this->logger = $logger;
        $this->procPeriodGet = $procPeriodGet;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /**
         * perform processing
         */
        $ctx->set(self::CTX_OUT_SUCCESS, false);
        $this->logger->info("Personal bonus is started.");
        /* get dependent calculation data */
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $baseCalc */
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Period $depPeriod */
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $depCalc */
        list($baseCalc, $depPeriod, $depCalc) = $this->getCalcData();
        $baseCalcId = $baseCalc->getId();
        $depCalcId = $depCalc->getId();
        /* load downlines (compressed for period & current) */
        $dwnlCompress = $this->getBonusDwnl($baseCalcId);

        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, false);
        $this->logger->info("Personal bonus is completed.");
    }

    /**
     * Get data for dependent calculation.
     *
     * @return array [$periodData, $calcData]
     */
    private function getCalcData()
    {
        /* get period & calc data */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->procPeriodGet::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_VALUE_TV);
        $ctx->set($this->procPeriodGet::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $depCalcData */
        $baseCalcData = $ctx->get($this->procPeriodGet::CTX_OUT_BASE_CALC_DATA);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $depCalcData */
        $depCalcData = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_CALC_DATA);
        $result = [$baseCalcData, $depCalcData];
        return $result;
    }

}