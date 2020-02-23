<?php

require 'vendor/autoload.php';

@session_start();
if (! isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = base64_encode(openssl_random_pseudo_bytes(32));
}

$router = new \Buki\Router([
  	'paths' => [
		'controllers' => 'src',
		'middlewares' => 'src',
  	],
  	'namespaces' => [
		'controllers' => 'App',
		'middlewares' => 'App',
  	],
]);


$router->get('/', 'Controller@index');
$router->post('/upload', ['before' => 'CsrfMiddleware'], 'Controller@upload');
$router->get('/gallery/:string', 'Controller@gallery');
$router->get('/delete/:string', 'Controller@del');
$router->post('/destroy/:id', 'Controller@destroy');
$router->get('/download/:string', 'Controller@download');
$router->get('/:string/:string', 'Controller@show');
$router->get('/:string', 'Controller@view');
$router->get('/i/:string/:all?', 'Controller@file');

$router->error(function() {
	http_response_code(404);
	$views = new League\Plates\Engine('resources/views', 'html');
	echo $views->render('404');
});

$router->run();