import template from './sw-order-line-items-grid.html.twig';

const { Component, Service } = Shopware;

Component.override('sw-order-line-items-grid', {
    template,

    inject: [
        'TrxpsPaymentsRefundService',
    ],

    data() {
        return {
            isLoading: false,
            selectedItems: {},
            showRefundModal: false,
            createCredit: false,
            quantityToRefund: 1,
            refundQuantity: 0,
        };
    },

    computed: {
        getLineItemColumns() {
            const columnDefinitions = this.$super('getLineItemColumns');

            columnDefinitions.push(
                {
                    property: 'customFields.refundedQuantity',
                    label: this.$tc('sw-order.detailExtended.columnRefunded'),
                    allowResize: false,
                    align: 'right',
                    inlineEdit: false,
                    width: '100px'
                }
            );

            return columnDefinitions;
        }
    },

    methods: {
        onRefundItem(item) {
            this.showRefundModal = item.id;
        },

        onCloseRefundModal() {
            this.showRefundModal = false;
        },

        onConfirmRefund(item) {
            this.showRefundModal = false;

            if (this.quantityToRefund > 0) {
                this.TrxpsPaymentsRefundService.refund({
                    itemId: item.id,
                    versionId: item.versionId,
                    quantity: this.quantityToRefund,
                    createCredit: this.createCredit
                })
                .then(document.location.reload());
            }

            this.quantityToRefund = 0;
        },

        isRefundable(item) {
            let refundable = false;
            if (
                item.type === 'product'
                && (
                    item.customFields == undefined
                    ||
                    (
                        item.customFields.refundedQuantity === undefined
                        || parseInt(item.customFields.refundedQuantity, 10) < item.quantity
                    )
                )
            ) {
                refundable = true;
            }

            return refundable;
        },

        refundableQuantity(item) {
            if (
                item.customFields !== null
                && item.customFields.refundedQuantity !== null
            ) {
                return item.quantity - parseInt(item.customFields.refundedQuantity, 10);
            }

            return item.quantity;
        },
    }
});