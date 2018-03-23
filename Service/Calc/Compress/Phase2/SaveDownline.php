<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 17.6.11
 * Time: 17:23
 */

namespace Praxigento\BonusHybrid\Service\Calc\Compress\Phase2;

use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;
use Praxigento\BonusHybrid\Repo\Data\Downline\Qualification as EBonDwnQual;
use Praxigento\Downline\Repo\Data\Customer as EDwnlCust;

/**
 * Save compressed downline and related data (ranks, etc.).
 *
 * (subroutine for ..\Phase2 - exec() has a list of params)
 */
class SaveDownline
{
    /** @var \Praxigento\Downline\Helper\Tree */
    private $hlpDwnl;
    /** @var \Praxigento\BonusHybrid\Helper\IScheme */
    private $hlpScheme;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $repoBonDwnl;
    /** @var \Praxigento\Downline\Repo\Dao\Customer */
    private $repoDwnlCust;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline\Qualification */
    private $repoDwnlQual;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Compression\Phase2\Legs */
    private $repoLegs;

    public function __construct(
        \Praxigento\Downline\Helper\Tree $hlpDwnl,
        \Praxigento\BonusHybrid\Helper\IScheme $hlpScheme,
        \Praxigento\Downline\Repo\Dao\Customer $repoDwnlCust,
        \Praxigento\BonusHybrid\Repo\Dao\Compression\Phase2\Legs $repoLegs,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $repoBonDwnl,
        \Praxigento\BonusHybrid\Repo\Dao\Downline\Qualification $repoDwnlQual
    )
    {
        $this->hlpDwnl = $hlpDwnl;
        $this->hlpScheme = $hlpScheme;
        $this->repoDwnlCust = $repoDwnlCust;
        $this->repoLegs = $repoLegs;
        $this->repoBonDwnl = $repoBonDwnl;
        $this->repoDwnlQual = $repoDwnlQual;
    }

    /**
     * @param \Praxigento\BonusHybrid\Repo\Data\Downline[] $downline
     * @param $legs
     * @param $calcIdWriteOff
     * @param $phase1CalcId
     * @param $scheme
     * @throws \Exception
     */
    public function exec($downline, $legs, $calcIdWriteOff, $phase1CalcId, $scheme)
    {
        $this->saveDownline($downline, $calcIdWriteOff, $phase1CalcId, $scheme);
        $this->saveLegs($legs);
    }

    /**
     * @param int $calcId
     * @return EBonDwnl[]
     */
    private function getBonTreeByCustId($calcId)
    {
        $tree = $this->repoBonDwnl->getByCalcId($calcId);
        $result = $this->hlpDwnl->mapById($tree, EBonDwnl::A_CUST_REF);
        return $result;
    }

    private function getCustomersById()
    {
        $customers = $this->repoDwnlCust->get();
        $result = $this->hlpDwnl->mapById($customers, EDwnlCust::A_CUSTOMER_ID);
        return $result;
    }

    /**
     * @param \Praxigento\BonusHybrid\Repo\Data\Downline[] $entries
     * @param int $plainCalcId
     * @param int $cmprsCalcId
     * @param string $scheme
     * @throws \Exception
     */
    private function saveDownline($entries, $plainCalcId, $cmprsCalcId, $scheme)
    {
        $custById = $this->getCustomersById();
        $plainByCust = $this->getBonTreeByCustId($plainCalcId);
        /** @var \Praxigento\BonusHybrid\Repo\Data\Downline $entry */
        foreach ($entries as $entry) {
            $custId = $entry->getCustomerRef();
            /* create downline entry */
            $this->repoBonDwnl->create($entry);
            $rankId = $entry->getRankRef();
            /* check customer scheme */
            $customer = $custById[$custId];
            $custScheme = $this->hlpScheme->getSchemeByCustomer($customer);
            if ($custScheme == $scheme) {
                /* get plain entry ID */
                $plainEntry = $plainByCust[$custId];
                $plainId = $plainEntry->getId();
                /* create qualification entry */
                $qual = new EBonDwnQual();
                $qual->setTreeEntryRef($plainId);
                $qual->setRankRef($rankId);
                $this->repoDwnlQual->create($qual);
                /* update rank in downlines (plain & compressed) */
                $dwnlData = [EBonDwnl::A_RANK_REF => $rankId];
                $byCust = EBonDwnl::A_CUST_REF . '=' . (int)$custId;
                $byPlain = EBonDwnl::A_CALC_REF . '=' . (int)$plainCalcId;
                $byCmprs = EBonDwnl::A_CALC_REF . '=' . (int)$cmprsCalcId;
                $where = "($byCust) AND (($byPlain) OR ($byCmprs))";
                $this->repoBonDwnl->update($dwnlData, $where);
            }
        }
    }

    private function saveLegs($entries)
    {
        foreach ($entries as $entry) {
            $this->repoLegs->create($entry);
        }
    }
}