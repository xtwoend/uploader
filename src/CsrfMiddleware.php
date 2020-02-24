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
    	if(isset($_REQUEST['csrf']) && ($_REQUEST['csrf'] === $_SESSION['csrf'])){
            return $handler->handle($request);
		}

       $response = new \Laminas\Diactoros\Response;
        $response->getBody()->write(json_encode(['error' => 'forbidden']));
        return $response->withAddedHeader('content-type', 'application/json')->withStatus(403);
    }
}