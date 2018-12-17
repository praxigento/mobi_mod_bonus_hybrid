<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Unqualified;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;
use Praxigento\BonusHybrid\Repo\Data\Downline\Inactive as EInact;
use Praxigento\BonusHybrid\Repo\Query\GetInactive as QGetInact;
use Praxigento\Downline\Repo\Data\Customer as EDwnlCust;

/**
 * Collect customer inactivity stats.
 *
 * This is internal service (for this module only), so it has no own interface.
 */
class Collect
    implements \Praxigento\Core\Api\App\Service\Process
{
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $daoBonDwnl;
    /** @var \Praxigento\BonusBase\Repo\Dao\Calculation */
    private $daoCalc;
    /** @var \Praxigento\Downline\Repo\Dao\Customer */
    private $daoDwnlCust;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline\Inactive */
    private $daoInact;
    /** @var \Praxigento\BonusHybrid\Api\Helper\Scheme */
    private $hlpScheme;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusHybrid\Repo\Query\GetInactive */
    private $qGetInact;
    /** @var \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent */
    private $servGetDepend;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Downline\Repo\Dao\Customer $daoDwnlCust,
        \Praxigento\BonusBase\Repo\Dao\Calculation $daoCalc,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoBonDwnl,
        \Praxigento\BonusHybrid\Repo\Dao\Downline\Inactive $daoInact,
        \Praxigento\BonusHybrid\Repo\Query\GetInactive $qGetInact,
        \Praxigento\BonusHybrid\Api\Helper\Scheme $hlpScheme,
        \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent $servGetDepend

    )
    {
        $this->logger = $logger;
        $this->daoDwnlCust = $daoDwnlCust;
        $this->daoCalc = $daoCalc;
        $this->daoBonDwnl = $daoBonDwnl;
        $this->daoInact = $daoInact;
        $this->qGetInact = $qGetInact;
        $this->hlpScheme = $hlpScheme;
        $this->servGetDepend = $servGetDepend;
    }

    /**
     * @param EBonDwnl[] $tree
     * @param $prevStat
     * @return array
     * @throws \Exception
     */
    private function calc($tree, $prevStat)
    {
        $result = [];
        /* get qualification data */
        $forced = $this->hlpScheme->getForcedQualificationCustomersIds();
        $qLevels = $this->hlpScheme->getQualificationLevels();
        /* ... and customers to validate scheme (DEF/EU) */
        $customers = $this->getCustomers();
        foreach ($tree as $item) {
            $custId = $item->getCustomerRef();
            $custData = $customers[$custId];
            $scheme = $this->hlpScheme->getSchemeByCustomer($custData);
            $pv = $item->getPv();
            $level = $qLevels[$scheme];
            if ($pv < $level) {
                /* this customer is unqualified in this period */
                /* skip customers with forced qualification */
                if (in_array($custId, $forced)) continue;
                $treeEntryId = $item->getId();
                if (isset($prevStat[$custId])) {
                    $prevMonths = $prevStat[$custId];
                    $months = $prevMonths + 1;
                } else {
                    $months = 1;
                }
                $inactItem = new EInact();
                $inactItem->setTreeEntryRef($treeEntryId);
                $inactItem->setInactMonths($months);
                $result[] = $inactItem;
            }
        }
        return $result;
    }

    /**
     * Get customers data (id & country) to validate scheme (DEF/EU).
     *
     * @return EDwnlCust[]
     */
    private function getCustomers()
    {
        $result = [];
        $cols = [EDwnlCust::A_CUSTOMER_ID, EDwnlCust::A_COUNTRY_CODE];
        /** @var EDwnlCust[] $rs */
        $rs = $this->daoDwnlCust->get(null, null, null, null, $cols);
        foreach ($rs as $one) {
            $custId = $one->getCustomerId();
            $result[$custId] = $one;
        }
        return $result;
    }
    public function exec(\Praxigento\Core\Data $ctx)
    {
        $this->logger->info("Unqualified Stats Collection calculation is started.");
        /**
         * perform processing
         */
        $ctx->set(self::CTX_OUT_SUCCESS, false);
        /* get dependent calculation data */
        list($writeOffCalc, $writeOffCalcPrev, $collectCalc) = $this->getCalcData();
        $writeOffCalcId = $writeOffCalc->getId();
        $tree = $this->daoBonDwnl->getByCalcId($writeOffCalcId);
        $inactPrev = [];
        if ($writeOffCalcPrev) {
            $writeOffCalcIdPrev = $writeOffCalcPrev->getId();
            $inactPrev = $this->getPrevInactStats($writeOffCalcIdPrev);
        }
        $stats = $this->calc($tree, $inactPrev);
        $this->saveInactiveCurr($stats);
        /* mark this calculation complete */
        $calcId = $collectCalc->getId();
        $this->daoCalc->markComplete($calcId);
        /* mark process as successful */
        $ctx->set(self::CTX_OUT_SUCCESS, true);
        $this->logger->info("Unqualified Stats Collection calculation is completed.");
    }

    /**
     * Get related calculations data for this calculation.
     *
     * @return array [$writeOffCalc, $writeOffCalcPrev, $collectCalc]
     * @throws \Exception
     */
    private function getCalcData()
    {
        /* get period & calc data */
        $req = new \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Request();
        $req->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $req->setDepCalcTypeCode(Cfg::CODE_TYPE_CALC_UNQUALIFIED_COLLECT);
        $resp = $this->servGetDepend->exec($req);
        /** @var \Praxigento\BonusBase\Repo\Data\Period $writeOffPeriod */
        $writeOffPeriod = $resp->getBasePeriodData();
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $writeOffCalc */
        $writeOffCalc = $resp->getBaseCalcData();
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $collectCalc */
        $collectCalc = $resp->getDepCalcData();
        /**
         * Get previous write off period to access inactive stats history.
         */
        $periodPrev = $writeOffPeriod->getDstampBegin();
        $req = new \Praxigento\BonusBase\Api\Service\Period\Calc\Get\Dependent\Request();
        $req->setBaseCalcTypeCode(Cfg::CODE_TYPE_CALC_PV_WRITE_OFF);
        $req->setDepCalcTypeCode(Cfg::CODE_TYPE_CALC_UNQUALIFIED_COLLECT);
        $req->setPeriodEnd($periodPrev);
        $req->setDepIgnoreComplete(true);
        $resp = $this->servGetDepend->exec($req);
        /** @var \Praxigento\BonusBase\Repo\Data\Calculation $writeOffCalcPrev */
        $writeOffCalcPrev = $resp->getBaseCalcData();
        /**
         * Compose result.
         */
        $result = [$writeOffCalc, $writeOffCalcPrev, $collectCalc];
        return $result;
    }

    /**
     * @param int $calcId previous Write Off Calculation ID
     * @return array [custId=>months]
     */
    private function getPrevInactStats($calcId)
    {
        $result = [];
        $query = $this->qGetInact->build();
        $conn = $query->getConnection();
        $bind = [
            QGetInact::BND_CALC_ID => $calcId
        ];
        $rs = $conn->fetchAll($query, $bind);
        foreach ($rs as $one) {
            $custId = $one[QGetInact::A_CUST_REF];
            $months = $one[QGetInact::A_MONTHS];
            $result[$custId] = $months;
        }
        return $result;
    }

    /**
     * @param EInact[] $items
     */
    private function saveInactiveCurr($items)
    {
        foreach ($items as $item) {
            $this->daoInact->create($item);
        }
    }
}