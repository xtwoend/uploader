<?php

namespace App;

/**
 * 
 */
class CsrfMiddleware
{
	
	public function handle()
	{
		if(! isset($_REQUEST['csrf']) && ($_REQUEST['csrf'] !== $_SESSION['csrf'])){
			http_response_code(403);
			return json_encode(['error']);
		}

		return true;
	}
}