<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus\Courtesy;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;
use Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Data\Bonus as DBonus;
use Praxigento\Downline\Repo\Data\Customer as ECustomer;

class Calc
{
    /** @var \Praxigento\Downline\Api\Helper\Tree */
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
    /** @var \Praxigento\BonusBase\Repo\Dao\Level */
    private $daoLevel;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\Core\Api\Helper\Format $hlpFormat,
        \Praxigento\BonusHybrid\Api\Helper\Scheme $hlpScheme,
        \Praxigento\Downline\Api\Helper\Tree $hlpDwnlTree,
        \Praxigento\BonusBase\Repo\Dao\Level $daoLevel,
        \Praxigento\Downline\Repo\Dao\Customer $daoDwnl,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoBonDwnl
    )
    {
        $this->logger = $logger;
        $this->hlpFormat = $hlpFormat;
        $this->hlpScheme = $hlpScheme;
        $this->hlpDwnlTree = $hlpDwnlTree;
        $this->daoLevel = $daoLevel;
        $this->daoDwnl = $daoDwnl;
        $this->daoBonDwnl = $daoBonDwnl;
    }

    public function exec($calcId)
    {
        $result = [];
        /* collect additional data */
        $percentCourtesy = Cfg::COURTESY_BONUS_PERCENT;
        $dwnlCompress = $this->daoBonDwnl->getByCalcId($calcId);
        $dwnlCurrent = $this->daoDwnl->get();
        $levelsPersonal = $this->daoLevel->getByCalcTypeCode(Cfg::CODE_TYPE_CALC_BONUS_PERSONAL);
        $levelsTeam = $this->daoLevel->getByCalcTypeCode(Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF);
        /* create maps to access data */
        $mapDataById = $this->hlpDwnlTree->mapById($dwnlCompress, EBonDwnl::A_CUST_REF);
        $mapTeams = $this->hlpDwnlTree->mapByTeams($dwnlCompress, EBonDwnl::A_CUST_REF, EBonDwnl::A_PARENT_REF);
        $mapCustById = $this->hlpDwnlTree->mapById($dwnlCurrent, ECustomer::A_CUSTOMER_REF);
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
                $custMlmId = $custData->getMlmId();
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
                        $memberMlmId = $memberData->getMlmId();
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