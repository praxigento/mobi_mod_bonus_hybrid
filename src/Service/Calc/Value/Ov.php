<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Value;

use Praxigento\BonusBase\Service\Period\Calc\Get\IDependent as PGetPeriodDep;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EBonDwnl;

class Ov
    implements \Praxigento\BonusHybrid\Service\Calc\Value\IOv
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
        /* get compressed downline & populate it with OV data */
        $dwnl = $this->subCalc->exec($compressCalcId);
        /* save updates into repo */
        $this->updateOv($dwnl);
        /* mark this calculation complete */
        $this->repoCalc->markComplete($ovCalcId);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("OV calculation is completed.");
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

    /**
     * Update downline tree with calculated OV values.
     *
     * @param EBonDwnl[] $dwnl
     */
    private function updateOv($dwnl)
    {
        $entity = new  EBonDwnl();
        /** @var EBonDwnl $one */
        foreach ($dwnl as $one) {
            $ov = $one->getOv();
            $calcId = $one->getCalculationRef();
            $custId = $one->getCustomerRef();
            $entity->setOv($ov);
            $id = [
                EBonDwnl::ATTR_CALC_REF => $calcId,
                EBonDwnl::ATTR_CUST_REF => $custId
            ];
            $this->repoDwnlBon->updateById($id, $entity);
        }
    }
}