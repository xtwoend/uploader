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
        if($this->isAjax()){
            $json = file_get_contents('php://input');
            $data = json_decode($json);
            if(isset($data->csrf) && $data->csrf === $_SESSION['csrf']){
                return $handler->handle($request);
            }
        }

    	if(isset($_REQUEST['csrf']) && ($_REQUEST['csrf'] === $_SESSION['csrf'])){
            return $handler->handle($request);
		}

       $response = new \Laminas\Diactoros\Response;
        $response->getBody()->write(json_encode(['error' => 'forbidden']));
        return $response->withAddedHeader('content-type', 'application/json')->withStatus(403);
    }

    public function headers($ky)
    {
        $headers = [];
        foreach (getallheaders() as $key => $value) {
            $headers[$key] = $value;
        }

        return $headers[$ky]?? null;
    }
    public function isAjax()
    {
        return $this->headers('X-Requested-With') === 'XMLHttpRequest';   
    }
}