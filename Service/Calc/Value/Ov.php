<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Value;

use Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Request as AGetPeriodRequest;
use Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Response as AGetPeriodResponse;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;

class Ov
    implements \Praxigento\Core\Api\App\Service\Process
{

    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent */
    private $servPeriodGet;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $daoBonDwnl;
    /** @var \Praxigento\BonusBase\Repo\Dao\Calculation */
    private $daoCalc;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Value\Ov\Calc */
    private $subCalc;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\BonusBase\Repo\Dao\Calculation $daoCalc,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoBonDwnl,
        \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent $servPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\Value\Ov\Calc $subCalc
    )
    {
        $this->logger = $logger;
        $this->daoCalc = $daoCalc;
        $this->daoBonDwnl = $daoBonDwnl;
        $this->servPeriodGet = $servPeriodGet;
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
        $this->daoCalc->markComplete($ovCalcId);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("OV calculation is completed.");
    }

    /**
     * Get data for periods/calculations.
     *
     * @return array [$compressCalc, $ovCalc]
     * @throws \Exception
     */
    private function getCalcData()
    {
        /**
         * Get period & calc data.
         */
        $req = new AGetPeriodRequest();
        $req->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1);
        $req->setDepCalcTypeCode(Cfg::CODE_TYPE_CALC_VALUE_OV);
        /** @var AGetPeriodResponse $resp */
        $resp = $this->servPeriodGet->exec($req);
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $compressCalc */
        $compressCalc = $resp->getBaseCalcData();
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $ovCalc */
        $ovCalc = $resp->getDepCalcData();
        /**
         * Compose result.
         */
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
                EBonDwnl::A_CALC_REF => $calcId,
                EBonDwnl::A_CUST_REF => $custId
            ];
            $this->daoBonDwnl->updateById($id, $entity);
        }
    }
}