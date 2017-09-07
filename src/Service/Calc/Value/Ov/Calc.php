<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Value\Ov;

use Praxigento\BonusHybrid\Defaults as Def;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EDwnlBon;

/**
 * Calculate OV on the compressed downline tree.
 */
class Calc
{
    /** Add traits */
    use \Praxigento\BonusHybrid\Service\Calc\Traits\TMap {
        mapById as protected;
        mapByTeams as protected;
        mapByTreeDepthDesc as protected;
    }

    /** @var \Praxigento\BonusHybrid\Helper\SignupDebit\GetCustomersIds */
    protected $hlpSignupDebitCust;
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoDwnlBon;

    public function __construct(
        \Praxigento\Core\Fw\Logger\App $logger,
        \Praxigento\BonusHybrid\Helper\SignupDebit\GetCustomersIds $hlpSignupDebitCust,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoDwnlBon
    )
    {
        $this->logger = $logger;
        $this->repoDwnlBon = $repoDwnlBon;
        $this->hlpSignupDebitCust = $hlpSignupDebitCust;
    }

    /**
     * Calculate OV for the downline tree.
     *
     * @param int $calcId
     * @return EDwnlBon[] updated tree (with OV)
     */
    public function exec($calcId)
    {
        $result = [];
        /* collect additional data */
        $dwnlCompress = $this->repoDwnlBon->getByCalcId($calcId);
        /* create maps to access data */
        $mapById = $this->mapById($dwnlCompress, EDwnlBon::ATTR_CUST_REF);
        $mapDepth = $this->mapByTreeDepthDesc($dwnlCompress, EDwnlBon::ATTR_CUST_REF, EDwnlBon::ATTR_DEPTH);
        $mapTeams = $this->mapByTeams($dwnlCompress, EDwnlBon::ATTR_CUST_REF, EDwnlBon::ATTR_PARENT_REF);
        $signupDebitCustomers = $this->hlpSignupDebitCust->exec();
        /**
         * Scan downline by level from bottom to top
         */
        foreach ($mapDepth as $depth => $levelCustomers) {
            $this->logger->debug("Process level #$depth of the downline tree.");
            /* ... then scan customers on each level */
            foreach ($levelCustomers as $custId) {
                /** @var EDwnlBon $entity */
                $entity = $mapById[$custId];
                $ov = $entity->getPv(); // initial OV equals to customer's own PV
                $isSignupDebit = in_array($custId, $signupDebitCustomers);
                if ($isSignupDebit) {
                    /* add written-off PV if customer was qualified to Sign Up Debit bonus */
                    $ov += Def::SIGNUP_DEBIT_PV;
                }
                if (isset($mapTeams[$custId])) {
                    /* add OV from front team members */
                    $team = $mapTeams[$custId];
                    foreach ($team as $memberId) {
                        /** @var EDwnlBon $member */
                        $member = $result[$memberId];
                        $memberOv = $member->getOv();
                        $ov += $memberOv;
                    }
                }
                $entity->setOv($ov);
                $result[$custId] = $entity;
            }
        }
        unset($mapPv);
        unset($mapTeams);
        unset($mapDepth);

        return $result;
    }

}