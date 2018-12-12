<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Value;

use Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Request as AGetPeriodRequest;
use Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Response as AGetPeriodResponse;
use Praxigento\BonusBase\Repo\Data\Calculation as ECalc;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;

class Tv
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
    /** @var \Praxigento\BonusHybrid\Service\Calc\Value\Tv\Calc */
    private $subCalc;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\BonusBase\Repo\Dao\Calculation $daoCalc,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoBonDwnl,
        \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent $servPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\Value\Tv\Calc $subCalc
    ) {
        $this->logger = $logger;
        $this->daoCalc = $daoCalc;
        $this->daoBonDwnl = $daoBonDwnl;
        $this->servPeriodGet = $servPeriodGet;
        $this->subCalc = $subCalc;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        $this->logger->info("TV calculation is started.");
        /**
         * perform processing
         */
        /**
         * Get dependent calculation data
         *
         * @var ECalc $compressCalc
         * @var ECalc $tvCalc
         *
         */
        list($compressCalc, $tvCalc) = $this->getCalcData();
        $compressCalcId = $compressCalc->getId();
        $tvCalcId = $tvCalc->getId();
        /* load compressed downlines for period */
        $dwnlCompress = $this->daoBonDwnl->getByCalcId($compressCalcId);
        /* populate downline with TV data */
        $dwnlUpdated = $this->subCalc->exec($dwnlCompress);
        /* save updates into repo */
        $this->updateTv($dwnlUpdated);
        /* mark this calculation complete */
        $this->daoCalc->markComplete($tvCalcId);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("TV calculation is completed.");
    }

    /**
     * Get data for periods/calculations.
     *
     * @return array [$compressCalc, $tvCalc]
     * @throws \Exception
     */
    private function getCalcData()
    {
        /**
         * Get period & calc data.
         */
        $req = new AGetPeriodRequest();
        $req->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1);
        $req->setDepCalcTypeCode(Cfg::CODE_TYPE_CALC_VALUE_TV);
        /** @var AGetPeriodResponse $resp */
        $resp = $this->servPeriodGet->exec($req);
        /** @var ECalc $compressCalc */
        $compressCalc = $resp->getBaseCalcData();
        /** @var ECalc $tvCalc */
        $tvCalc = $resp->getDepCalcData();
        /**
         * Compose result.
         */
        $result = [$compressCalc, $tvCalc];
        return $result;
    }

    /**
     * Update downline tree with calculated TV values.
     *
     * @param EBonDwnl[] $dwnl
     */
    private function updateTv($dwnl)
    {
        $entity = new  EBonDwnl();
        /** @var EBonDwnl $one */
        foreach ($dwnl as $one) {
            $tv = $one->getTv();
            $calcId = $one->getCalculationRef();
            $custId = $one->getCustomerRef();
            $entity->setTv($tv);
            $id = [
                EBonDwnl::A_CALC_REF => $calcId,
                EBonDwnl::A_CUST_REF => $custId
            ];
            $this->daoBonDwnl->updateById($id, $entity);
        }
    }

}