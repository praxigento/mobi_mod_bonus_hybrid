<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Dcp\Report\Check\Data\Response;

class Customer
    extends \Praxigento\Core\Data
{
    /**
     * @return int
     */
    public function getId(): int
    {
        $result = parent::getId();
        return $result;
    }

    /**
     * Absolute customer level int the downline tree.
     *
     * @return int
     */
    public function getLevel(): int
    {
        $result = parent::getLevel();
        return $result;
    }

    /**
     * @return string
     */
    public function getMlmId(): string
    {
        $result = parent::getMlmId();
        return $result;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        $result = parent::getName();
        return $result;
    }

    public function setId(int $data)
    {
        parent::setId($data);
    }

    /**
     * Absolute customer level int the downline tree.
     *
     * @param int $data
     */
    public function setLevel(int $data)
    {
        parent::setLevel($data);
    }

    public function setMlmId(string $data)
    {
        parent::setMlmId($data);
    }

    public function setName(string $data)
    {
        parent::setName($data);
    }
}