<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus\Team\Calc;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Defaults as Def;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EDwnlBon;
use Praxigento\Downline\Repo\Entity\Data\Customer as ECustomer;
use Praxigento\BonusHybrid\Service\Calc\Data\Bonus as DBonus;
/**
 * Calculate Team bonus according to EU scheme.
 */
class EuScheme
{
    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\Traits\TMap {
        mapById as protected;
    }

    /** @var \Praxigento\Core\Tool\IFormat */
    private $hlpFormat;
    /** @var  \Praxigento\BonusHybrid\Tool\IScheme */
    private $hlpScheme;
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Praxigento\Downline\Repo\Entity\Customer */
    private $repoDwnl;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoDwnlBon;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\Core\Tool\IFormat $hlpFormat,
        \Praxigento\BonusHybrid\Tool\IScheme $hlpScheme,
        \Praxigento\Downline\Repo\Entity\Customer $repoDwnl,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoDwnlBon
    )
    {
        $this->logger = $logger;
        $this->hlpFormat = $hlpFormat;
        $this->hlpScheme = $hlpScheme;
        $this->repoDwnl = $repoDwnl;
        $this->repoDwnlBon = $repoDwnlBon;
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
        $bonusPercent = Def::TEAM_BONUS_EU_PERCENT;
        $dwnlCompress = $this->getBonusDwnl($calcId);
        $dwnlCurrent = $this->repoDwnl->get();
        /* create maps to access data */
        $mapDwnlById = $this->mapById($dwnlCompress, EDwnlBon::ATTR_CUST_REF);
        $mapCustById = $this->mapById($dwnlCurrent, ECustomer::ATTR_CUSTOMER_ID);
        /**
         * Go through all customers from compressed tree and calculate bonus.
         *
         * @var int $custId
         * @var EDwnlBon $custDwnl
         */
        foreach ($mapDwnlById as $custId => $custDwnl) {
            /** @var ECustomer $custData */
            $custData = $mapCustById[$custId];
            $custMlmId = $custData->getHumanRef();
            $pv = $custDwnl->getPv();
            $parentId = $custDwnl->getParentRef();
            /** @var EDwnlBon $parentDwnl */
            $parentDwnl = $mapDwnlById[$parentId];
            /** @var ECustomer $parentData */
            $parentData = $mapCustById[$parentId];
            $parentMlmId = $parentData->getHumanRef();
            $scheme = $this->hlpScheme->getSchemeByCustomer($parentData);
            if ($scheme == Def::SCHEMA_EU) {
                $pvParent = $parentDwnl->getPv();
                if ($pvParent > (Def::PV_QUALIFICATION_LEVEL_EU - Cfg::DEF_ZERO)) {
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

    /**
     * Get compressed downline for base calculation from Bonus module.
     *
     * @param int $calcId
     * @return \Praxigento\BonusHybrid\Repo\Entity\Data\Downline[]
     */
    private function getBonusDwnl($calcId)
    {
        $where = EDwnlBon::ATTR_CALC_REF . '=' . (int)$calcId;
        $result = $this->repoDwnlBon->get($where);
        return $result;
    }
}