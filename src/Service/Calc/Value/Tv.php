<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Value;

use Praxigento\BonusBase\Repo\Entity\Data\Calculation as ECalc;
use Praxigento\BonusBase\Service\Period\Calc\Get\IDependent as PGetPeriodDep;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EDwnlBon;
use Praxigento\BonusHybrid\Service\Calc\Value\ITv;

class Tv
    implements \Praxigento\BonusHybrid\Service\Calc\Value\ITv
{

    /** @var \Praxigento\Core\Fw\Logger\App */
    private $logger;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent */
    private $procPeriodGet;
    /** @var \Praxigento\BonusBase\Repo\Entity\Calculation */
    private $repoCalc;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoDwnlBon;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Value\Tv\Calc */
    private $subCalc;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\BonusBase\Repo\Entity\Calculation $repoCalc,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoDwnlBon,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\Value\Tv\Calc $subCalc
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
        $dwnlCompress = $this->repoDwnlBon->getByCalcId($compressCalcId);
        /* populate downline with TV data */
        $dwnlUpdated = $this->subCalc->exec($dwnlCompress);
        /* save updates into repo */
        $this->updateTv($dwnlUpdated);
        /* mark this calculation complete */
        $this->repoCalc->markComplete($tvCalcId);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("TV calculation is completed.");
    }

    /**
     * Get data for periods/calculations.
     *
     * @return array [$compressCalc, $tvCalc]
     */
    private function getCalcData()
    {
        /* get period & calc data */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(PGetPeriodDep::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1);
        $ctx->set(PGetPeriodDep::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_VALUE_TV);
        $this->procPeriodGet->exec($ctx);
        /** @var ECalc $compressCalc */
        $compressCalc = $ctx->get(PGetPeriodDep::CTX_OUT_BASE_CALC_DATA);
        /** @var ECalc $tvCalc */
        $tvCalc = $ctx->get(PGetPeriodDep::CTX_OUT_DEP_CALC_DATA);
        $result = [$compressCalc, $tvCalc];
        return $result;
    }

    /**
     * Update downline tree with calculated TV values.
     *
     * @param EDwnlBon[] $dwnl
     */
    private function updateTv($dwnl)
    {
        $entity = new  EDwnlBon();
        /** @var EDwnlBon $one */
        foreach ($dwnl as $one) {
            $tv = $one->getTv();
            $calcId = $one->getCalculationRef();
            $custId = $one->getCustomerRef();
            $entity->setTv($tv);
            $id = [
                EDwnlBon::ATTR_CALC_REF => $calcId,
                EDwnlBon::ATTR_CUST_REF => $custId
            ];
            $this->repoDwnlBon->updateById($id, $entity);
        }
    }

}