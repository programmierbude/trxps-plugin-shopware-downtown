const ApiService = Shopware.Classes.ApiService;

class TrxpsPaymentsRefundService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'trxps') {
        super(httpClient, loginService, apiEndpoint);
    }

    refund(data = {itemId: null, versionId: null, quantity: null, createCredit: null}) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/refund`,
                JSON.stringify(data),
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    total(data = {orderId: null}) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/refund/total`,
                JSON.stringify(data),
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default TrxpsPaymentsRefundService;