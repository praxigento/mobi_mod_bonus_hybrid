<?php
/**
 * This is default values for project specific parameters. This values are used in default implementations of the
 * services and tools.
 *
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid;


class Defaults {
    /**
     * Default qualification levels to compress downline tree in Santegra project.
     */
    const PV_QUALIFICATION_LEVEL_DEF = 50;
    const PV_QUALIFICATION_LEVEL_EU = 100;
    /**
     * Default codes for the ranks in Santegra project.
     */
    const RANK_DIRECTOR = 'DIRECTOR';
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
}