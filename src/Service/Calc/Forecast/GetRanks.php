<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast;

use Praxigento\BonusBase\Repo\Query\Period\Calcs\GetLast\ByCalcTypeCode\Builder as QbldPeriodCalcLast;
use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Defaults as Def;
use Praxigento\BonusHybrid\Repo\Data\Entity\Compression\Oi as EOi;

/**
 * Get the last OI calculation, collect customers and its qualification ranks then populate downline tree
 * with ranks.
 */
class GetRanks
{
    const CTX_IN_DATE_ON = 'dateOn';
    const CTX_IO_TREE = 'dwnlTree';

    /** @var \Praxigento\BonusBase\Service\IPeriod */
    protected $callBonusPeriod;
    /** @var \Praxigento\BonusHybrid\Tool\IScheme */
    protected $hlpScheme;
    /** @var \Praxigento\BonusBase\Repo\Query\Period\Calcs\GetLast\ByCalcTypeCode\Builder */
    protected $qbldPeriodCalcGetLast;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Compression\Oi */
    protected $repoCompressOi;
    /** @var \Praxigento\Downline\Repo\Entity\Customer */
    protected $repoDownline;
    /** @var \Praxigento\BonusBase\Repo\Entity\Rank */
    protected $repoRanks;

    public function __construct(
        \Praxigento\BonusHybrid\Tool\IScheme $hlpScheme,
        \Praxigento\BonusBase\Repo\Entity\Rank $repoRanks,
        \Praxigento\BonusHybrid\Repo\Entity\Compression\Oi $repoCompressOi,
        \Praxigento\Downline\Repo\Entity\Customer $repoDownline,
        \Praxigento\BonusBase\Service\IPeriod $callBonusPeriod,
        \Praxigento\BonusBase\Repo\Query\Period\Calcs\GetLast\ByCalcTypeCode\Builder $qbldPeriodCalcGetLast
    )
    {
        $this->hlpScheme = $hlpScheme;
        $this->repoRanks = $repoRanks;
        $this->repoCompressOi = $repoCompressOi;
        $this->repoDownline = $repoDownline;
        $this->callBonusPeriod = $callBonusPeriod;
        $this->qbldPeriodCalcGetLast = $qbldPeriodCalcGetLast;
    }

    /**
     * @param \Flancer32\Lib\Data $ctx
     */
    public function exec(\Flancer32\Lib\Data $ctx = null)
    {
        /* get working data from context */
        $dateOn = $ctx->get(self::CTX_IN_DATE_ON);
        /** @var \Praxigento\BonusHybrid\Repo\Entity\Data\Downline[] $dwnlTree */
        $dwnlTree = $ctx->get(self::CTX_IO_TREE);


        /**
         * Perform processing
         */

        /* get OI data with ranks for both schemas */
        $def = $this->getOiData(Cfg::CODE_TYPE_CALC_COMPRESS_PHASE2_DEF, $dateOn);
        $eu = $this->getOiData(Cfg::CODE_TYPE_CALC_COMPRESS_PHASE2_EU, $dateOn);
        $ranks[Def::SCHEMA_DEFAULT] = $def;
        $ranks[Def::SCHEMA_EU] = $eu;

        /* get downline data (with countries) to define bonus scheme for customer */
        $columns = [
            \Praxigento\Downline\Data\Entity\Customer::ATTR_CUSTOMER_ID,
            \Praxigento\Downline\Data\Entity\Customer::ATTR_COUNTRY_CODE
        ];
        /** @var \Praxigento\Downline\Data\Entity\Customer[] $customers */
        $customers = $this->repoDownline->get(null, null, null, null, $columns);

        /**
         * walk through the customers (w/o IDs in indexes) and map ranks from OI data to current downline
         * tree (indexed by IDs)
         */
        $defRankId = $this->getDefaultRankId();
        /** @var \Praxigento\Downline\Data\Entity\Customer $one */
        foreach ($customers as $one) {
            $custId = $one->getCustomerId();
            $scheme = $this->hlpScheme->getSchemeByCustomer($one);
            if (isset($ranks[$scheme][$custId])) {
                $rankId = $ranks[$scheme][$custId];
            } else {
                $rankId = $defRankId;
            }
            if (isset($dwnlTree[$custId])) $dwnlTree[$custId]->setRankRef($rankId);
        }
    }

    /**
     * Get ID for rank with code DISTRIBUTOR.
     * @return int
     */
    protected function getDefaultRankId()
    {
        $result = $this->repoRanks->getIdByCode(Def::RANK_DISTRIBUTOR);
        return $result;
    }

    /**
     * Get Compressed OI data for the latest period by calculation type.
     *
     * @param string $calcTypeCode
     * @param array $ranks ranks codes map by id
     * @return array
     */
    protected function getOiData($calcTypeCode, $dateOn)
    {

        /* TODO: split code on 2 parts */
        $query = $this->qbldPeriodCalcGetLast->build();
        $bind = [
            QbldPeriodCalcLast::BND_CODE => $calcTypeCode,
            QbldPeriodCalcLast::BND_DATE => $dateOn,
            QbldPeriodCalcLast::BND_STATE => Cfg::CALC_STATE_COMPLETE
        ];
        $rowCalc = $query->getConnection()->fetchRow($query, $bind);
        $calcId = $rowCalc[$this->qbldPeriodCalcGetLast::A_CALC_ID];

        /* this is part II ofr splitting */
        /* get compressed data from repository (DB) by calc ID */
        $where = EOi::ATTR_CALC_ID . '=' . (int)$calcId;
        $rows = $this->repoCompressOi->get($where);
        $result = [];
        /** @var \Praxigento\BonusHybrid\Repo\Data\Entity\Compression\Oi $row */
        foreach ($rows as $row) {
            $rankId = $row->getRankId();
            $custId = $row->getCustomerId();
            $result[$custId] = $rankId;
        }
        return $result;
    }
}