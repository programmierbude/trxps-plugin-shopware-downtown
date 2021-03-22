import template from './sw-order-user-card.html.twig';

const { Component } = Shopware;

Component.override('sw-order-user-card', {
    template,

    computed: {
        trxpsOrderId() {
            if (
                !!this.currentOrder
                && !!this.currentOrder.customFields
                && !!this.currentOrder.customFields.trxps_payments
                && !!this.currentOrder.customFields.trxps_payments.order_id
            ) {
                return this.currentOrder.customFields.trxps_payments.order_id;
            }

            return null;
        }
    }
});