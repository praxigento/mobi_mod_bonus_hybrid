<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Value;

use Praxigento\BonusBase\Service\Period\Calc\Get\IDependent as PGetPeriodDep;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EDwnlBon;

class Ov
    implements IOv
{

    /** @var \Praxigento\Core\Fw\Logger\App */
    private $logger;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent */
    private $procPeriodGet;
    /** @var \Praxigento\BonusBase\Repo\Entity\Calculation */
    private $repoCalc;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoDwnlBon;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Value\Ov\Calc */
    private $subCalc;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\BonusBase\Repo\Entity\Calculation $repoCalc,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoDwnlBon,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\Value\Ov\Calc $subCalc
    )
    {
        $this->logger = $logger;
        $this->repoCalc = $repoCalc;
        $this->repoDwnlBon = $repoDwnlBon;
        $this->procPeriodGet = $procPeriodGet;
        $this->subCalc = $subCalc;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        $this->logger->info("OV calculation is started.");
        /**
         * perform processing
         */
        /* get dependent calculation data */
        list($compressCalc, $ovCalc) = $this->getCalcData();
        $compressCalcId = $compressCalc->getId();
        $ovCalcId = $ovCalc->getId();
        /* load compressed downlines for period */
        $dwnlCompress = $this->getBonusDwnl($compressCalcId);
        /* populate downline with OV data */
        $dwnlUpdated = $this->subCalc->exec($dwnlCompress);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, false);
        $this->logger->info("OV calculation is completed.");
    }

    /**
     * Get downline for base calculation from Bonus module.
     *
     * @param int $calcId
     * @return EDwnlBon[]
     */
    private function getBonusDwnl($calcId)
    {
        $where = EDwnlBon::ATTR_CALC_REF . '=' . (int)$calcId;
        $result = $this->repoDwnlBon->get($where);
        return $result;
    }

    /**
     * Get data for periods/calculations.
     *
     * @return array [$compressCalc, $ovCalc]
     */
    private function getCalcData()
    {
        /* get period & calc data */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(PGetPeriodDep::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1);
        $ctx->set(PGetPeriodDep::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_VALUE_OV);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $compressCalc */
        $compressCalc = $ctx->get(PGetPeriodDep::CTX_OUT_BASE_CALC_DATA);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $ovCalc */
        $ovCalc = $ctx->get(PGetPeriodDep::CTX_OUT_DEP_CALC_DATA);
        $result = [$compressCalc, $ovCalc];
        return $result;
    }

}