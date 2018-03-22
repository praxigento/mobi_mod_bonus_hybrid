<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus\Team;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EBonDwnl;
use Praxigento\BonusHybrid\Service\Calc\A\Data\Bonus as DBonus;
use Praxigento\Downline\Repo\Entity\Data\Customer as ECustomer;

/**
 * Calculate Team bonus according to EU scheme.
 */
class CalcEu
{
    /** @var \Praxigento\Downline\Helper\Tree */
    private $hlpDwnlTree;
    /** @var \Praxigento\Core\Api\Helper\Format */
    private $hlpFormat;
    /** @var  \Praxigento\BonusHybrid\Helper\IScheme */
    private $hlpScheme;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoBonDwnl;
    /** @var \Praxigento\Downline\Repo\Entity\Customer */
    private $repoDwnl;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Core\Api\Helper\Format $hlpFormat,
        \Praxigento\BonusHybrid\Helper\IScheme $hlpScheme,
        \Praxigento\Downline\Helper\Tree $hlpDwnlTree,
        \Praxigento\Downline\Repo\Entity\Customer $repoDwnl,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoBonDwnl
    )
    {
        $this->logger = $logger;
        $this->hlpFormat = $hlpFormat;
        $this->hlpScheme = $hlpScheme;
        $this->hlpDwnlTree = $hlpDwnlTree;
        $this->repoDwnl = $repoDwnl;
        $this->repoBonDwnl = $repoBonDwnl;
    }

    /**
     * Walk trough the compressed downline & calculate team bonus for EU scheme.
     *
     * @param int $calcId ID of the compression calculation to get downline.
     * @return Data[]
     */
    public function exec($calcId)
    {
        $result = [];
        /* collect additional data */
        $bonusPercent = Cfg::TEAM_BONUS_EU_PERCENT;
        $dwnlCompress = $this->repoBonDwnl->getByCalcId($calcId);
        $dwnlCurrent = $this->repoDwnl->get();
        /* create maps to access data */
        $mapDwnlById = $this->hlpDwnlTree->mapById($dwnlCompress, EBonDwnl::ATTR_CUST_REF);
        $mapCustById = $this->hlpDwnlTree->mapById($dwnlCurrent, ECustomer::ATTR_CUSTOMER_ID);
        /**
         * Go through all customers from compressed tree and calculate bonus.
         *
         * @var int $custId
         * @var EBonDwnl $custDwnl
         */
        foreach ($mapDwnlById as $custId => $custDwnl) {
            /** @var ECustomer $custData */
            $custData = $mapCustById[$custId];
            $custMlmId = $custData->getMlmId();
            $pv = $custDwnl->getPv();
            $parentId = $custDwnl->getParentRef();
            /** @var EBonDwnl $parentDwnl */
            $parentDwnl = $mapDwnlById[$parentId];
            /** @var ECustomer $parentData */
            $parentData = $mapCustById[$parentId];
            $parentMlmId = $parentData->getMlmId();
            $scheme = $this->hlpScheme->getSchemeByCustomer($parentData);
            if ($scheme == Cfg::SCHEMA_EU) {
                $pvParent = $parentDwnl->getPv();
                if ($pvParent > (Cfg::PV_QUALIFICATION_LEVEL_EU - Cfg::DEF_ZERO)) {
                    $bonus = $this->hlpFormat->roundBonus($pv * $bonusPercent);
                    if ($bonus > Cfg::DEF_ZERO) {
                        $entry = new DBonus();
                        $entry->setCustomerRef($parentId);
                        $entry->setDonatorRef($custId);
                        $entry->setValue($bonus);
                        $result[] = $entry;
                    }
                    $this->logger->debug("parent #$parentId (ref. #$parentMlmId) has '$bonus' as EU Team Bonus from downline customer #$custId (ref. #$custMlmId ).");
                } else {
                    $this->logger->debug("parent #$parentId (ref. #$parentMlmId) does not qualified t oget EU Team Bonus from downline customer #$custId (ref. #$custMlmId ).");
                }
            } else {
                $this->logger->debug("Parent #$parentId (ref. #$parentMlmId) has incompatible scheme '$scheme' for EU Team Bonus.");
            }
        }
        unset($mapCustById);
        unset($mapDwnlById);
        return $result;
    }

}