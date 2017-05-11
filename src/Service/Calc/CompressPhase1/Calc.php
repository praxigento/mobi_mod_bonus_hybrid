<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Service\Calc\CompressPhase1;

/**
 * Compression calculation itself.
 */
class Calc
{
    public function exec()
    {
        {
            $qLevels = $this->toolScheme->getQualificationLevels();
            $forcedCustomers = $this->toolScheme->getForcedQualificationCustomersIds();
            $signupDebitCustomers = $this->hlpSignupDebitCust->exec();
            $this->logger->info("PTC Compression parameters:" .
                " qualification levels=" . var_export($qLevels, true)
                . ", forced customers: " . var_export($forcedCustomers, true));
            /* array with results: [$customerId => [$pvCompressed, $parentCompressed], ... ]*/
            $compressedTree = [];
            $mapCustomer = $this->mapById($customers, Customer::ATTR_CUSTOMER_ID);
            $mapPv = $this->mapByPv($trans, Account::ATTR_CUST_ID, Transaction::ATTR_VALUE);
            $mapDepth = $this->mapByTreeDepthDesc($treeSnap, Snap::ATTR_CUSTOMER_ID, Snap::ATTR_DEPTH);
            $mapTeams = $this->mapByTeams($treeSnap, Snap::ATTR_CUSTOMER_ID, Snap::ATTR_PARENT_ID);
            foreach ($mapDepth as $depth => $levelCustomers) {
                foreach ($levelCustomers as $custId) {
                    $pv = isset($mapPv[$custId]) ? $mapPv[$custId] : 0;
                    $parentId = $treeSnap[$custId][Snap::ATTR_PARENT_ID];
                    $custData = $mapCustomer[$custId];
                    $scheme = $this->toolScheme->getSchemeByCustomer($custData);
                    $level = $qLevels[$scheme]; // qualification level for current customer
                    $isForced = in_array($custId, $forcedCustomers);
                    $isSignupDebit = in_array($custId, $signupDebitCustomers);
                    if (($pv >= $level) || $isForced || $isSignupDebit) {
                        if (isset($compressedTree[$custId])) {
                            $pvExist = $compressedTree[$custId][0];
                            $pvNew = $pv + $pvExist;
                            $compressedTree[$custId] = [$pvNew, $parentId];
                        } else {
                            $compressedTree[$custId] = [$pv, $parentId];
                        }
                    } else {
                        /* move PV up to the closest qualified parent (parent's level is used for qualification) */
                        $path = $treeSnap[$custId][Snap::ATTR_PATH];
                        $parents = $this->toolDownlineTree->getParentsFromPathReversed($path);
                        $foundParentId = null;
                        foreach ($parents as $newParentId) {
                            $parentData = $mapCustomer[$newParentId];
                            $parentScheme = $this->toolScheme->getSchemeByCustomer($parentData);
                            $parentLevel = $qLevels[$parentScheme]; // qualification level for current parent
                            $pvParent = isset($mapPv[$newParentId]) ? $mapPv[$newParentId] : 0;
                            if (
                                ($pvParent >= $parentLevel) ||
                                (in_array($newParentId, $forcedCustomers))
                            ) {
                                $foundParentId = $newParentId;
                                break;
                            }
                        }
                        unset($parents);
                        /* add PV to the closest qualified parent */
                        if (
                            !is_null($foundParentId) &&
                            ($pv > 0)
                        ) {
                            if (isset($compressedTree[$foundParentId])) {
                                $pvExist = $compressedTree[$foundParentId][0];
                                $pvNew = $pv + $pvExist;
                                $compressedTree[$foundParentId][0] = $pvNew;
                            } else {
                                $compressedTree[$foundParentId] [0] = $pv;
                            }
                            $this->logger->debug("$pv PV are transferred from customer #$custId to his qualified parent #$foundParentId .");
                        }
                        /* change parent for all siblings of the unqualified customer */
                        if (isset($mapTeams[$custId])) {
                            $team = $mapTeams[$custId];
                            foreach ($team as $memberId) {
                                if (isset($compressedTree[$memberId])) {
                                    /* if null set customer own id to indicate root node */
                                    $compressedTree[$memberId][1] = is_null($foundParentId) ? $memberId : $foundParentId;
                                }
                            }
                        }
                    }
                }
            }
            unset($mapCustomer);
            unset($mapPv);
            unset($mapDepth);
            unset($mapTeams);
            /* compose compressed snapshot data */
            $data = $this->composeSnapUpdates($compressedTree);
            /* add compressed PV data */
            $result = $this->populateCompressedSnapWithPv($data, $compressedTree);
            return $result;
        }
    }
}