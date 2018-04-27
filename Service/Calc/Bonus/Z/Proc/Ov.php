<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\Bonus\Z\Proc;

use Praxigento\BonusHybrid\Config as Cfg;
use Praxigento\BonusHybrid\Repo\Data\Downline as EBonDwnl;

/**
 * Calculate OV on the compressed downline tree.
 */
class Ov
    implements \Praxigento\Core\Api\App\Service\Process
{
    /** \Praxigento\BonusHybrid\Repo\Data\Downline[] downline with PV & TV */
    const IN_DWNL = 'downline';
    /** bool 'false' - don't use "Sign Up" bonus values in OV calculation */
    const IN_USE_SIGN_UP = 'useSignUp';
    /** \Praxigento\BonusHybrid\Repo\Data\Downline[] updated downline with OV*/
    const OUT_DWNL = 'downline';

    /** @var \Praxigento\Downline\Helper\Tree */
    private $hlpDwnlTree;
    /** @var \Praxigento\BonusHybrid\Service\Calc\Z\Helper\GetCustomersIds */
    private $hlpSignUpDebitCust;
    /** @var \Praxigento\Core\Api\App\Logger\Main */
    private $logger;

    public function __construct(
        \Praxigento\Core\Api\App\Logger\Main $logger,
        \Praxigento\BonusHybrid\Service\Calc\Z\Helper\GetCustomersIds $hlpSignUpDebitCust,
        \Praxigento\Downline\Helper\Tree $hlpDwnlTree
    )
    {
        $this->logger = $logger;
        $this->hlpSignUpDebitCust = $hlpSignUpDebitCust;
        $this->hlpDwnlTree = $hlpDwnlTree;
    }

    public function exec(\Praxigento\Core\Data $ctx)
    {
        /* get working data from input */
        /** @var EBonDwnl[] $dwnlBonus */
        $dwnlCompress = $ctx->get(self::IN_DWNL);
        $useSignUp = (bool)$ctx->get(self::IN_DWNL);

        /* define local working data */

        /* create maps to access data */
        $mapById = $this->hlpDwnlTree->mapById($dwnlCompress, EBonDwnl::A_CUST_REF);
        $mapDepth = $this->hlpDwnlTree->mapByTreeDepthDesc($dwnlCompress, EBonDwnl::A_CUST_REF, EBonDwnl::A_DEPTH);
        $mapTeams = $this->hlpDwnlTree->mapByTeams($dwnlCompress, EBonDwnl::A_CUST_REF, EBonDwnl::A_PARENT_REF);
        $signupDebitCustomers = [];
        if ($useSignUp) {
            $signupDebitCustomers = $this->hlpSignUpDebitCust->exec();
        }
        /**
         * Scan downline by level from bottom to top
         */
        $out = [];
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
                        $member = $out[$memberId];
                        $memberOv = $member->getOv();
                        $ov += $memberOv;
                    }
                }
                $entity->setOv($ov);
                $out[$custId] = $entity;
            }
        }
        unset($mapPv);
        unset($mapTeams);
        unset($mapDepth);

        /* put result data into output */
        $result = new \Praxigento\Core\Data();
        $result->set(self::OUT_DWNL, $out);
        return $result;
    }

}