<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Tiltshift\Algoritmeregister\Algoritmeregister;

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

$algoritmeregister = new Algoritmeregister(
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
            ->withHeader('Access-Control-Allow-Origin', 'https://haltalk.herokuapp.com')
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
                "title" => "Algoritmeregister"
            ],
            "curies" => [
                [
                    "name" => "ar",
                    "href" => "{$baseUrl}/docs/{rel}",
                    "templated" => true
                ]
            ],
            "ar:toepassingen" => [
                "href" => "{$baseUrl}/toepassingen",
                "title" => "Alle toepassingen in dit algoritmeregister"
            ]
        ]
    ]));
    return $response->withHeader('Content-Type', 'application/hal+json');
});

$app->get('/dump-events', function (Request $request, Response $response, $args) use ($algoritmeregister) {
    $events = $algoritmeregister->getEvents();
    $response->getBody()->write($events . "\n");
    return $response->withHeader('Content-Type', 'text');
});

$app->get('/dump-toepassingen', function (Request $request, Response $response, $args) use ($algoritmeregister) {
    $toepassingen = $algoritmeregister->listToepassingen();
    $txt = "\"" . implode("\",\"", array_keys(reset($toepassingen))) . "\"\n";
    foreach ($toepassingen as &$toepassing) {
        $txt .= "\"" . implode("\",\"", $toepassing) . "\"\n";
    }
    $response->getBody()->write($txt);
    return $response->withHeader('Content-Type', 'text');
});

$app->get('/toepassingen', function (Request $request, Response $response, $args) use ($algoritmeregister) {
    $baseUrl = getBaseUrl($request);
    $toepassingen = $algoritmeregister->listToepassingen();
    foreach ($toepassingen as &$toepassing) {
        $toepassing["_links"] = [
            "self" => [
                "href" => "{$baseUrl}/toepassingen/{$toepassing["id"]}",
                "title" => "Detailpagina voor toepassing {$toepassing["naam"]}"
            ]
        ];
    }
    $response->getBody()->write(json_encode([
        "_links" => [
            "self" => [
                "href" => "{$baseUrl}/toepassingen",
                "title" => "Alle toepassingen in dit algoritmeregister"
            ],
            "curies" => [
                [
                    "name" => "ar",
                    "href" => "{$baseUrl}/docs/{rel}",
                    "templated" => true
                ]
            ],
            "ar:algoritmeregister" => [
                "href" => "{$baseUrl}/",
                "title" => "Algoritmeregister"
            ]
        ],
        "count" => count($toepassingen),
        "_embedded" => [
            "toepassingen" => $toepassingen
        ]
    ]));
    return $response->withHeader('Content-Type', 'application/hal+json');
});

$app->get('/toepassingen/{id}', function (Request $request, Response $response, $args) use ($algoritmeregister) {
    $baseUrl = getBaseUrl($request);
    $id = $args['id'];
    $toepassing = $algoritmeregister->readToepassing($id);
    $toepassing["_links"] = [
        "self" => [
            "href" => "{$baseUrl}/toepassingen/{$id}",
            "title" => "Detailpagina voor toepassing {$toepassing["naam"]["waarde"]}"
        ],
        "curies" => [
            [
                "name" => "ar",
                "href" => "{$baseUrl}/docs/{rel}",
                "templated" => true
            ]
        ],
        "ar:toepassingen" => [
            "href" => "{$baseUrl}/toepassingen",
            "title" => "Alle toepassingen in dit algoritmeregister"
        ],
        "ar:events" => [
            "href" => "{$baseUrl}/events/{$id}",
            "title" => "Alle events voor toepassing {$toepassing["naam"]["waarde"]}"
        ]
    ];
    $response->getBody()->write(json_encode(
        $toepassing
    ));
    return $response->withHeader('Content-Type', 'application/hal+json');
});

$app->post('/toepassingen', function (Request $request, Response $response, $args) use ($algoritmeregister) {
    $baseUrl = getBaseUrl($request);
    $toepassing = $algoritmeregister->createToepassing($request->getParsedBody(), $request->getUri());
    $toepassing["_links"] = [
        "self" => [
            "href" => "{$baseUrl}/toepassingen/{$toepassing["uuid"]["waarde"]}"
        ],
        "curies" => [
            [
                "name" => "ar",
                "href" => "{$baseUrl}/docs/{rel}",
                "templated" => true
            ]
        ],
        "ar:toepassingen" => [
            "href" => "{$baseUrl}/toepassingen",
            "title" => "Alle toepassingen in dit algoritmeregister"
        ]
    ];
    $response->getBody()->write(json_encode(
        $toepassing
    ));
    return $response->withHeader('Content-Type', 'application/hal+json');
});

$app->put('/toepassingen/{id}', function (Request $request, Response $response, $args) use ($algoritmeregister) {
    $baseUrl = getBaseUrl($request);
    $token = $request->getQueryParams()["token"];
    $toepassing = $algoritmeregister->updateToepassing($args['id'], $request->getParsedBody(), $token);
    $toepassing["_links"] = [
        "self" => [
            "href" => "{$baseUrl}/toepassingen/{$toepassing["uuid"]}"
        ],
        "curies" => [
            [
                "name" => "ar",
                "href" => "{$baseUrl}/docs/{rel}",
                "templated" => true
            ]
        ],
        "ar:toepassingen" => [
            "href" => "{$baseUrl}/toepassingen",
            "title" => "Alle toepassingen in dit algoritmeregister"
        ]
    ];
    $response->getBody()->write(json_encode(
        $toepassing
    ));
    return $response->withHeader('Content-Type', 'application/hal+json');
});

$app->delete('/toepassingen/{id}', function (Request $request, Response $response, $args) use ($algoritmeregister) {
    $token = $request->getQueryParams()["token"];
    $algoritmeregister->deleteToepassing($args['id'], $token);
    return $response->withStatus('204');
});

$app->get('/events/{id}', function (Request $request, Response $response, $args) use ($algoritmeregister) {
    $baseUrl = getBaseUrl($request);
    $id = $args['id'];
    $events = $algoritmeregister->listEvents($id);
    $response->getBody()->write(json_encode([
        "_links" => [
            "self" => [
                "href" => "{$baseUrl}/toepassingen",
                "title" => "Alle toepassingen in dit algoritmeregister"
            ],
            "curies" => [
                [
                    "name" => "ar",
                    "href" => "{$baseUrl}/docs/{rel}",
                    "templated" => true
                ]
            ],
            "ar:algoritmeregister" => [
                "href" => "{$baseUrl}/",
                "title" => "Algoritmeregister"
            ],
            "ar:toepassing" => [
                "href" => "{$baseUrl}/toepassingen/{$id}",
                "title" => "Detailpagina voor toepassing {$id}"
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
