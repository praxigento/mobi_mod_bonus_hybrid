<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast\Compress;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;
use Praxigento\Downline\Repo\Data\Customer as ECustDwnl;

/**
 * Update Phase 1 downline with ranks from Phase2 trees (DEF & EU). This is in-memory update (not in-DB).
 */
class UpdateDwnl
    implements \Praxigento\Core\App\Service\IProcess
{
    /**  \Praxigento\BonusHybrid\Repo\Data\Downline[] */
    const IN_DWNL_PHASE1 = 'dwnlPhase1';
    /** \Praxigento\BonusHybrid\Repo\Data\Downline[] */
    const IN_DWNL_PHASE2_DEF = 'dwnlPhase2Def';
    /** \Praxigento\BonusHybrid\Repo\Data\Downline[] */
    const IN_DWNL_PHASE2_EU = 'dwnlPhase2Eu';
    /** \Praxigento\BonusHybrid\Repo\Data\Downline[] */
    const OUT_DWNL_PHASE1 = 'dwnlPhase1';
    /** @var \Praxigento\Downline\Helper\Tree */
    private $hlpDwnlTree;
    /** @var \Praxigento\BonusHybrid\Api\Helper\Scheme */
    private $hlpScheme;
    /** @var \Praxigento\Downline\Repo\Dao\Customer */
    private $daoCustDwnl;

    public function __construct(
        \Praxigento\BonusHybrid\Api\Helper\Scheme $hlpScheme,
        \Praxigento\Downline\Helper\Tree $hlpDwnlTree,
        \Praxigento\Downline\Repo\Dao\Customer $daoCustDwnl
    )
    {
        $this->hlpScheme = $hlpScheme;
        $this->hlpDwnlTree = $hlpDwnlTree;
        $this->daoCustDwnl = $daoCustDwnl;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /* get working data from input */
        $dwnlPhase1 = $ctx->get(self::IN_DWNL_PHASE1);
        $dwnlPhase2Def = $ctx->get(self::IN_DWNL_PHASE2_DEF);
        $dwnlPhase2Eu = $ctx->get(self::IN_DWNL_PHASE2_EU);

        /* define local working data */
        $dwnlCust = $this->daoCustDwnl->get();
        $mapCust = $this->hlpDwnlTree->mapById($dwnlCust, ECustDwnl::A_CUSTOMER_ID);
        $mapByIdDef = $this->hlpDwnlTree->mapById($dwnlPhase2Def, EBonDwnl::A_CUST_REF);
        $mapByIdEu = $this->hlpDwnlTree->mapById($dwnlPhase2Eu, EBonDwnl::A_CUST_REF);

        /* prepare output vars */
        $outUpdated = [];


        /**
         * perform processing
         */
        /** @var EBonDwnl $item */
        foreach ($dwnlPhase1 as $item) {
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
            }
            $outUpdated[$custRef] = $item;
        }

        /* put result data into output */
        $result = new \Praxigento\Core\Data();
        $result->set(self::OUT_DWNL_PHASE1, $outUpdated);
        return $result;
    }

}