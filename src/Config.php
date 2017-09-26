<?php
/**
 * Module's configuration (hard-coded).
 *
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid;

use Praxigento\BonusBase\Config as BonusBaseCfg;
use Praxigento\Downline\Config as DownlineCfg;
use Praxigento\Pv\Config as PvCfg;
use Praxigento\Wallet\Config as WalletCfg;

class Config extends BonusBaseCfg
{
    /**
     * Asset types.
     */
    const CODE_TYPE_ASSET_PV = PvCfg::CODE_TYPE_ASSET_PV;
    
    /**
     * Calculation types. Suffix DEF & EU are for DEFAULT & EU1 calculations schemas.
     */
    const CODE_TYPE_CALC_BONUS_COURTESY = 'HYBRID_BON_COURTESY';
    const CODE_TYPE_CALC_BONUS_INFINITY_DEF = 'HYBRID_BON_INFINITY_DEF';
    const CODE_TYPE_CALC_BONUS_INFINITY_EU = 'HYBRID_BON_INFINITY_EU';
    const CODE_TYPE_CALC_BONUS_OVERRIDE_DEF = 'HYBRID_BON_OVERRIDE_DEF';
    const CODE_TYPE_CALC_BONUS_OVERRIDE_EU = 'HYBRID_BON_OVERRIDE_EU';
    const CODE_TYPE_CALC_BONUS_PERSONAL_DEF = 'HYBRID_BON_PERSONAL_DEF';
    const CODE_TYPE_CALC_BONUS_PERSONAL_EU = 'HYBRID_BON_PERSONAL_EU';
    const CODE_TYPE_CALC_BONUS_QUICK_START = 'HYBRID_BON_QUICK_START';
    const CODE_TYPE_CALC_BONUS_SIGN_UP_DEBIT = 'HYBRID_BON_SIGN_UP_DEBIT';
    const CODE_TYPE_CALC_BONUS_TEAM_DEF = 'HYBRID_BON_TEAM_DEF';
    const CODE_TYPE_CALC_BONUS_TEAM_EU = 'HYBRID_BON_TEAM_EU';
    const CODE_TYPE_CALC_COMPRESS_PHASE1 = 'HYBRID_COMPRESS_PHASE1';
    const CODE_TYPE_CALC_COMPRESS_PHASE2_DEF = 'HYBRID_COMPRESS_PHASE2_DEF'; // Override & Infinity (DEFAULT scheme)
    const CODE_TYPE_CALC_COMPRESS_PHASE2_EU = 'HYBRID_COMPRESS_PHASE2_EU'; // Override & Infinity (EU scheme)
    const CODE_TYPE_CALC_FORECAST_PHASE2_DEF = 'HYBRID_FORECAST_PHASE2_DEF'; // Daily forecast compressed (DEFAULT)
    const CODE_TYPE_CALC_FORECAST_PHASE2_EU = 'HYBRID_FORECAST_PHASE2_EU'; // Daily forecast compressed (EU)
    const CODE_TYPE_CALC_FORECAST_PLAIN = 'HYBRID_FORECAST_PLAIN'; // Daily forecast calculation (plain tree)
    const CODE_TYPE_CALC_PV_WRITE_OFF = 'HYBRID_PV_WRITE_OFF';
    const CODE_TYPE_CALC_VALUE_OV = 'HYBRID_VALUE_OV';
    const CODE_TYPE_CALC_VALUE_TV = 'HYBRID_VALUE_TV';

    /**
     * Operation types.
     */
    const CODE_TYPE_OPER_BONUS_COURTESY = 'HYBRID_BONUS_COURTESY';
    const CODE_TYPE_OPER_BONUS_INFINITY = 'HYBRID_BONUS_INFINITY';
    const CODE_TYPE_OPER_BONUS_OVERRIDE = 'HYBRID_BONUS_OVERRIDE';
    const CODE_TYPE_OPER_BONUS_PERSONAL = 'HYBRID_BONUS_PERSONAL';
    const CODE_TYPE_OPER_BONUS_REBATE = 'HYBRID_BONUS_REBATE';
    const CODE_TYPE_OPER_BONUS_SIGNUP_DEBIT = 'HYBRID_BONUS_SIGNUP_DEBIT';
    const CODE_TYPE_OPER_BONUS_TEAM = 'HYBRID_BONUS_TEAM';
    const CODE_TYPE_OPER_PV_FORWARD = 'HYBRID_PV_FWRD';
    const CODE_TYPE_OPER_PV_WRITE_OFF = 'HYBRID_PV_WRITE_OFF';
    const CODE_TYPE_OPER_WALLET_TRANSFER = WalletCfg::CODE_TYPE_OPER_WALLET_TRANSFER;

    /**
     * Other hardcode.
     */
    const DTPS = DownlineCfg::DTPS;
    const DT_DEPTH_INIT = DownlineCfg::INIT_DEPTH;
    const MODULE = 'Praxigento_BonusHybrid';
}