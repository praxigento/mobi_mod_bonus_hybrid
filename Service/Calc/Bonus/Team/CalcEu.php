<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus\Team;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;
use Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Helper\Data\Bonus as DBonus;
use Praxigento\Downline\Repo\Data\Customer as ECustomer;

/**
 * Calculate Team bonus according to EU scheme.
 */
class CalcEu
{
    /** @var \Praxigento\Downline\Helper\Tree */
    private $hlpDwnlTree;
    /** @var \Praxigento\Core\Api\Helper\Format */
    private $hlpFormat;
    /** @var  \Praxigento\BonusHybrid\Api\Helper\Scheme */
    private $hlpScheme;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $daoBonDwnl;
    /** @var \Praxigento\Downline\Repo\Dao\Customer */
    private $daoDwnl;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Core\Api\Helper\Format $hlpFormat,
        \Praxigento\BonusHybrid\Api\Helper\Scheme $hlpScheme,
        \Praxigento\Downline\Helper\Tree $hlpDwnlTree,
        \Praxigento\Downline\Repo\Dao\Customer $daoDwnl,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoBonDwnl
    )
    {
        $this->logger = $logger;
        $this->hlpFormat = $hlpFormat;
        $this->hlpScheme = $hlpScheme;
        $this->hlpDwnlTree = $hlpDwnlTree;
        $this->daoDwnl = $daoDwnl;
        $this->daoBonDwnl = $daoBonDwnl;
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
        $dwnlCompress = $this->daoBonDwnl->getByCalcId($calcId);
        $dwnlCurrent = $this->daoDwnl->get();
        /* create maps to access data */
        $mapDwnlById = $this->hlpDwnlTree->mapById($dwnlCompress, EBonDwnl::A_CUST_REF);
        $mapCustById = $this->hlpDwnlTree->mapById($dwnlCurrent, ECustomer::A_CUSTOMER_ID);
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