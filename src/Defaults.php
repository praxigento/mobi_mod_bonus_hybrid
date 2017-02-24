<?php
/**
 * This is default values for Santegra specific parameters. This values are used in default implementations of the
 * services and tools. This module is used in Santegra project only.
 *
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\BonusHybrid;


class Defaults {

    const COURTESY_BONUS_PERCENT = 0.05;
    /**
     * Default qualification levels to compress downline tree in Santegra project.
     */
    const PV_QUALIFICATION_LEVEL_DEF = 50;
    const PV_QUALIFICATION_LEVEL_EU = 100;
    /**
     * Default codes for the ranks in Santegra project.
     */
    const RANK_DIRECTOR = 'DIRECTOR';
    const RANK_DISTRIBUTOR = 'DISTRIBUTOR';
    const RANK_EXEC_DIRECTOR = 'EXECUTIVE DIRECTOR';
    const RANK_EXEC_VICE = 'EXEC VICE';
    const RANK_MANAGER = 'MANAGER';
    const RANK_PRESIDENT = 'PRESIDENT';
    const RANK_SEN_DIRECTOR = 'SENIOR DIRECTOR';
    const RANK_SEN_MANAGER = 'SENIOR MANAGER';
    const RANK_SEN_VICE = 'SENIOR VICE';
    const RANK_SUPERVISOR = 'SUPERVISOR';
    /**
     * Rebate bonus percent for qualified distributors (Personal bonus in EU).
     */
    const REBATE_PERCENT = 0.4;
    /**
     * Default schemas are used in the Santegra Projects.
     */
    const SCHEMA_DEFAULT = 'DEFAULT';
    const SCHEMA_EU = 'EU';
    /**
     * Sign Up Volume Debit parameters: PV Off & Wallet On values.
     */
    const SIGNUP_DEBIT_PV = 100;
    const SIGNUP_DEBIT_WALLET = 34;

    const TEAM_BONUS_EU_PERCENT = 0.05;
}