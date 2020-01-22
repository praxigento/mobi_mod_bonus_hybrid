<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2020
 */

namespace Praxigento\BonusHybrid\Controller\Adminhtml\Downgrade;

use Praxigento\BonusBase\Config as CfgBase;
use Praxigento\BonusHybrid\Config as Cfg;

class Index
    extends \Praxigento\Core\App\Action\Back\Base
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context
    ) {
        $aclResource = CfgBase::MODULE . '::' . Cfg::ACL_BONUS_DOWNLINE;
        $activeMenu = Cfg::MODULE . '::' . Cfg::MENU_CUSTOMER_DOWNGRADE;
        $breadcrumbLabel = 'Customers Downgrade';
        $breadcrumbTitle = 'Customers Downgrade';
        $pageTitle = 'Customers Downgrade';
        parent::__construct(
            $context,
            $aclResource,
            $activeMenu,
            $breadcrumbLabel,
            $breadcrumbTitle,
            $pageTitle
        );
    }
}