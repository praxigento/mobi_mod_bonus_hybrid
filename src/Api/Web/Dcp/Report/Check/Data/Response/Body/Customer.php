<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Api\Web\Dcp\Report\Check\Data\Response\Body;

class Customer
    extends \Praxigento\Core\Data
{
    const A_ID = 'id';
    const A_LEVEL = 'level';
    const A_MLM_ID = 'mlm_id';
    const A_NAME = 'name';

    /**
     * @return int
     */
    public function getId(): int
    {
        $result = parent::get(self::A_ID);
        return $result;
    }

    /**
     * Absolute customer level int the downline tree.
     *
     * @return int
     */
    public function getLevel(): int
    {
        $result = parent::get(self::A_LEVEL);
        return $result;
    }

    /**
     * @return string
     */
    public function getMlmId(): string
    {
        $result = parent::get(self::A_MLM_ID);
        return $result;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        $result = parent::get(self::A_NAME);
        return $result;
    }

    public function setId(int $data)
    {
        parent::set(self::A_ID, $data);
    }

    /**
     * Absolute customer level int the downline tree.
     *
     * @param int $data
     */
    public function setLevel(int $data)
    {
        parent::set(self::A_LEVEL, $data);
    }

    public function setMlmId(string $data)
    {
        parent::set(self::A_MLM_ID, $data);
    }

    public function setName(string $data)
    {
        parent::set(self::A_NAME, $data);
    }
}