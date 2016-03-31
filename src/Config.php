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
use Praxigento\Wallet\Lib\Config as WalletCfg;

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
    const CODE_TYPE_CALC_BONUS_TEAM_DEF = 'HYBRID_BON_TEAM_DEF';
    const CODE_TYPE_CALC_BONUS_TEAM_EU = 'HYBRID_BON_TEAM_EU';
    const CODE_TYPE_CALC_COMPRESS_FOR_OI_DEF = 'HYBRID_COMPRESS_FOR_OI_DEF';
    const CODE_TYPE_CALC_COMPRESS_FOR_OI_EU = 'HYBRID_COMPRESS_FOR_OI_EU'; // Override & Infinity (DEFAULT scheme)
    const CODE_TYPE_CALC_COMPRESS_FOR_PTC = 'HYBRID_COMPRESS_FOR_PTC'; // Override & Infinity (EU scheme)
    const CODE_TYPE_CALC_PV_WRITE_OFF = 'HYBRID_PV_WRITE_OFF'; // Compression for Personal, Team & Courtesy
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