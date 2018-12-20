define([
    "Praxigento_Core/js/grid/columns/link"
], function (Column) {
    "use strict";

    return Column.extend({
        defaults: {
            /* see \Praxigento\BonusHybrid\Ui\DataProvider\Downline\Grid\A\Repo\Query\Grid::A_CUST_MLM_ID */
            idAttrName: "custMlmId",
            route: "/customer/downline/index/mlmId/"
        }
    });
});
