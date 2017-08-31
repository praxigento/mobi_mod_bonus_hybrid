<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc;

use Praxigento\BonusHybrid\Config as Cfg;

class PvWriteOff
    implements IPvWriteOff
{
    /** @var  \Praxigento\Core\Tool\IPeriod */
    private $hlpPeriod;
    /**
     * @var \Praxigento\Core\Fw\Logger\App
     * @deprecated use it or remove it
     */
    private $logger;
    /** @var \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent */
    private $procPeriodGet;
    /** @var  \Praxigento\Accounting\Repo\Entity\Type\Asset */
    protected $repoTypeAsset;
    /** @var  \Praxigento\Accounting\Repo\Entity\Type\Operation */
    protected $repoTypeOper;
    /** @var PvWriteOff\Query\GetData\Builder */
    private $subQbGetData;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\Core\Tool\IPeriod $hlpPeriod,
        \Praxigento\Accounting\Repo\Entity\Type\Asset $repoTypeAsset,
        \Praxigento\Accounting\Repo\Entity\Type\Operation $repoTypeOper,
        \Praxigento\BonusBase\Service\Period\Calc\Get\IDependent $procPeriodGet,
        PvWriteOff\Query\GetData\Builder $subQbGetData
    )
    {
        $this->logger = $logger;
        $this->hlpPeriod = $hlpPeriod;
        $this->repoTypeAsset = $repoTypeAsset;
        $this->repoTypeOper = $repoTypeOper;
        $this->procPeriodGet = $procPeriodGet;
        $this->subQbGetData = $subQbGetData;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /* get working data from context */

        /**
         * perform processing
         */
        /* get period & calc data */
        $ctx = new \Praxigento\Core\Data();
        $ctx->set($this->procPeriodGet::CTX_IN_BASE_TYPE_CODE, Cfg::CODE_TYPE_CALC_BONUS_SIGNUP_DEBIT);
        $ctx->set($this->procPeriodGet::CTX_IN_DEP_TYPE_CODE, Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $ctx->set($this->procPeriodGet::CTX_IN_LOAD_DATA, true);
        $this->procPeriodGet->exec($ctx);
        /* get calculation data */
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Period $periodData */
        $periodData = $ctx->get($this->procPeriodGet::CTX_OUT_PERIOD_DATA);
        $from = $periodData->getDstampBegin();
        $to = $periodData->getDstampEnd();
        /** @var \Praxigento\BonusBase\Repo\Entity\Data\Calculation $calcData */
        $calcData = $ctx->get($this->procPeriodGet::CTX_OUT_CALC_DATA);
        $calcId = $calcData->getId();
        /* get accounting data for calculation */
        $bu = $this->getTransData($calcId, $from, $to);
    }

    private function getTransData($calcId, $from, $to)
    {
        $assetTypeId = $this->repoTypeAsset->getIdByCode(Cfg::CODE_TYPE_ASSET_PV);
        $operTypeId = $this->repoTypeOper->getIdByCode(Cfg::CODE_TYPE_OPER_PV_WRITE_OFF);
        $dateFrom = $this->hlpPeriod->getTimestampFrom($from);
        $dateTo = $this->hlpPeriod->getTimestampNextFrom($to);

        $query = $this->subQbGetData->build();
        $bind = [
            $this->subQbGetData::BND_ASSET_TYPE_ID => $assetTypeId,
            $this->subQbGetData::BND_CALC_ID => $calcId,
            $this->subQbGetData::BND_DATE_FROM => $dateFrom,
            $this->subQbGetData::BND_DATE_TO => $dateTo,
            $this->subQbGetData::BND_OPER_TYPE_ID => $operTypeId
        ];

        $conn = $query->getConnection();
        $rows = $conn->fetchAll($query, $bind);
    }

}