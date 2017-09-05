<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EDwnlBon;

/**
 * Calculate Team Bonus.
 */
class Team
    implements ITeam
{

    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent */
    private $procPeriodGet;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoDwnlBon;
    /** @var \Praxigento\BonusBase\Repo\Entity\Level */
    private $repoLevel;
    /** @var \Praxigento\BonusBase\Repo\Entity\Type\Calc */
    private $repoCalcType;
    /** @var Team\Calc */
    private $subCalc;
    /** @var Team\PrepareTrans */
    private $subPrepareTrans;
    /** @var  \Praxigento\Core\Tool\IPeriod */
    private $hlpPeriod;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\Core\Tool\IPeriod $hlpPeriod,
        \Praxigento\BonusBase\Repo\Entity\Level $repoLevel,
        \Praxigento\BonusBase\Repo\Entity\Type\Calc $repoCalcType,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoDwnlBon,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Team\Calc $subCalc,
        \Praxigento\BonusHybrid\Service\Calc\Bonus\Team\PrepareTrans $subPrepareTrans
    )
    {
        $this->logger = $logger;
        $this->hlpPeriod = $hlpPeriod;
        $this->repoLevel = $repoLevel;
        $this->repoCalcType = $repoCalcType;
        $this->repoDwnlBon = $repoDwnlBon;
        $this->procPeriodGet = $procPeriodGet;
        $this->subCalc = $subCalc;
        $this->subPrepareTrans = $subPrepareTrans;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /**
         * perform processing
         */
        $ctx->set(self::CTX_OUT_SUCCESS, false);
        $this->logger->info("Team bonus is started.");
        /**
         * get dependent calculation data
         *
         * @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $compressCalc
         * @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $tvCalc
         * @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $teamPeriod
         * @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $teamCalc
         */
        list($compressCalc, $tvCalc, $teamPeriod, $teamCalc) = $this->getCalcData();
        $compressCalcId = $compressCalc->getId();
        $tvCalcId = $tvCalc->getId();
        $teamCalcId = $teamCalc->getId();
        /* load downlines (compressed for period & current) */
        $dwnlCompress = $this->getBonusDwnl($compressCalcId);
        /* load levels & percents for personal & team bonuses */
        $levelsPers = $this->getLevelsByType(Cfg::CODE_TYPE_CALC_BONUS_PERSONAL_DEF);
        $levelsTeam = $this->getLevelsByType(Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF);
        /* calculate bonus */
        $bonus = $this->subCalc->exec($dwnlCompress, $levelsPers, $levelsTeam);
        /* convert calculated bonus to transactions */
        $trans = $this->getTransactions($bonus, $teamPeriod);
        /* register bonus operation */
        /* register operation in log */
        /* mark this calculation complete */
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, false);
        $this->logger->info("Team bonus is completed.");
    }

    /**
     * @param array $bonus [custId => bonusValue]
     * @param \Praxigento\BonusBase\Repo\Entity\Data\Period $period
     * @return \Praxigento\Accounting\Repo\Entity\Data\Transaction[]
     */
    private function getTransactions($bonus, $period)
    {
        $dsEnd = $period->getDstampEnd();
        $dateApplied = $this->hlpPeriod->getTimestampTo($dsEnd);
        $result = $this->subPrepareTrans->exec($bonus, $dateApplied);
        return $result;
    }

    /**
     * Load bonus percents by levels for given calculation type.
     *
     * @param string $code
     * @return array ordered by level asc ([$level => $percent])
     */
    private function getLevelsByType($code)
    {
        $calcTypeId = $this->repoCalcType->getIdByCode($code);
        $result = $this->repoLevel->getByCalcTypeId($calcTypeId);
        return $result;
    }

    /**
     * Get compressed downline for base calculation from Bonus module.
     *
     * @param int $calcId
     * @return \Praxigento\BonusHybrid\Repo\Entity\Data\Downline[]
     */
    private function getBonusDwnl($calcId)
    {
        $where = EDwnlBon::ATTR_CALC_REF . '=' . (int)$calcId;
        $result = $this->repoDwnlBon->get($where);
        return $result;
    }

    /**
     * Get period and calculation data for all related calculation types.
     *
     * @return array [$compressCalc, $tvCalc, $teamPeriod, $teamCalc]
     */
    private function getCalcData()
    {
        /* get period & calc data for team bonus & TV volumes calculations */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->procPeriodGet::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_VALUE_TV);
        $ctx->set($this->procPeriodGet::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $tvCalc */
        $tvCalc = $ctx->get($this->procPeriodGet::CTX_OUT_BASE_CALC_DATA);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Period $teamPeriod */
        $teamPeriod = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_PERIOD_DATA);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $teamCalc */
        $teamCalc = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_CALC_DATA);
        /* get period and calc data for compression calc (basic for TV volumes) */
        $ctx->set($this->procPeriodGet::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1);
        $ctx->set($this->procPeriodGet::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_VALUE_TV);
        $ctx->set($this->procPeriodGet::CTX_IN_DEP_IGNORE_COMPLETE, true);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $compressCalc */
        $compressCalc = $ctx->get($this->procPeriodGet::CTX_OUT_BASE_CALC_DATA);
        /* compose result */
        $result = [$compressCalc, $tvCalc, $teamPeriod, $teamCalc];
        return $result;
    }

}