const ApiService = Shopware.Classes.ApiService;

class TrxpsPaymentsConfigService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'trxps') {
        super(httpClient, loginService, apiEndpoint);
    }

    testApiKeys(data = {liveApiKey: null, testApiKey: null}) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/config/test-api-keys`,
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

export default TrxpsPaymentsConfigService;