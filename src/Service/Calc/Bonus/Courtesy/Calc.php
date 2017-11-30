<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus\Courtesy;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EBonDwnl;
use Praxigento\BonusHybrid\Service\Calc\A\Data\Bonus as DBonus;
use Praxigento\Downline\Repo\Entity\Data\Customer as ECustomer;

class Calc
{
    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\A\Traits\TMap {
        mapById as protected;
        mapByTeams as protected;
    }

    /** @var \Praxigento\Core\Tool\IFormat */
    private $hlpFormat;
    /** @var  \Praxigento\BonusHybrid\Helper\IScheme */
    private $hlpScheme;
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Praxigento\Downline\Repo\Entity\Customer */
    private $repoDwnl;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoBonDwnl;
    /** @var \Praxigento\BonusBase\Repo\Entity\Level */
    private $repoLevel;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\Core\Tool\IFormat $hlpFormat,
        \Praxigento\BonusHybrid\Helper\IScheme $hlpScheme,
        \Praxigento\BonusBase\Repo\Entity\Level $repoLevel,
        \Praxigento\Downline\Repo\Entity\Customer $repoDwnl,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoBonDwnl
    )
    {
        $this->logger = $logger;
        $this->hlpFormat = $hlpFormat;
        $this->hlpScheme = $hlpScheme;
        $this->repoLevel = $repoLevel;
        $this->repoDwnl = $repoDwnl;
        $this->repoBonDwnl = $repoBonDwnl;
    }

    public function exec($calcId)
    {
        $result = [];
        /* collect additional data */
        $percentCourtesy = Cfg::COURTESY_BONUS_PERCENT;
        $dwnlCompress = $this->repoBonDwnl->getByCalcId($calcId);
        $dwnlCurrent = $this->repoDwnl->get();
        $levelsPersonal = $this->repoLevel->getByCalcTypeCode(Cfg::CODE_TYPE_CALC_BONUS_PERSONAL);
        $levelsTeam = $this->repoLevel->getByCalcTypeCode(Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF);
        /* create maps to access data */
        $mapDataById = $this->mapById($dwnlCompress, EBonDwnl::ATTR_CUST_REF);
        $mapTeams = $this->mapByTeams($dwnlCompress, EBonDwnl::ATTR_CUST_REF, EBonDwnl::ATTR_PARENT_REF);
        $mapCustById = $this->mapById($dwnlCurrent, ECustomer::ATTR_CUSTOMER_ID);
        /**
         * Go through all customers from compressed tree and calculate bonus.
         *
         * @var EBonDwnl $item
         */
        foreach ($dwnlCompress as $item) {
            $custId = $item->getCustomerRef();
            /** @var ECustomer $custData */
            $custData = $mapCustById[$custId];
            $custScheme = $this->hlpScheme->getSchemeByCustomer($custData);
            if (
                isset($mapTeams[$custId]) &&
                ($custScheme == Cfg::SCHEMA_DEFAULT)
            ) {
                $custMlmId = $custData->getHumanRef();
                $tv = $item->getTv();
                $tv = $this->hlpScheme->getForcedTv($custId, $custScheme, $tv);
                $percentTeam = $this->getLevelPercent($tv, $levelsTeam);
                $this->logger->debug("Customer #$custId ($custMlmId) has $tv TV and $percentTeam% as max percent.");
                /* for all front team members of the customer */
                $team = $mapTeams[$custId];
                foreach ($team as $memberId) {
                    /** @var EBonDwnl $memberCompress */
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