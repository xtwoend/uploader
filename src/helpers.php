<?php

function str_random($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function siteURL()
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'].'/';
    return $protocol.$domainName;
}

function url($path)
{
	return siteURL().$path;
}

function thumbUrl($bucket, $path)
{
    return 'http://'. $bucket. '/thumb/'. $path;
}

function imageUrl($bucket, $path)
{
    return 'http://'. $bucket. '/'. $path;
}

if( !function_exists('mime_content_type')) {
    function mime_content_type( $filename ) {
        $finfo = finfo_open( FILEINFO_MIME_TYPE );
        $mime_type = finfo_file( $finfo, $filename );
        finfo_close( $finfo );
        return $mime_type;
    }
}

function view($template, $data = [])
{
    $response = new \Laminas\Diactoros\Response;
    $view = new \League\Plates\Engine('resources/views', 'html');

    $response->getBody()->write($view->render($template, $data));

    return $response;
}