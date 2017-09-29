<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Forecast\Compress;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EBonDwnl;
use Praxigento\Downline\Repo\Entity\Data\Customer as ECustDwnl;

/**
 * Update Phase 1 downline with ranks from Phase2 trees (DEF & EU). This is in-memory update (not in-DB).
 */
class UpdateDwnl
    implements \Praxigento\Core\Service\IProcess
{
    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\A\Traits\TMap {
        mapById as protected;
    }

    /**  \Praxigento\BonusHybrid\Repo\Entity\Data\Downline[] */
    const IN_DWNL_PHASE1 = 'dwnlPhase1';
    /** \Praxigento\BonusHybrid\Repo\Entity\Data\Downline[] */
    const IN_DWNL_PHASE2_DEF = 'dwnlPhase2Def';
    /** \Praxigento\BonusHybrid\Repo\Entity\Data\Downline[] */
    const IN_DWNL_PHASE2_EU = 'dwnlPhase2Eu';
    /** \Praxigento\BonusHybrid\Repo\Entity\Data\Downline[] */
    const OUT_DWNL_PHASE1 = 'dwnlPhase1';
    /** @var \Praxigento\BonusHybrid\Helper\IScheme */
    private $hlpScheme;
    /** @var \Praxigento\Downline\Repo\Entity\Customer */
    private $repoCustDwnl;

    public function __construct(
        \Praxigento\BonusHybrid\Helper\IScheme $hlpScheme,
        \Praxigento\Downline\Repo\Entity\Customer $repoCustDwnl
    )
    {
        $this->hlpScheme = $hlpScheme;
        $this->repoCustDwnl = $repoCustDwnl;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /* get working data from input */
        $dwnlPhase1 = $ctx->get(self::IN_DWNL_PHASE1);
        $dwnlPhase2Def = $ctx->get(self::IN_DWNL_PHASE2_DEF);
        $dwnlPhase2Eu = $ctx->get(self::IN_DWNL_PHASE2_EU);

        /* define local working data */
        $dwnlCust = $this->repoCustDwnl->get();
        $mapCust = $this->mapById($dwnlCust, ECustDwnl::ATTR_CUSTOMER_ID);
        $mapByIdDef = $this->mapById($dwnlPhase2Def, EBonDwnl::ATTR_CUST_REF);
        $mapByIdEu = $this->mapById($dwnlPhase2Eu, EBonDwnl::ATTR_CUST_REF);

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