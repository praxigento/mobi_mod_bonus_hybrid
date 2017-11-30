<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus\Override;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Cfg\Override as ECfgOvrd;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EBonDwnl;
use Praxigento\BonusHybrid\Service\Calc\A\Data\Bonus as DBonus;
use Praxigento\BonusHybrid\Service\Calc\Bonus\Override\Calc\Entry as DEntry;
use Praxigento\Downline\Repo\Entity\Data\Customer as ECustomer;

class Calc
{
    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\A\Traits\TMap {
        mapById as private;
        mapByTeams as private;
        mapByTreeDepthDesc as private;
    }

    /** @var \Praxigento\Downline\Tool\ITree */
    private $hlpDwnl;
    /** @var \Praxigento\Core\Tool\IFormat */
    private $hlpFormat;
    /** @var  \Praxigento\BonusHybrid\Helper\IScheme */
    private $hlpScheme;
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Cfg\Override */
    private $repoCfgOvrd;
    /** @var \Praxigento\Downline\Repo\Entity\Customer */
    private $repoDwnl;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoBonDwnl;
    /** @var \Praxigento\BonusBase\Repo\Entity\Rank */
    private $repoRank;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\Core\Tool\IFormat $hlpFormat,
        \Praxigento\Downline\Tool\ITree $hlpDwnl,
        \Praxigento\BonusHybrid\Helper\IScheme $hlpScheme,
        \Praxigento\Downline\Repo\Entity\Customer $repoDwnl,
        \Praxigento\BonusBase\Repo\Entity\Rank $repoRank,
        \Praxigento\BonusHybrid\Repo\Entity\Cfg\Override $repoCfgOvrd,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoBonDwnl
    )
    {
        $this->logger = $logger;
        $this->hlpFormat = $hlpFormat;
        $this->hlpDwnl = $hlpDwnl;
        $this->hlpScheme = $hlpScheme;
        $this->repoDwnl = $repoDwnl;
        $this->repoRank = $repoRank;
        $this->repoCfgOvrd = $repoCfgOvrd;
        $this->repoBonDwnl = $repoBonDwnl;
    }

    /**
     * @param $custId int Customer ID
     * @param $cfgOvr array override bonus configuration parameters for the customer
     * @param $mapGen array generations mapping
     * @param $mapById array customer data by ID mapping
     *
     * @return number
     */
    private function calcOverrideBonusByRank($custId, $cfgOvr, $mapGen, $mapById)
    {
        $result = [];
        if (isset($mapGen[$custId])) {
            $generations = $mapGen[$custId];
            /* this customer has generations in downline */
            /**
             * @var int $gen
             * @var ECfgOvrd $cfgData
             */
            foreach ($cfgOvr as $gen => $cfgData) {
                $percent = $cfgData->getPercent();
                if ($percent > 0) {
                    if (isset($generations[$gen])) {
                        /* this generation exists for the customer */
                        $team = $mapGen[$custId][$gen];
                        foreach ($team as $childId) {
                            /** @var EBonDwnl $childData */
                            $childData = $mapById[$childId];
                            $pv = $childData->getPv();
                            $bonus = $this->hlpFormat->roundBonus($pv * $percent);
                            $this->logger->debug("Customer #$custId has '$pv' PV for '$gen' generation and '$bonus' as override bonus part from child #$childId .");
                            $resultEntry = new DBonus();
                            $resultEntry->setCustomerRef($custId);
                            $resultEntry->setDonatorRef($childId);
                            $resultEntry->setValue($bonus);
                            $result[] = $resultEntry;
                        }
                    }
                }
            }
        }
        return $result;
    }

    public function exec($compressCalcId, $scheme)
    {
        $result = [];
        /* collect additional data */
        $dwnlCompress = $this->repoBonDwnl->getByCalcId($compressCalcId);
        $dwnlPlain = $this->repoDwnl->get();
        $cfgOverride = $this->getCfgOverride();
        /* create maps to access data */
        $mapCmprsById = $this->mapById($dwnlCompress, EBonDwnl::ATTR_CUST_REF);
        $mapPlainById = $this->mapById($dwnlPlain, ECustomer::ATTR_CUSTOMER_ID);
        $mapTeams = $this->mapByTeams($dwnlCompress, EBonDwnl::ATTR_CUST_REF, EBonDwnl::ATTR_PARENT_REF);
        /* populate compressed data with depth & path values */
        $mapByDepthDesc = $this->mapByTreeDepthDesc($dwnlCompress, EBonDwnl::ATTR_CUST_REF, EBonDwnl::ATTR_DEPTH);
        /* scan all levels starting from the bottom and collect PV by generations */
        $mapGenerations = $this->mapByGeneration($mapByDepthDesc,
            $mapCmprsById); // [ $custId=>[$genId => $totalPv, ...], ... ]
        $defRankId = $this->repoRank->getIdByCode(Cfg::RANK_DISTRIBUTOR);
        /* scan all customers and calculate bonus values */
        /** @var EBonDwnl $custCompress */
        foreach ($dwnlCompress as $custCompress) {
            $custId = $custCompress->getCustomerRef();
            $rankId = $custCompress->getRankRef();
            /** @var ECustomer $custPlain */
            $custPlain = $mapPlainById[$custId];
            $custRef = $custPlain->getHumanRef();
            $custScheme = $this->hlpScheme->getSchemeByCustomer($custPlain);
            if (
                ($rankId != $defRankId) &&
                ($custScheme == $scheme)
            ) {
                /* this is qualified manager */
                $this->logger->debug("Customer #$custId (#$custRef ) from scheme '$custScheme' is qualified to rank #$rankId.");
                if (isset($cfgOverride[$scheme][$rankId])) {
                    $cfgOvrEntry = $cfgOverride[$scheme][$rankId];
                    // calculate bonus value for $custId according rank configuration
                    $bonusData = $this->calcOverrideBonusByRank($custId, $cfgOvrEntry, $mapGenerations, $mapCmprsById);
                    /* ... and add to result set */
                    $entry = new DEntry();
                    $entry->setCustomerRef($custId);
                    $entry->setRankRef($rankId);
                    $entry->setEntries($bonusData);
                    $result[] = $entry;
                } else {
                    /* this rank is not qualified to the bonus */
                }
            }
        }
        unset($mapGenerations);
        unset($mapByDepthDesc);
        unset($mapTreeExp);
        unset($mapTeams);
        unset($mapCmprsById);
        /* convert 2D array with results into plain array */
        $result = $this->plainBonus($result);
        return $result;
    }

    /**
     * @return array [$scheme][$rankId][$gen] => $cfg;
     */
    private function getCfgOverride()
    {
        $result = [];
        $data = $this->repoCfgOvrd->get();
        /** @var ECfgOvrd $one */
        foreach ($data as $one) {
            $scheme = $one->getScheme();
            $rankId = $one->getRankId();
            $gen = $one->getGeneration();
            $result[$scheme][$rankId][$gen] = $one;
        }
        return $result;
    }

    /**
     * Generate map of the customer generations.
     *
     * @param $mapByDepthDesc
     * @param $mapById
     * @param $mapById
     *
     * @return array [$custId=>[$genNum=>[$childId, ...], ...], ...]
     */
    private function mapByGeneration($mapByDepthDesc, $mapById)
    {
        $result = []; // [ $custId=>[$genId => $totalPv, ...], ... ]
        foreach ($mapByDepthDesc as $depth => $ids) {
            foreach ($ids as $custId) {
                /** @var EBonDwnl $entry */
                $entry = $mapById[$custId];
                $path = $entry->getPath();
                $parents = $this->hlpDwnl->getParentsFromPathReversed($path);
                $level = 0;
                foreach ($parents as $parentId) {
                    $level += 1;
                    if (!isset($result[$parentId])) {
                        $result[$parentId] = [];
                    }
                    if (!isset($result[$parentId][$level])) {
                        $result[$parentId][$level] = [];
                    }
                    $result[$parentId][$level][] = $custId;
                }
            }
        }
        return $result;
    }

    /**
     * Convert 2D array with bonuses into 1D array.
     *
     * @param $bonus
     * @return array
     */
    private function plainBonus($bonus)
    {
        /* prepare data for updates */
        $result = [];
        /** @var DEntry $item */
        foreach ($bonus as $item) {
            $bonusData = $item->getEntries();
            /** @var DBonus $entry */
            foreach ($bonusData as $entry) {
                $bonus = $entry->getValue();
                if ($bonus > Cfg::DEF_ZERO) {
                    $result[] = $entry;
                }
            }
        }
        return $result;
    }

}