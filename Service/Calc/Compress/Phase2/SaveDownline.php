<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 17.6.11
 * Time: 17:23
 */

namespace Praxigento\BonusHybrid\Service\Calc\Compress\Phase2;

use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;
use Praxigento\Downline\Repo\Data\Customer as EDwnlCust;

/**
 * Save compressed downline and related data (ranks, etc.).
 *
 * (subroutine for ..\Phase2 - exec() has a list of params)
 */
class SaveDownline
{
    /** @var \Praxigento\Downline\Api\Helper\Tree */
    private $hlpDwnl;
    /** @var \Praxigento\BonusHybrid\Api\Helper\Scheme */
    private $hlpScheme;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $daoBonDwnl;
    /** @var \Praxigento\Downline\Repo\Dao\Customer */
    private $daoDwnlCust;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Compression\Phase2\Legs */
    private $daoLegs;

    public function __construct(
        \Praxigento\Downline\Api\Helper\Tree $hlpDwnl,
        \Praxigento\BonusHybrid\Api\Helper\Scheme $hlpScheme,
        \Praxigento\Downline\Repo\Dao\Customer $daoDwnlCust,
        \Praxigento\BonusHybrid\Repo\Dao\Compression\Phase2\Legs $daoLegs,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoBonDwnl
    )
    {
        $this->hlpDwnl = $hlpDwnl;
        $this->hlpScheme = $hlpScheme;
        $this->daoDwnlCust = $daoDwnlCust;
        $this->daoLegs = $daoLegs;
        $this->daoBonDwnl = $daoBonDwnl;
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
        $tree = $this->daoBonDwnl->getByCalcId($calcId);
        $result = $this->hlpDwnl->mapById($tree, EBonDwnl::A_CUST_REF);
        return $result;
    }

    private function getCustomersById()
    {
        $customers = $this->daoDwnlCust->get();
        $result = $this->hlpDwnl->mapById($customers, EDwnlCust::A_CUSTOMER_REF);
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
        /** @var \Praxigento\BonusHybrid\Repo\Data\Downline $entry */
        foreach ($entries as $entry) {
            $custId = $entry->getCustomerRef();
            /* create downline entry */
            $this->daoBonDwnl->create($entry);
            $rankId = $entry->getRankRef();
            /* check customer scheme */
            $customer = $custById[$custId];
            $custScheme = $this->hlpScheme->getSchemeByCustomer($customer);
            if ($custScheme == $scheme) {
                /* update rank in downlines (plain & compressed) */
                $dwnlData = [EBonDwnl::A_RANK_REF => $rankId];
                $byCust = EBonDwnl::A_CUST_REF . '=' . (int)$custId;
                $byPlain = EBonDwnl::A_CALC_REF . '=' . (int)$plainCalcId;
                $byCmprs = EBonDwnl::A_CALC_REF . '=' . (int)$cmprsCalcId;
                $where = "($byCust) AND (($byPlain) OR ($byCmprs))";
                $this->daoBonDwnl->update($dwnlData, $where);
            }
        }
    }

    private function saveLegs($entries)
    {
        foreach ($entries as $entry) {
            $this->daoLegs->create($entry);
        }
    }
}