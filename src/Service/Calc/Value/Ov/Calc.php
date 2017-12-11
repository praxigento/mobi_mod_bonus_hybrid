<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Value\Ov;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Entity\Data\Downline as EBonDwnl;

/**
 * Calculate OV on the compressed downline tree.
 *
 * @deprecated use \Praxigento\BonusHybrid\Service\Calc\A\Proc\Ov
 */
class Calc
{
    /** @var \Praxigento\Downline\Helper\Tree */
    private $hlpDwnlTree;
    /** @var \Praxigento\BonusHybrid\Helper\SignupDebit\GetCustomersIds */
    private $hlpSignupDebitCust;
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Praxigento\BonusHybrid\Repo\Entity\Downline */
    private $repoBonDwnl;

    public function __construct(
        \Praxigento\Core\App\Logger\App $logger,
        \Praxigento\BonusHybrid\Helper\SignupDebit\GetCustomersIds $hlpSignupDebitCust,
        \Praxigento\Downline\Helper\Tree $hlpDwnlTree,
        \Praxigento\BonusHybrid\Repo\Entity\Downline $repoBonDwnl
    )
    {
        $this->logger = $logger;
        $this->hlpSignupDebitCust = $hlpSignupDebitCust;
        $this->hlpDwnlTree = $hlpDwnlTree;
        $this->repoBonDwnl = $repoBonDwnl;
    }

    /**
     * Calculate OV for the downline tree.
     *
     * @param int $calcId
     * @return EBonDwnl[] updated tree (with OV)
     */
    public function exec($calcId)
    {
        $result = [];
        /* collect additional data */
        $dwnlCompress = $this->repoBonDwnl->getByCalcId($calcId);
        /* create maps to access data */
        $mapById = $this->hlpDwnlTree->mapById($dwnlCompress, EBonDwnl::ATTR_CUST_REF);
        $mapDepth = $this->hlpDwnlTree->mapByTreeDepthDesc($dwnlCompress, EBonDwnl::ATTR_CUST_REF, EBonDwnl::ATTR_DEPTH);
        $mapTeams = $this->hlpDwnlTree->mapByTeams($dwnlCompress, EBonDwnl::ATTR_CUST_REF, EBonDwnl::ATTR_PARENT_REF);
        $signupDebitCustomers = $this->hlpSignupDebitCust->exec();
        /**
         * Scan downline by level from bottom to top
         */
        foreach ($mapDepth as $depth => $levelCustomers) {
            $this->logger->debug("Process level #$depth of the downline tree.");
            /* ... then scan customers on each level */
            foreach ($levelCustomers as $custId) {
                /** @var EBonDwnl $entity */
                $entity = $mapById[$custId];
                $ov = $entity->getPv(); // initial OV equals to customer's own PV
                $isSignupDebit = in_array($custId, $signupDebitCustomers);
                if ($isSignupDebit) {
                    /* add written-off PV if customer was qualified to Sign Up Debit bonus */
                    $ov += Cfg::SIGNUP_DEBIT_PV;
                }
                if (isset($mapTeams[$custId])) {
                    /* add OV from front team members */
                    $team = $mapTeams[$custId];
                    foreach ($team as $memberId) {
                        /** @var EBonDwnl $member */
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