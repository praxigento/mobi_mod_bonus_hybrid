<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EDwnlBon;

class ValueTv
    implements IValueTv
{

    /** @var \Praxigento\Core\Fw\Logger\App */
    private $logger;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent */
    private $procPeriodGet;
    /** @var \Praxigento\BonusBase\Repo\Entity\Calculation */
    private $repoCalc;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoDwnlBon;
    /** @var \Praxigento\BonusHybrid\Service\Calc\ValueTv\Calc */
    private $subCalc;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\BonusBase\Repo\Entity\Calculation $repoCalc,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoDwnlBon,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet,
        \Praxigento\BonusHybrid\Service\Calc\ValueTv\Calc $subCalc
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
        /* get dependent calculation data */
        list($baseCalc, $depCalc) = $this->getCalcData();
        $baseCalcId = $baseCalc->getId();
        $depCalcId = $depCalc->getId();
        /* load downlines (compressed for period & current) */
        $dwnlCompress = $this->getBonusDwnl($baseCalcId);
        /* populate downline with TV data */
        $dwnlUpdated = $this->subCalc->exec($dwnlCompress);
        /* save updates into repo */
        $this->updateTv($dwnlUpdated);
        /* mark this calculation complete */
        $this->repoCalc->markComplete($depCalcId);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("TV calculation is completed.");
    }

    /**
     * Get downline for base calculation from Bonus module.
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
     * Get data for dependent calculation.
     *
     * @return array [$periodData, $calcData]
     */
    private function getCalcData()
    {
        /* get period & calc data */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->procPeriodGet::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1);
        $ctx->set($this->procPeriodGet::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_VALUE_TV);
        $this->procPeriodGet->exec($ctx);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $depCalcData */
        $baseCalcData = $ctx->get($this->procPeriodGet::CTX_OUT_BASE_CALC_DATA);
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $depCalcData */
        $depCalcData = $ctx->get($this->procPeriodGet::CTX_OUT_DEP_CALC_DATA);
        $result = [$baseCalcData, $depCalcData];
        return $result;
    }

    /**
     * Update downline tree with calculated TV values.
     *
     * @param \Praxigento\BonusHybrid\Repo\Entity\Data\Downline[] $dwnl
     */
    private function updateTv($dwnl)
    {
        $entity = new  EDwnlBon();
        /** @var \Praxigento\BonusHybrid\Repo\Entity\Data\Downline $one */
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