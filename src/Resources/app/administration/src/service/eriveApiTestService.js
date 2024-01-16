const { Application } = Shopware;

class ApiClient {
  constructor(httpClient) {
    this.httpClient = httpClient;
  }

  check(configValues) {
    let baseUrl = "";
    let apiKey = configValues["EriveDelivery.config.apiTestKey"];
    const eriveEnv = configValues["EriveDelivery.config.eriveEnvironment"];

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

    const testUrl = window.location.origin + "/api/endpoint/test";
    const headers = { Authorization: "Bearer " + this.readAuthToken(), "Content-Type": "application/json" };
    const options = { method: "POST", headers, body: JSON.stringify({ baseUrl, apiKey }) };
    return new Promise((resolve, reject) => {
      fetch(testUrl, options)
        .then((result) => {
          if (result.ok) {
            return result.json();
          } else {
            reject(result);
          }
        })
        .then((response) => {
          resolve(response);
        })
        .catch((e) => {
          console.error(e);
          reject(e);
        });
    });
  }

  readAuthToken() {
    const bearerAuth = document.cookie
      .split("; ")
      .find((row) => row.startsWith("bearerAuth="))
      ?.split("=")[1];

    try {
      return JSON.parse(decodeURIComponent(bearerAuth))["access"];
    } catch (e) {
      return;
    }
  }
}

Application.addServiceProvider("eriveApiTest", (container) => {
  const initContainer = Application.getContainer("init");
  return new ApiClient(initContainer.httpClient);
});
