<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EBonDwnl;
use Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase1 as PCpmrsPhase1;
use Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase2 as PCpmrsPhase2;
use Praxigento\BonusHybrid\Service\Calc\A\Proc\Ov as POv;
use Praxigento\BonusHybrid\Service\Calc\A\Proc\Tv as PTv;
use Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc\Clean as PCalcClean;
use Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc\Register as PCalcReg;
use Praxigento\BonusHybrid\Service\Calc\Forecast\Compress\GetPlainData as PGetPlainData;
use Praxigento\BonusHybrid\Service\Calc\Forecast\Compress\UpdateDwnl as PUpdateDwnl;

class Compress
    implements \Praxigento\BonusHybrid\Service\Calc\Forecast\ICompress
{
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc\Clean */
    private $procCalcClean;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc\Register */
    private $procCalcReg;
    /** @var \Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase1 */
    private $procCmprsPhase1;
    /** @var \Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase2 */
    private $procCmprsPhase2;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\Compress\GetPlainData */
    private $procGetPlainData;
    /** @var \Praxigento\BonusHybrid\Service\Calc\A\Proc\Ov */
    private $procOv;
    /** @var \Praxigento\BonusHybrid\Service\Calc\A\Proc\Tv */
    private $procTv;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Forecast\Compress\UpdateDwnl */
    private $procUpdateDwnl;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoBonDwnl;
    /** @var \Praxigento\BonusBase\Repo\Dao\Calculation */
    private $repoCalc;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\BonusBase\Repo\Dao\Calculation $repoCalc,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoBonDwnl,
        \Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase1 $procCmprsPhase1,
        \Praxigento\BonusHybrid\Service\Calc\A\Proc\Compress\Phase2 $procCmprsPhase2,
        \Praxigento\BonusHybrid\Service\Calc\A\Proc\Tv $procTv,
        \Praxigento\BonusHybrid\Service\Calc\A\Proc\Ov $procOv,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc\Clean $procCalcClean,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\A\Proc\Calc\Register $procCalcReg,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\Compress\GetPlainData $procGetPlainData,
        \Praxigento\BonusHybrid\Service\Calc\Forecast\Compress\UpdateDwnl $procUpdateDwnl
    )
    {
        $this->logger = $logger;
        $this->repoCalc = $repoCalc;
        $this->repoBonDwnl = $repoBonDwnl;
        $this->procCmprsPhase1 = $procCmprsPhase1;
        $this->procCmprsPhase2 = $procCmprsPhase2;
        $this->procTv = $procTv;
        $this->procOv = $procOv;
        $this->procCalcClean = $procCalcClean;
        $this->procCalcReg = $procCalcReg;
        $this->procGetPlainData = $procGetPlainData;
        $this->procUpdateDwnl = $procUpdateDwnl;
    }

    /**
     * @param EBonDwnl $dwnl
     * @return EBonDwnl
     */
    private function calcOv($dwnl)
    {
        $in = new \Praxigento\Core\Data();
        $in->set(POv::IN_DWNL, $dwnl);
        $in->set(POv::IN_USE_SIGN_UP, false);
        $out = $this->procOv->exec($in);
        $result = $out->get(POv::OUT_DWNL);
        return $result;
    }

    /**
     * @param EBonDwnl $dwnl
     * @return EBonDwnl
     */
    private function calcTv($dwnl)
    {
        $in = new \Praxigento\Core\Data();
        $in->set(PTv::IN_DWNL, $dwnl);
        $out = $this->procTv->exec($in);
        $result = $out->get(PTv::OUT_DWNL);
        return $result;
    }

    /**
     * Clean up existing forecast calculation data.
     */
    private function cleanCalc()
    {
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(PCalcClean::IN_CALC_TYPE_CODE, Cfg::CODE_TYPE_CALC_FORECAST_PHASE1);
        $this->procCalcClean->exec($ctx);
    }

    private function compressPhase1($calcId)
    {
        /* get the last forecast plain calculation and extract collected PV */
        $inPv = new \Praxigento\Core\Data();
        $outPv = $this->procGetPlainData->exec($inPv);
        $pv = $outPv->get(PGetPlainData::OUT_PV);
        $downline = $outPv->get(PGetPlainData::OUT_DWNL);

        $ctx = new \Praxigento\Core\Data();
        $ctx->set(PCpmrsPhase1::IN_CALC_ID, $calcId);
        $ctx->set(PCpmrsPhase1::IN_PV, $pv);
        $ctx->set(PCpmrsPhase1::IN_DWNL_PLAIN, $downline);
        $ctx->set(PCpmrsPhase1::IN_KEY_CALC_ID, EBonDwnl::ATTR_CALC_REF);
        $ctx->set(PCpmrsPhase1::IN_KEY_CUST_ID, EBonDwnl::ATTR_CUST_REF);
        $ctx->set(PCpmrsPhase1::IN_KEY_PARENT_ID, EBonDwnl::ATTR_PARENT_REF);
        $ctx->set(PCpmrsPhase1::IN_KEY_DEPTH, EBonDwnl::ATTR_DEPTH);
        $ctx->set(PCpmrsPhase1::IN_KEY_PATH, EBonDwnl::ATTR_PATH);
        $ctx->set(PCpmrsPhase1::IN_KEY_PV, EBonDwnl::ATTR_PV);

        $outPhase1 = $this->procCmprsPhase1->exec($ctx);
        $result = $outPhase1->get(PCpmrsPhase1::OUT_COMPRESSED);
        return $result;
    }

    private function compressPhase2($calcId, $scheme, $dwnlPhase1)
    {
        list($pv, $dwnlPlain) = $this->getDwnlPlain();
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(PCpmrsPhase2::IN_CALC_ID_PHASE2, $calcId);
        $ctx->set(PCpmrsPhase2::IN_MAP_PV, $pv);
        $ctx->set(PCpmrsPhase2::IN_DWNL_PLAIN, $dwnlPlain);
        $ctx->set(PCpmrsPhase2::IN_DWNL_PHASE1, $dwnlPhase1);
        $ctx->set(PCpmrsPhase2::IN_SCHEME, $scheme);
        $out = $this->procCmprsPhase2->exec($ctx);
        $result = $out->get(PCpmrsPhase2::OUT_DWNL_PHASE2);
        return $result;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        $this->logger->info("'Forecast Compress' calculation is started.");

        /* clean up existing calculation data and register new one */
        $this->cleanCalc();
        $calcId = $this->registerCalc();

        /* perform Phase1 compression */
        $dwnlPhase1 = $this->compressPhase1($calcId);

        /* calculate TV & OV on compressed tree */
        $dwnlPhase1 = $this->calcTv($dwnlPhase1);
        $dwnlPhase1 = $this->calcOv($dwnlPhase1);

        /* calculate phase 2 compression for both schemes */
        $dwnlPhase2Def = $this->compressPhase2($calcId, Cfg::SCHEMA_DEFAULT, $dwnlPhase1);
        $dwnlPhase2Eu = $this->compressPhase2($calcId, Cfg::SCHEMA_EU, $dwnlPhase1);

        /* ... then populate Phase1 downline with ranks from Phase2 downlines */
        $dwnlPhase1 = $this->updateDwnl($dwnlPhase1, $dwnlPhase2Def, $dwnlPhase2Eu);

        /* save updated Phase1 compression */
        $this->saveDownline($dwnlPhase1);

        /* finalize calculation */
        $this->repoCalc->markComplete($calcId);

        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("'Forecast Compress' calculation is completed.");
    }

    private function getDwnlPlain()
    {
        /* get the last forecast plain calculation and extract collected PV */
        $in = new \Praxigento\Core\Data();
        $out = $this->procGetPlainData->exec($in);
        $pv = $out->get(PGetPlainData::OUT_PV);
        $downline = $out->get(PGetPlainData::OUT_DWNL);
        $result = [$pv, $downline];
        return $result;
    }

    /**
     * Register new compression calculation.
     *
     * @return int calculation ID.
     */
    private function registerCalc()
    {
        $ctx = new \Praxigento\Core\Data();
        $ctx->set(PCalcReg::IN_CALC_TYPE_CODE, Cfg::CODE_TYPE_CALC_FORECAST_PHASE1);
        /** @var \Praxigento\Core\Data $res */
        $res = $this->procCalcReg->exec($ctx);
        $result = $res->get(PCalcReg::OUT_CALC_ID);
        return $result;
    }

    private function saveDownline($items)
    {
        foreach ($items as $item) {
            $entryId = $item->getId();
            $found = $this->repoBonDwnl->getById($entryId);
            if ($found) {
                $this->repoBonDwnl->updateById($entryId, $item);
            } else {
                $this->repoBonDwnl->create($item);
            }
        }
    }

    private function updateDwnl($dwnlPhase1, $dwnlPhase2Def, $dwnlPhase2Eu)
    {
        $in = new \Praxigento\Core\Data();
        $in->set(PUpdateDwnl::IN_DWNL_PHASE1, $dwnlPhase1);
        $in->set(PUpdateDwnl::IN_DWNL_PHASE2_DEF, $dwnlPhase2Def);
        $in->set(PUpdateDwnl::IN_DWNL_PHASE2_EU, $dwnlPhase2Eu);
        $out = $this->procUpdateDwnl->exec($in);
        $result = $out->get(PUpdateDwnl::OUT_DWNL_PHASE1);
        return $result;
    }
}