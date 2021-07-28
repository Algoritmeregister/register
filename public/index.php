<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Tiltshift\Algoritmeregister\Algoritmeregister;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

$app->addBodyParsingMiddleware(); // needed for PUT payload

$algoritmeregister = new Algoritmeregister(__DIR__ . "/../storage/"); // FIXME config

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

$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write(json_encode([
        "_links" => [
            "self" => [
                "href" => "/",
                "title" => "Algoritmeregister home"
            ],
            "toepassingen" => [
                "href" => "/toepassingen",
                "title" => "Alle toepassingen in dit algoritmeregister"
            ]
        ]
    ]));
    return $response->withHeader('Content-Type', 'application/hal+json');
});

$app->get('/toepassingen', function (Request $request, Response $response, $args) use ($algoritmeregister) {
    $toepassingen = $algoritmeregister->listToepassingen();
    foreach ($toepassingen as &$toepassing) {
        $toepassing["_links"] = [
            "self" => [
                "href" => "/toepassingen/{$toepassing["id"]}",
                "title" => "Detailpagina voor toepassing {$toepassing["naam"]}"
            ]
        ];
    }
    $response->getBody()->write(json_encode([
        "_links" => [
            "self" => [
                "href" => "/toepassingen",
                "title" => "Alle toepassingen in dit algoritmeregister"
            ],
            "home" => [
                "href" => "/",
                "title" => "Algoritmeregister home"
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
    $id = $args['id'];
    $toepassing = $algoritmeregister->readToepassing($id);
    $toepassing["_links"] = [
        "self" => [
            "href" => "/toepassingen/{$id}",
            "title" => "Detailpagina voor toepassing {$toepassing["naam"]["waarde"]}"
        ],
        "toepassingen" => [
            "href" => "/toepassingen",
            "title" => "Alle toepassingen in dit algoritmeregister"
        ]
    ];
    $response->getBody()->write(json_encode(
        $toepassing
    ));
    return $response->withHeader('Content-Type', 'application/hal+json');
});

$app->post('/toepassingen', function (Request $request, Response $response, $args) use ($algoritmeregister) {
    $toepassing = $algoritmeregister->createToepassing($request->getParsedBody(), $request->getUri());
    $toepassing["_links"] = [
        "self" => [
            "href" => "/toepassingen/{$toepassing["uuid"]["waarde"]}"
        ],
        "toepassingen" => [
            "href" => "/toepassingen",
            "title" => "Alle toepassingen in dit algoritmeregister"
        ]
    ];
    $response->getBody()->write(json_encode(
        $toepassing
    ));
    return $response->withHeader('Content-Type', 'application/hal+json');
});

$app->put('/toepassingen/{id}', function (Request $request, Response $response, $args) use ($algoritmeregister) {
    $toepassing = $algoritmeregister->updateToepassing($args['id'], $request->getParsedBody());
    $toepassing["_links"] = [
        "self" => [
            "href" => "/toepassingen/{$toepassing["uuid"]}"
        ],
        "toepassingen" => [
            "href" => "/toepassingen",
            "title" => "Alle toepassingen in dit algoritmeregister"
        ]
    ];
    $response->getBody()->write(json_encode(
        $toepassing
    ));
    return $response->withHeader('Content-Type', 'application/hal+json');
});

$app->delete('/toepassingen/{id}', function (Request $request, Response $response, $args) use ($algoritmeregister) {
    $algoritmeregister->deleteToepassing($args['id']);
    return $response->withStatus('204');
});

$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function ($request, $response) {
    throw new HttpNotFoundException($request);
});

$app->run();
