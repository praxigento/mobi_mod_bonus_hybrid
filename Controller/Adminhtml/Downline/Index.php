<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Controller\Adminhtml\Downline;

use Praxigento\BonusBase\Config as CfgBase;
use Praxigento\BonusHybrid\Config as Cfg;

class Index
    extends \Praxigento\BonusBase\Controller\Adminhtml\Base
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context
    ) {
        $aclResource = CfgBase::MODULE . '::' . Cfg::ACL_BONUS_DOWNLINE;
        $activeMenu = Cfg::MODULE . '::' . Cfg::MENU_BONUS_DOWNLINE;
        $breadcrumbLabel = 'Bonus Downline';
        $breadcrumbTitle = 'Bonus Downline';
        $pageTitle = 'Bonus Downline';
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