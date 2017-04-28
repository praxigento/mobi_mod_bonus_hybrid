<?php
/**
 * User: Alex Gusev <alex@flancer64.com>
 */

namespace Praxigento\BonusHybrid\Cli\Cmd;

use Praxigento\Accounting\Repo\Query\Balance\OnDate\Closing\ByAsset\Builder as QBalanceClose;

/**
 * Daily calculation to forecast results on final bonus calc.
 */
class Forecast
    extends \Praxigento\Core\Cli\Cmd\Base
{
    /** @var \Praxigento\Accounting\Service\IBalance */
    protected $callBalance;
    /** @var \Praxigento\BonusHybrid\Service\Calc\IForecast */
    protected $callCalcForecast;
    /** @var \Magento\Framework\DB\Adapter\AdapterInterface */
    protected $conn;
    /** @var \Praxigento\Accounting\Repo\Query\Balance\OnDate\Closing\ByAsset\Builder */
    protected $qbldBalClose;
    /** @var \Magento\Framework\App\ResourceConnection */
    protected $resource;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $manObj,
        \Magento\Framework\App\ResourceConnection $resource,
        \Praxigento\Accounting\Repo\Query\Balance\OnDate\Closing\ByAsset\Builder $qbldBalClose,
        \Praxigento\BonusHybrid\Service\Calc\IForecast $callCalcForecast,
        \Praxigento\Accounting\Service\IBalance $callBalance
    ) {
        parent::__construct(
            $manObj,
            'prxgt:bonus:forecast',
            'Daily calculation to forecast results on final bonus calc.'
        );
        $this->resource = $resource;
        $this->conn = $this->resource->getConnection();
        $this->qbldBalClose = $qbldBalClose;
        $this->callCalcForecast = $callCalcForecast;
        $this->callBalance = $callBalance;
    }

    protected function execute(
        \Symfony\Component\Console\Input\InputInterface $input,
        \Symfony\Component\Console\Output\OutputInterface $output
    ) {
        $output->writeln("<info>Start forecast calculation.<info>");
        $this->conn->beginTransaction();
        try {
            $req = new \Praxigento\BonusHybrid\Service\Calc\Forecast\Request();
            $resp = $this->callCalcForecast->exec($req);

            /* get balances on the end of the previous period */
            $qCloseBegin = $this->qbldBalClose->getSelectQuery();
            $bind = [
                QBalanceClose::BIND_ASSET_TYPE_ID => 3,
                QBalanceClose::BIND_MAX_DATE => '20170228'
            ];
            $rowsBegin = $this->conn->fetchAll($qCloseBegin, $bind);

            /* get balances on the end of this period */
            $qCloseBegin = $this->qbldBalClose->getSelectQuery();
            $bind = [
                QBalanceClose::BIND_ASSET_TYPE_ID => 3,
                QBalanceClose::BIND_MAX_DATE => '20170310'
            ];
            $rowsEnd = $this->conn->fetchAll($qCloseBegin, $bind);


            /* compose PV delta for period */
            $bal = [];
            $sum = 0;
            foreach ($rowsEnd as $row) {
                $customerId = $row[QBalanceClose::A_CUST_ID];
                $accId = $row[QBalanceClose::A_ACC_ID];
                $balanceClose = $row[QBalanceClose::A_BALANCE];
                $data = new \Flancer32\Lib\Data();
                $data->set('accountId', $accId);
                $data->set('customerId', $customerId);
                $data->set('balanceClose', $balanceClose);
                $data->set('balanceOpen', 0);
                $data->set('turnover', $balanceClose);
                $bal[$customerId] = $data;
            }
            /* add opening balance and delta */
            foreach ($rowsBegin as $row) {
                $customerId = $row[QBalanceClose::A_CUST_ID];
                $balanceOpen = $row[QBalanceClose::A_BALANCE];
                $data = $bal[$customerId];
                $balanceClose = $data->get('balanceClose');
                $turnover = ($balanceClose - $balanceOpen);
                $data->set('balanceOpen', $balanceOpen);
                $data->set('turnover', $turnover);
                $bal[$customerId] = $data;
                $sum += $turnover;
            }

//            $this->conn->commit();
            $this->conn->rollBack();
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $trace = $e->getTraceAsString();
            $output->writeln("<error>$msg<error>\n$trace");
            $this->conn->rollBack();
        }
        $output->writeln('<info>Command is completed.<info>');

    }

}