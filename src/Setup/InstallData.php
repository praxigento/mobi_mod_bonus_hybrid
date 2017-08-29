<?php
/**
 * Populate DB schema with module's initial data
 * .
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Setup;

use Praxigento\Accounting\Repo\Entity\Data\Type\Operation as TypeOperation;
use Praxigento\BonusBase\Repo\Entity\Data\Type\Calc as TypeCalc;
use Praxigento\BonusHybrid\Config as Cfg;

class InstallData extends \Praxigento\Core\Setup\Data\Base
{
    private function _addAccountingOperationsTypes()
    {
        $this->_conn->insertArray(
            $this->_resource->getTableName(TypeOperation::ENTITY_NAME),
            [TypeOperation::ATTR_CODE, TypeOperation::ATTR_NOTE],
            [
                [Cfg::CODE_TYPE_OPER_BONUS_COURTESY, 'Courtesy bonus.'],
                [Cfg::CODE_TYPE_OPER_BONUS_INFINITY, 'Infinity bonus.'],
                [Cfg::CODE_TYPE_OPER_BONUS_OVERRIDE, 'Override bonus.'],
                [Cfg::CODE_TYPE_OPER_BONUS_PERSONAL, 'Personal bonus.'],
                [
                    Cfg::CODE_TYPE_OPER_PV_FORWARD,
                    'PV transfer from one not closed period to other period in the future for the same customer.'
                ],
                [Cfg::CODE_TYPE_OPER_PV_WRITE_OFF, 'PV write off in the end of the bonus calculation period.'],
                [Cfg::CODE_TYPE_OPER_BONUS_REBATE, 'Orders rebates (personal bonus in EU scheme).'],
                [Cfg::CODE_TYPE_OPER_BONUS_SIGNUP_DEBIT, 'Sign Up Volume Debit bonus.'],
                [Cfg::CODE_TYPE_OPER_BONUS_TEAM, 'Team bonus.']
            ]
        );
    }

    private function _addBonusCalculationsTypes()
    {
        $this->_conn->insertArray(
            $this->_resource->getTableName(TypeCalc::ENTITY_NAME),
            [TypeCalc::ATTR_CODE, TypeCalc::ATTR_NOTE],
            [
                [Cfg::CODE_TYPE_CALC_BONUS_SIGNUP_DEBIT, 'Sign Up Volume Debit (EU only).'],
                [Cfg::CODE_TYPE_CALC_BONUS_QUICK_START, 'Quick Start (EU only).'],
                [Cfg::CODE_TYPE_CALC_FORECAST_COMPRESS, 'Daily forecast calculation (compressed).'],
                [Cfg::CODE_TYPE_CALC_FORECAST_PLAIN, 'Daily forecast calculation (plain tree).'],
                [Cfg::CODE_TYPE_CALC_PV_WRITE_OFF, 'PV write off calculation.'],
                [
                    Cfg::CODE_TYPE_CALC_COMPRESS_PHASE1,
                    'Compression calculation for Personal, Team & Courtesy bonuses).'
                ],
                [Cfg::CODE_TYPE_CALC_BONUS_PERSONAL_DEF, 'Personal bonus calculation (DEFAULT scheme).'],
                [Cfg::CODE_TYPE_CALC_BONUS_PERSONAL_EU, 'Personal bonus calculation (EU scheme).'],
                [Cfg::CODE_TYPE_CALC_VALUE_TV, 'Team Volumes calculation.'],
                [Cfg::CODE_TYPE_CALC_BONUS_TEAM_DEF, 'Team bonus calculation (DEFAULT scheme).'],
                [Cfg::CODE_TYPE_CALC_BONUS_TEAM_EU, 'Team bonus calculation (EU scheme).'],
                [Cfg::CODE_TYPE_CALC_BONUS_COURTESY, 'Courtesy bonus calculation.'],
                [Cfg::CODE_TYPE_CALC_VALUE_OV, 'Organizational Volumes calculation.'],
                [
                    Cfg::CODE_TYPE_CALC_COMPRESS_PHASE2_DEF,
                    'Compression calculation for Override & Infinity bonuses (DEFAULT scheme).'
                ],
                [
                    Cfg::CODE_TYPE_CALC_COMPRESS_PHASE2_EU,
                    'Compression calculation for Override & Infinity bonuses (EU scheme).'
                ],
                [Cfg::CODE_TYPE_CALC_BONUS_OVERRIDE_DEF, 'Override bonus calculation (DEFAULT scheme).'],
                [Cfg::CODE_TYPE_CALC_BONUS_OVERRIDE_EU, 'Override bonus calculation (EU scheme).'],
                [Cfg::CODE_TYPE_CALC_BONUS_INFINITY_DEF, 'Infinity bonus calculation (DEFAULT scheme).'],
                [Cfg::CODE_TYPE_CALC_BONUS_INFINITY_EU, 'Infinity bonus calculation (EU scheme).']
            ]
        );
    }

    protected function _setup()
    {
        $this->_addBonusCalculationsTypes();
        $this->_addAccountingOperationsTypes();
    }

}