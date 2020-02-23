<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';

@session_start();

if (! isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = base64_encode(openssl_random_pseudo_bytes(32));
}

$request = Laminas\Diactoros\ServerRequestFactory::fromGlobals(
    $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
);

$router = new League\Route\Router;

$router->get('/', 'App\Controller::index');
$router->post('/upload', 'App\Controller::upload')->middleware(new App\CsrfMiddleware);
$router->get('/gallery/{group}', 'App\Controller::gallery');
$router->get('/delete/{id:number}', 'App\Controller::del');
$router->get('/download/{post}', 'App\Controller::download');
$router->get('/{post}', 'App\Controller::view');
$router->get('/{group}/{post}', 'App\Controller::show');

$response = $router->dispatch($request);

(new Laminas\HttpHandlerRunner\Emitter\SapiEmitter)->emit($response);