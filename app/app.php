<?php
//
// SlimPHP Example Application
// =============================================================================
//
// This is a small application with a few endpoints that have been designed to
// highlight some common integration testing techniques. This application has
// objects for external requests via Curl, and a custom authentication class.
// We'll be able to mock both of those in order to control our application
// state, and we've got some simple behaviors to test in the endpoint for
// authenticated file storage access.
//
// Please view the `README.md` file, and check out the integration tests in
// `tests/integration/`.
//
// * Author: [Craig Davis](craig@there4development.com)
// * Since: 10/2/2013
//
// -----------------------------------------------------------------------------


// Dependency Injection Containers
// -----------------------------------------------------------------------------
// In our unit tests, we'll mock these so that we can control our application
// state.
$app->curl = function ($c) use ($app) {
    return new \Curl();
};
$app->authentication = function ($c) use ($app) {
    return new \There4\Authentication\Cookie();
};


// Error Handler for any uncaught exception
// -----------------------------------------------------------------------------
// This can be silenced by turning on Slim Debugging. All exceptions thrown by
// our application will be collected here.
$app->error(function (\Exception $e) use ($app) {
    $app->response->setStatus(500);
    include __DIR__ . '/views/error.php';
});


// Token Authentication Middleware
// -----------------------------------------------------------------------------
// Halt the response if the token is not valid.
$authenticate = function ($app) {
    return function () use ($app) {
        $auth = $app->authentication;
        if ($auth->authenticate($app->getCookie('ApiToken'))) {
            return;
        }
        $app->halt(401, 'Invalid authentication token');
    };
};


// Version Endpoint
// -----------------------------------------------------------------------------
// Heartbeat endpoint, should always return 200 and the application version.
$app->get('/version', function () use ($app) {
    echo $app->config('version');
});


// Zen Statement From GitHub
// -----------------------------------------------------------------------------
// Can be used to verify that the application has external connectivity.
$app->get('/zen', function () use ($app) {
    $response = $app->curl->get('https://api.github.com/zen');
    if ($response->headers['Status-Code'] != 200) {
        $app->halt(502, 'GitHub has failed with :' + $response->headers['Status-Code']);
    }
    echo $response->body;
});


// Fetch a file from the file store.
// -----------------------------------------------------------------------------
// Authenticated request for a file from the file store
$app->get('/files/:filename', $authenticate($app), function ($filename) use ($app) {
    $supported_types = (object) array(
        'json'    => 'application/json',
        'xml'     => 'application/xml',
        'csv'     => 'text/csv',
        'unknown' => 'application/octet-stream'
    );

    $filename  = pathinfo($filename, PATHINFO_BASENAME);
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $path      = realpath(__DIR__ . '/../file_store/' . $filename);

    $content_type
        = property_exists($supported_types, $extension)
        ? $supported_types->$extension
        : $supported_types->unknown;

    if (!is_readable($path)) {
        $app->notFound();
    }

    $app->response->headers->set('Content-Type', $content_type);
    readfile($path);
});


/* End of file app.php */
