<?php
/**
 * Authors: Alex Gusev <alex@flancer64.com>
 * Since: 2018
 */

namespace Praxigento\BonusHybrid\Ui\DataProvider\Downline;


class Form
    extends \Praxigento\Core\App\Ui\DataProvider\Base
{
    public const FIELDSET = 'downline_select';
    public const FLD_PERIOD = 'period';
    public const FLD_TREE_TYPE = 'tree_type';
    private $zInput;

    public function __construct(
        \Praxigento\BonusHybrid\Ui\DataProvider\Downline\Z\Input $zInput,
        string $name,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, 'primaryField', 'requestField', $meta, $data);
        $this->zInput = $zInput;
    }


    public function getData()
    {
        [$dsBegin, $type] = $this->zInput->extractInput();
        $period = substr($dsBegin, 0, 6);
        return [
            null => [
                self::FIELDSET => [
                    self::FLD_PERIOD => $period,
                    self::FLD_TREE_TYPE => $type
                ]
            ]
        ];
    }
}