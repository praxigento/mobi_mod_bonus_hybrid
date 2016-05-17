<?php
/**
 * Toolbox to get base implementation of tools from \Praxigento\Core\Lib\Tool package.
 *
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib\Tool;


use Praxigento\Bonus\Hybrid\Lib\IToolbox;
use Praxigento\Core\Lib\Tool\Box as CoreToolBox;
use Praxigento\Core\Lib\Tool\Convert;
use Praxigento\Core\Tool\IDate;
use Praxigento\Core\Tool\IFormat;
use Praxigento\Core\Tool\IPeriod;
use Praxigento\Downline\Tool\ITree;

class Box extends CoreToolBox implements IToolbox {

    /** @var IScheme */
    private $_scheme;
    /** @var ITree */
    private $_tree;

    public function __construct(
        Convert $convert,
        Date $date,
        Format $format,
        Period $period,
        IScheme $scheme,
        ITree $tree
    ) {
        parent::__construct($convert, $date, $format, $period, $tree);
        $this->_scheme = $scheme;
        $this->_tree = $tree;
    }

    /**
     * @return ITree
     */
    public function getDownlineTree() {
        return $this->_tree;
    }

    /**
     * @return IScheme
     */
    public function getScheme() {
        return $this->_scheme;
    }
}