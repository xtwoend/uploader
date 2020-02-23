<?php declare(strict_types=1);

namespace App;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {	
    	if(! isset($_REQUEST['csrf']) && ($_REQUEST['csrf'] !== $_SESSION['csrf'])){
			$response = new \Laminas\Diactoros\Response;
		    $response->getBody()->write(json_encode(['error' => 'forbidden']));
			return $response->withAddedHeader('content-type', 'application/json')->withStatus(403);
		}

        // invoke the rest of the middleware stack and your controller resulting
        // in a returned response object
        return $handler->handle($request);
    }
}