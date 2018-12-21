define([
    "Praxigento_Core/js/grid/column/link"
], function (Column) {
    "use strict";

    return Column.extend({
        defaults: {
            /* see \Praxigento\BonusHybrid\Ui\DataProvider\Downline\Grid\A\Repo\Query\Grid::A_CUST_ID */
            idAttrName: "custId",
            route: "/customer/index/edit/id/"
        }
    });
});
