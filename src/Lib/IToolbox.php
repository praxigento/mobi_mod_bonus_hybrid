<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */
namespace Praxigento\Bonus\Hybrid\Lib;


/**
 * Toolbox interface to get tools that are used in this module.
 */
interface IToolbox extends \Praxigento\Core\Lib\IToolbox {
    /**
     * @return \Praxigento\Bonus\Hybrid\Lib\Tool\IScheme
     */
    public function getScheme();

    /**
     * @return \Praxigento\Downline\Lib\Tool\ITree
     */
    public function getDownlineTree();
}