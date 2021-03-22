import TrxpsPaymentsConfigService
    from '../core/service/api/trxps-payments-config.service';

import TrxpsPaymentsRefundService
    from '../core/service/api/trxps-payments-refund.service';

import TrxpsPaymentsShippingService
    from '../core/service/api/trxps-payments-shipping.service';

const { Application } = Shopware;

Application.addServiceProvider('TrxpsPaymentsConfigService', (container) => {
    const initContainer = Application.getContainer('init');

    return new TrxpsPaymentsConfigService(initContainer.httpClient, container.loginService);
});

Application.addServiceProvider('TrxpsPaymentsRefundService', (container) => {
    const initContainer = Application.getContainer('init');

    return new TrxpsPaymentsRefundService(initContainer.httpClient, container.loginService);
});

Application.addServiceProvider('TrxpsPaymentsShippingService', (container) => {
    const initContainer = Application.getContainer('init');

    return new TrxpsPaymentsShippingService(initContainer.httpClient, container.loginService);
});