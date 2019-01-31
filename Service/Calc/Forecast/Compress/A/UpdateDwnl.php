<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast\Compress\A;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;
use Praxigento\Downline\Repo\Data\Customer as ECustDwnl;

/**
 * Update Phase 1 downline with ranks from Phase2 trees (DEF & EU). This is in-memory update (not in-DB).
 */
class UpdateDwnl
{
    /**  \Praxigento\BonusHybrid\Repo\Data\Downline[] */
    const IN_DWNL_PHASE1 = 'dwnlPhase1';
    /** \Praxigento\BonusHybrid\Repo\Data\Downline[] */
    const IN_DWNL_PHASE2_DEF = 'dwnlPhase2Def';
    /** \Praxigento\BonusHybrid\Repo\Data\Downline[] */
    const IN_DWNL_PHASE2_EU = 'dwnlPhase2Eu';
    /** \Praxigento\BonusHybrid\Repo\Data\Downline[] */
    const OUT_DWNL_PHASE1 = 'dwnlPhase1';
    /** @var \Praxigento\Downline\Repo\Dao\Customer */
    private $daoCustDwnl;
    /** @var \Praxigento\Downline\Api\Helper\Tree */
    private $hlpDwnlTree;
    /** @var \Praxigento\BonusHybrid\Api\Helper\Scheme */
    private $hlpScheme;

    public function __construct(
        \Praxigento\BonusHybrid\Api\Helper\Scheme $hlpScheme,
        \Praxigento\Downline\Api\Helper\Tree $hlpDwnlTree,
        \Praxigento\Downline\Repo\Dao\Customer $daoCustDwnl
    ) {
        $this->hlpScheme = $hlpScheme;
        $this->hlpDwnlTree = $hlpDwnlTree;
        $this->daoCustDwnl = $daoCustDwnl;
    }

    public function exec($treePlain, $treeCmprsDef, $treeCmprsEu)
    {
        /* define local working data */
        $dwnlCust = $this->daoCustDwnl->get();
        $mapCust = $this->hlpDwnlTree->mapById($dwnlCust, ECustDwnl::A_CUSTOMER_REF);
        $mapByIdDef = $this->hlpDwnlTree->mapById($treeCmprsDef, EBonDwnl::A_CUST_REF);
        $mapByIdEu = $this->hlpDwnlTree->mapById($treeCmprsEu, EBonDwnl::A_CUST_REF);

        /* prepare output vars */
        $outUpdated = [];


        /**
         * perform processing
         */
        /** @var EBonDwnl $item */
        foreach ($treePlain as $item) {
            $custRef = $item->getCustomerRef();
            $custData = $mapCust[$custRef];
            $scheme = $this->hlpScheme->getSchemeByCustomer($custData);
            /** @var EBonDwnl $ph2Item */
            if ($scheme == Cfg::SCHEMA_EU) {
                $ph2Item = $mapByIdEu[$custRef] ?? null;
            } else {
                $ph2Item = $mapByIdDef[$custRef] ?? null;
            }
            if ($ph2Item) {
                $rankId = $ph2Item->getRankRef();
                $item->setRankRef($rankId);
                $item->setUnqMonths(0);
            }
            $outUpdated[$custRef] = $item;
        }

        /* put result data into output */
        $result = new \Praxigento\Core\Data();
        $result->set(self::OUT_DWNL_PHASE1, $outUpdated);
        return $outUpdated;
    }

}