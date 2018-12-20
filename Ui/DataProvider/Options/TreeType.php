<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Ui\DataProvider\Options;

class TreeType
    implements \Magento\Framework\Data\OptionSourceInterface
{
    private const LBL_COMPRESS = 'Compressed';
    private const LBL_PLAIN = 'Plain';
    public const VAL_COMPRESS = 'compressed';
    public const VAL_PLAIN = 'plain';

    /** @var array */
    private $options;

    public function toOptionArray()
    {
        if ($this->options === null) {
            $this->options = [
                ["label" => self::LBL_COMPRESS, "value" => self::VAL_COMPRESS],
                ["label" => self::LBL_PLAIN, "value" => self::VAL_PLAIN]
            ];
        }
        return $this->options;
    }
}
