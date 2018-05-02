<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Value\Ov;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;

/**
 * Calculate OV on the compressed downline tree.
 *
 * @deprecated use \Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc\Ov
 */
class Calc
{
    /** @var \Praxigento\Downline\Api\Helper\Tree */
    private $hlpDwnlTree;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Z\Helper\GetCustomersIds */
    private $hlpSignUpDebitCust;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;
    /** @var \Praxigento\BonusHybrid\Repo\Dao\Downline */
    private $daoBonDwnl;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\BonusHybrid\Service\Calc\Z\Helper\GetCustomersIds $hlpSignUpDebitCust,
        \Praxigento\Downline\Api\Helper\Tree $hlpDwnlTree,
        \Praxigento\BonusHybrid\Repo\Dao\Downline $daoBonDwnl
    )
    {
        $this->logger = $logger;
        $this->hlpSignUpDebitCust = $hlpSignUpDebitCust;
        $this->hlpDwnlTree = $hlpDwnlTree;
        $this->daoBonDwnl = $daoBonDwnl;
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
        $dwnlCompress = $this->daoBonDwnl->getByCalcId($calcId);
        /* create maps to access data */
        $mapById = $this->hlpDwnlTree->mapById($dwnlCompress, EBonDwnl::A_CUST_REF);
        $mapDepth = $this->hlpDwnlTree->mapByTreeDepthDesc($dwnlCompress, EBonDwnl::A_CUST_REF, EBonDwnl::A_DEPTH);
        $mapTeams = $this->hlpDwnlTree->mapByTeams($dwnlCompress, EBonDwnl::A_CUST_REF, EBonDwnl::A_PARENT_REF);
        $signupDebitCustomers = $this->hlpSignUpDebitCust->exec();
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
                $isSignUpDebit = in_array($custId, $signupDebitCustomers);
                if ($isSignUpDebit) {
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