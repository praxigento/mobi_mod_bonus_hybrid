<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Defaults as Def;
use Praxigento\BonusHybrid\Repo\Data\Entity\Compression\Oi as EOi;
use Praxigento\Downline\Data\Entity\Customer as ECustomer;

/**
 * Get the last OI calculation and collect customers and its qualification ranks.
 */
class GetRanks
{
    const A_CUST_ID = 'custId';
    const A_RANK_CODE = 'rank';

    /** @var \Praxigento\BonusBase\Service\IPeriod */
    protected $callBonusPeriod;
    /** @var \Praxigento\BonusHybrid\Tool\IScheme */
    protected $hlpScheme;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Compression\Oi */
    protected $repoCompressOi;
    /** @var \Praxigento\Downline\Repo\Entity\ICustomer */
    protected $repoDownline;
    /** @var \Praxigento\BonusBase\Repo\Entity\Rank */
    protected $repoRanks;

    public function __construct(
        \Praxigento\BonusHybrid\Tool\IScheme $hlpScheme,
        \Praxigento\BonusBase\Repo\Entity\Rank $repoRanks,
        \Praxigento\BonusHybrid\Repo\Entity\Compression\Oi $repoCompressOi,
        \Praxigento\Downline\Repo\Entity\ICustomer $repoDownline,
        \Praxigento\BonusBase\Service\IPeriod $callBonusPeriod
    ) {
        $this->hlpScheme = $hlpScheme;
        $this->repoRanks = $repoRanks;
        $this->repoCompressOi = $repoCompressOi;
        $this->repoDownline = $repoDownline;
        $this->callBonusPeriod = $callBonusPeriod;
    }

    /**
     * @param \Flancer32\Lib\Data $ctx
     */
    public function exec(\Flancer32\Lib\Data $ctx = null)
    {
        $result = [];
        /* get all ranks */
        $ranksCodes = $this->getRanks();
        /* get OI data for both schemas */
        $def = $this->getOiData(Cfg::CODE_TYPE_CALC_COMPRESS_FOR_OI_DEF, $ranksCodes);
        $eu = $this->getOiData(Cfg::CODE_TYPE_CALC_COMPRESS_FOR_OI_EU, $ranksCodes);
        $ranks[Def::SCHEMA_DEFAULT] = $def;
        $ranks[Def::SCHEMA_EU] = $eu;
        /* get downline data */
        $customers = $this->repoDownline->get();
        /** @var \Praxigento\Downline\Data\Entity\Customer $one */
        foreach ($customers as $one) {
            /* TODO: use as object not as array */
            $customer = (array)$one->get();
            $custId = $customer[ECustomer::ATTR_CUSTOMER_ID];
            $scheme = $this->hlpScheme->getSchemeByCustomer($customer);
            if (isset($ranks[$scheme][$custId])) {
                $code = $ranks[$scheme][$custId];
            } else {
                $code = Def::RANK_DISTRIBUTOR;
            }
            $result[$custId] = $code;
        }
        return $result;
    }

    /**
     * Get Compressed OI data for the latest period by calculation type.
     *
     * @param string $calcTypeCode
     * @param array $ranks ranks codes map by id
     * @return array
     */
    protected function getOiData($calcTypeCode, $ranks)
    {
        $req = new \Praxigento\BonusBase\Service\Period\Request\GetLatest();
        $req->setCalcTypeCode($calcTypeCode);
        $resp = $this->callBonusPeriod->getLatest($req);
        /** @var \Praxigento\BonusBase\Data\Entity\Calculation $calcData */
        $calcData = $resp->getCalcData();
        $calcId = $calcData->getId();
        $where = EOi::ATTR_CALC_ID . '=' . (int)$calcId;
        $rows = $this->repoCompressOi->get($where);
        $result = [];
        /** @var \Praxigento\BonusHybrid\Repo\Data\Entity\Compression\Oi $row */
        foreach ($rows as $row) {
            $rankId = $row->getRankId();
            $custId = $row->getCustomerId();
            $rankCode = $ranks[$rankId];
            $result[$custId] = $rankCode;
        }
        return $result;
    }

    /**
     * Map ranks codes by ranks ids.
     * @return array
     */
    protected function getRanks()
    {
        $result = [];
        $rows = $this->repoRanks->get();
        /** @var \Praxigento\BonusBase\Data\Entity\Rank $row */
        foreach ($rows as $row) {
            $id = $row->getId();
            $code = $row->getCode();
            $result[$id] = $code;
        }
        return $result;
    }
}