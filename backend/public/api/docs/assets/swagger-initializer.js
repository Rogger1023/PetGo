/* Swagger UI dist 5.32.0. Assets are served locally by the API container. */
window.addEventListener('load', function () {
  window.ui = SwaggerUIBundle({
    url: '/api/docs/openapi.yaml',
    dom_id: '#swagger-ui',
    deepLinking: true,
    withCredentials: true,
    persistAuthorization: false,
    validatorUrl: null,
    // Laravel renders API errors as JSON when this header is present.
    requestInterceptor: function (request) {
      request.credentials = 'same-origin';
      request.headers = request.headers || {};
      request.headers.Accept = 'application/json';
      return request;
    }
  });
});
