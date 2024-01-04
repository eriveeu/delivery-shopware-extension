const { Application } = Shopware;

class ApiClient {
  constructor(httpClient) {
    this.httpClient = httpClient;
  }

  check(configValues) {
    let baseUrl = "";
    let apiKey = configValues["EriveDelivery.config.apiTestKey"];
    const eriveEnv = configValues["EriveDelivery.config.eriveEnvironment"];
    const url = "/company/parcelsFrom";

    switch (eriveEnv) {
      case "www":
        baseUrl = `https://${eriveEnv}.erive.delivery/api/v1`;
        apiKey = configValues["EriveDelivery.config.apiKey"];
        break;
      case "custom":
        baseUrl = configValues["EriveDelivery.config.customApiEndpoint"];
        break;
      default:
        baseUrl = `https://${eriveEnv}.greentohome.at/api/v1`;
        break;
    }
    baseUrl = baseUrl && baseUrl.endsWith("/") ? baseUrl.substring(0, baseUrl.length - 1) : baseUrl;
    return this.httpClient.get(`${baseUrl}${url}?key=${apiKey}`);
  }
}

Application.addServiceProvider("eriveApiTest", (container) => {
  const initContainer = Application.getContainer("init");
  return new ApiClient(initContainer.httpClient);
});
