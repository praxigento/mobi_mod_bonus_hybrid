<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus\Courtesy;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Defaults as Def;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EDwnlBon;
use Praxigento\Downline\Repo\Entity\Data\Customer as ECustomer;
use Praxigento\BonusHybrid\Service\Calc\Data\Bonus as DBonus;
class Calc
{
    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\Traits\TMap {
        mapById as protected;
        mapByTeams as protected;
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
    /** @var \Praxigento\BonusBase\Repo\Entity\Level */
    private $repoLevel;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\Core\Tool\IFormat $hlpFormat,
        \Praxigento\BonusHybrid\Tool\IScheme $hlpScheme,
        \Praxigento\BonusBase\Repo\Entity\Level $repoLevel,
        \Praxigento\Downline\Repo\Entity\Customer $repoDwnl,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoDwnlBon
    )
    {
        $this->logger = $logger;
        $this->hlpFormat = $hlpFormat;
        $this->hlpScheme = $hlpScheme;
        $this->repoLevel = $repoLevel;
        $this->repoDwnl = $repoDwnl;
        $this->repoDwnlBon = $repoDwnlBon;
    }

    public function exec($calcId)
    {
        $result = [];
        /* collect additional data */
        $percentCourtesy = Def::COURTESY_BONUS_PERCENT;
        $dwnlCompress = $this->getBonusDwnl($calcId);
        $dwnlCurrent = $this->repoDwnl->get();
        $levelsPersonal = $this->repoLevel->getByCalcTypeCode(Cfg::CODE_TYPE_CALC_BONUS_PERSONAL_DEF);
        $levelsTeam = $this->repoLevel->getByCalcTypeCode(Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF);
        /* create maps to access data */
        $mapDataById = $this->mapById($dwnlCompress, EDwnlBon::ATTR_CUST_REF);
        $mapTeams = $this->mapByTeams($dwnlCompress, EDwnlBon::ATTR_CUST_REF, EDwnlBon::ATTR_PARENT_REF);
        $mapCustById = $this->mapById($dwnlCurrent, ECustomer::ATTR_CUSTOMER_ID);
        /**
         * Go through all customers from compressed tree and calculate bonus.
         *
         * @var EDwnlBon $item
         */
        foreach ($dwnlCompress as $item) {
            $custId = $item->getCustomerRef();
            /** @var ECustomer $custData */
            $custData = $mapCustById[$custId];
            $custScheme = $this->hlpScheme->getSchemeByCustomer($custData);
            if (
                isset($mapTeams[$custId]) &&
                ($custScheme == Def::SCHEMA_DEFAULT)
            ) {
                $custMlmId = $custData->getHumanRef();
                $tv = $item->getTv();
                $tv = $this->hlpScheme->getForcedTv($custId, $custScheme, $tv);
                $percentTeam = $this->getLevelPercent($tv, $levelsTeam);
                $this->logger->debug("Customer #$custId ($custMlmId) has $tv TV and $percentTeam% as max percent.");
                /* for all front team members of the customer */
                $team = $mapTeams[$custId];
                foreach ($team as $memberId) {
                    /** @var EDwnlBon $memberCompress */
                    $memberCompress = $mapDataById[$memberId];
                    $pv = $memberCompress->getPv();
                    if ($pv > 0) {
                        /** @var ECustomer $memberData */
                        $memberData = $mapCustById[$memberId];
                        $memberMlmId = $memberData->getHumanRef();
                        $percentPv = $this->getLevelPercent($pv, $levelsPersonal);
                        $percentDelta = $percentTeam - $percentPv;
                        if ($percentDelta > Cfg::DEF_ZERO) {
                            $this->logger->debug("Member $memberId ($memberMlmId) has $pv PV, percent: $percentPv%, delta: $percentDelta% and does not give bonus part to customer #$custId ($custMlmId).");
                        } else {
                            $bonusPart = $this->hlpFormat->roundBonus($pv * $percentCourtesy);
                            /* add new bonus entry */
                            $entry = new DBonus();
                            $entry->setCustomerRef($custId);
                            $entry->setDonatorRef($memberId);
                            $entry->setValue($bonusPart);
                            $result[] = $entry;
                            $this->logger->debug("$bonusPart is a Courtesy Bonus part for customer #$custId ($custMlmId) from front member #$memberId ($memberMlmId) - pv: $pv, percent: $percentPv%, delta: $percentDelta%.");
                        }
                    }
                }
            }
        }
        /* clear working data and return result */
        unset($mapDataById);
        unset($mapTeams);
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


    /**
     * Get percent for the first level that is greater then given $value.
     *
     * @param int $value PV/TV/... value to get level's percent
     * @param array $levels asc ordered array with levels & percents ([$level => $percent])
     *
     * @return number
     */
    private function getLevelPercent($value, $levels)
    {
        $result = 0;
        foreach ($levels as $level => $percent) {
            if ($value < $level) {
                break;
            }
            $result = $percent;
        }
        return $result;
    }

}