<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use AlgorithmRegister\AlgorithmRegister;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../private/config.php';

function getBaseUrl($request) {
    $baseUrl = $request->getUri()->getScheme() . "://" . $request->getUri()->getHost();
    $port = $request->getUri()->getPort();
    if ($port && $port !== "80") {
        $baseUrl .= ":" . $port;
    }
    return $baseUrl;
}

$app = AppFactory::create();

$twig = Twig::create(__DIR__ . '/../docs', [
    //'cache' => __DIR__ . '/../cache'
]);

$app->add(TwigMiddleware::create($app, $twig));

$app->addBodyParsingMiddleware(); // needed for PUT payload

$algorithmRegister = new AlgorithmRegister(
    $config["storage-directory"],
    $config["known-maildomains"],
    $config["uuid-service-url"],
    $config["metadata-standard-url"]
);

$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
            ->withHeader('Access-Control-Allow-Origin', 'https://haltalk.herokuapp.com') // HAL browser
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->withHeader('Access-Control-Allow-Credentials', 'true');
});

$app->get('/docs/{rel}', function (Request $request, Response $response, $args) {
    $view = Twig::fromRequest($request);
    return $view->render($response, "{$args["rel"]}.twig", []);
    return $response;
});

$app->get('/', function (Request $request, Response $response, $args) {
    $baseUrl = getBaseUrl($request);
    $response->getBody()->write(json_encode([
        "_links" => [
            "self" => [
                "href" => "{$baseUrl}/",
                "title" => "Algorithm register: register of algorithmic applications used by government organisations"
            ],
            "curies" => [
                [
                    "name" => "ar",
                    "href" => "{$baseUrl}/docs/{rel}",
                    "templated" => true
                ]
            ],
            "ar:applications" => [
                "href" => "{$baseUrl}/applications",
                "title" => "All algorithmic applications in this algorithm register"
            ]
        ]
    ]));
    return $response->withHeader('Content-Type', 'application/hal+json');
});

$app->get('/applications', function (Request $request, Response $response, $args) use ($algorithmRegister) {
    $baseUrl = getBaseUrl($request);
    $applications = $algorithmRegister->listApplications();
    foreach ($applications as &$application) {
        $application["_links"] = [
            "self" => [
                "href" => "{$baseUrl}/applications/{$application["id"]}",
                "title" => "Details for algorithmic application {$application["name"]}"
            ],
            "schema" => [
                "href" => $application["schema"],
                "title" => "The schema used for this entry"
            ]
        ];
    }
    $response->getBody()->write(json_encode([
        "_links" => [
            "self" => [
                "href" => "{$baseUrl}/applications",
                "title" => "All algorithmic applications in this algorithm register"
            ],
            "curies" => [
                [
                    "name" => "ar",
                    "href" => "{$baseUrl}/docs/{rel}",
                    "templated" => true
                ]
            ],
            "ar:register" => [
                "href" => "{$baseUrl}/",
                "title" => "Algorithm register: register of algorithmic applications used by government organisations"
            ]
        ],
        "count" => count($applications),
        "_embedded" => [
            "applications" => $applications
        ]
    ]));
    return $response->withHeader('Content-Type', 'application/hal+json');
});

$app->get('/applications/{id}', function (Request $request, Response $response, $args) use ($algorithmRegister) {
    $baseUrl = getBaseUrl($request);
    $id = $args['id'];
    $application = $algorithmRegister->readApplication($id);
    $application["_links"] = [
        "self" => [
            "href" => "{$baseUrl}/applications/{$id}",
            "title" => "Details for algorithmic application {$application["name"]["value"]}"
        ],
        "schema" => [
            "href" => $application["schema"]["value"],
            "title" => "The schema used for this entry"
        ],
        "curies" => [
            [
                "name" => "ar",
                "href" => "{$baseUrl}/docs/{rel}",
                "templated" => true
            ]
        ],
        "ar:applications" => [
            "href" => "{$baseUrl}/applications",
            "title" => "All algorithmic applications in this algorithm register"
        ],
        "ar:events" => [
            "href" => "{$baseUrl}/events/{$id}",
            "title" => "All events for algorithmic application {$application["name"]["value"]}"
        ]
    ];
    $response->getBody()->write(json_encode(
        $application
    ));
    return $response->withHeader('Content-Type', 'application/hal+json');
});

$app->post('/applications', function (Request $request, Response $response, $args) use ($algorithmRegister) {
    $baseUrl = getBaseUrl($request);
    $application = $algorithmRegister->createApplication($request->getParsedBody(), $request->getUri());
    $application["_links"] = [
        "self" => [
            "href" => "{$baseUrl}/applications/{$application["uuid"]["value"]}"
        ],
        "schema" => [
            "href" => $application["schema"]["value"],
            "title" => "The schema used for this entry"
        ],
        "curies" => [
            [
                "name" => "ar",
                "href" => "{$baseUrl}/docs/{rel}",
                "templated" => true
            ]
        ],
        "ar:applications" => [
            "href" => "{$baseUrl}/applications",
            "title" => "All algorithmic applications in this algorithm register"
        ]
    ];
    $response->getBody()->write(json_encode(
        $application
    ));
    return $response->withHeader('Content-Type', 'application/hal+json');
});

$app->put('/applications/{id}', function (Request $request, Response $response, $args) use ($algorithmRegister) {
    $baseUrl = getBaseUrl($request);
    $token = $request->getQueryParams()["token"];
    $application = $algorithmRegister->updateApplication($args['id'], $request->getParsedBody(), $token);
    $application["_links"] = [
        "self" => [
            "href" => "{$baseUrl}/applications/{$application["uuid"]}" // FIXME test ["value"]?
        ],
        "schema" => [
            "href" => $application["schema"]["value"],
            "title" => "The schema used for this entry"
        ],
        "curies" => [
            [
                "name" => "ar",
                "href" => "{$baseUrl}/docs/{rel}",
                "templated" => true
            ]
        ],
        "ar:applications" => [
            "href" => "{$baseUrl}/applications",
            "title" => "All algorithmic applications in this algorithm register"
        ]
    ];
    $response->getBody()->write(json_encode(
        $application
    ));
    return $response->withHeader('Content-Type', 'application/hal+json');
});

$app->delete('/applications/{id}', function (Request $request, Response $response, $args) use ($algorithmRegister) {
    $token = $request->getQueryParams()["token"];
    $algorithmRegister->deleteApplication($args['id'], $token);
    return $response->withStatus('204');
});

$app->get('/events/{id}', function (Request $request, Response $response, $args) use ($algorithmRegister) {
    $baseUrl = getBaseUrl($request);
    $id = $args['id'];
    $events = $algorithmRegister->listEvents($id);
    $response->getBody()->write(json_encode([
        "_links" => [
            "self" => [
                "href" => "{$baseUrl}/events/{$id}",
                "title" => "All events for algorithmic application {$id}"
            ],
            "curies" => [
                [
                    "name" => "ar",
                    "href" => "{$baseUrl}/docs/{rel}",
                    "templated" => true
                ]
            ],
            "ar:register" => [
                "href" => "{$baseUrl}/",
                "title" => "Algorithm register: register of algorithmic applications used by government organisations"
            ],
            "ar:applications" => [
                "href" => "{$baseUrl}/applications/{$id}",
                "title" => "Details for algorithmic application {$id}"
            ]
        ],
        "count" => count($events),
        "_embedded" => [
            "events" => $events
        ]
    ]));
    return $response->withHeader('Content-Type', 'application/hal+json');
});

$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function ($request, $response) {
    throw new HttpNotFoundException($request);
});

$app->run();
