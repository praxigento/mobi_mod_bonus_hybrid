<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Tool;


interface IScheme {
    /**
     * Check $custId against list of the customers with forced qualification and return the same PV or
     * qualification PV for forced customers.
     *
     * @param $custId int
     * @param $scheme stirng
     * @param $pv number
     *
     * @return number
     */
    public function getForcedPv($custId, $scheme, $pv);

    /**
     * Get array of the customers with forced qualification.
     * @return array [$custId => $rankId, ... ]
     */
    public function getForcedQualificationCustomers();

    /**
     * @return array [$custId, ...]
     */
    public function getForcedQualificationCustomersIds();

    /**
     * Return Rank ID for customers with forced qualification.
     *
     * @param $custId
     * @param $scheme
     *
     * @return null|int
     */
    public function getForcedQualificationRank($custId, $scheme);

    /**
     * Check $custId against list of the customers with forced qualification and return the same TV or
     * qualification TV for forced customers.
     *
     * @param $custId int
     * @param $scheme string
     * @param $tv number
     *
     * @return number
     */
    public function getForcedTv($custId, $scheme, $tv);

    /**
     * Get PV qualification levels for PTC Compression calculation.
     *
     * @return array [$scheme=>$level, ...]
     */
    public function getQualificationLevels();

    /**
     * Analyze customer data and return code of the calculation scheme used for this customer.
     *
     * @param $data
     *
     * @return string
     */
    public function getSchemeByCustomer($data);
}