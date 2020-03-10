<?php

namespace App;

use App\Uploader;
use Aws\S3\S3Client;
use EddTurtle\DirectUpload\Signature;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use League\Plates\Engine;
use Medoo\Medoo;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Intervention\Image\ImageManager;

/**
 * 
 */
class Controller
{
	protected $views;
	protected $db;
	protected $config;
	protected $s3;
	protected $img;

	public function __construct()
	{
		$this->config = require_once('config.php');
		$this->views = new Engine('resources/views', 'html');
		$this->db = new Medoo($this->config['database']);
		$this->s3 = new S3Client($this->config['aws']);
		$this->img = new ImageManager(array('driver' => 'imagick'));
	}

	public function index(): ResponseInterface
	{
		$count = $this->db->count('files');
		return view('home', compact('count'));
	}

	public function signature(ServerRequestInterface $request): ResponseInterface
	{
		$buckets = $this->config['buckets'];
		$i = rand(0, count($buckets) -1);
		$bucket = $buckets[$i];

		$prefix = date('Y/m/d/');
        $hasKey = str_random(10);

        $extension = '.'.$this->mime2ext($_POST['contentType']);

		$upload = new Signature(
		    $this->config['aws']['credentials']['key'],
		    $this->config['aws']['credentials']['secret'],
		    $bucket,
		    'ap-southeast-1',
		    [
		    	'acl' => 'public-read-write',
		    	'additional_inputs' => [
		    		'key'=> $prefix.$hasKey.$extension,
		    		'name' => $_POST['filePath'],
		    		'filename' => $hasKey,
		    		'bucket' => $bucket,
		    		'contentType' => $_POST['contentType'],
		    		'size' => $_POST['fileSize'],
		    		'session' => $_POST['session'],
		    		'expired' => $_POST['expired']??0
		    	]
		    ]
		);

		$signature = [
			'signature' => $upload->getFormInputs(false),
			'postEndpoint' => $upload->getFormUrl()
		];

		$response = new \Laminas\Diactoros\Response;
	    $response->getBody()->write(json_encode($signature));
		return $response->withAddedHeader('content-type', 'application/json')->withStatus(200);
	}

	public function view(ServerRequestInterface $request, $args): ResponseInterface
	{	
		$file = $this->db->get('files', '*', ['filename' => $args['post']]);

		if(! $file )
			header("location: /");

		return view('preview', ['file' => (object) $file]);
	}

	public function show(ServerRequestInterface $request, $args): ResponseInterface
	{
		$group = $args['group'];
		$files = $this->db->select('files', '*', ['group' => $group]);

		if(! $files)
			header("location: /");

		return view('show', compact('files'));
	}

	public function gallery(ServerRequestInterface $request, $args): ResponseInterface
	{
		$group = $args['group'];

		$files = $this->db->select('files', '*', ['group' => $group]);
		if(! $files)
			header("location: /");

		return view('gallery', compact('files'));
	}

	public function destroy(ServerRequestInterface $request, $args): ResponseInterface
	{
		$file = $this->db->get('files', '*', ['id' => $args['id']]);
		$response = new \Laminas\Diactoros\Response;
	    
		if($file){
			$file = (object) $file;
		
			$fs = $this->filesystem($file->bucket);
			$c = $fs->delete($file->path);
			$t = $fs->delete('thumb/'.$file->path);
			if($c) {
				$this->db->delete('files', ['id' => $file->id]);
				$response->getBody()->write(json_encode(['success' => true]));
				return $response->withAddedHeader('content-type', 'application/json')->withStatus(200);
			}
		}
		$response->getBody()->write(json_encode(['success' => false]));
		return $response->withAddedHeader('content-type', 'application/json')->withStatus(200);
	}

	public function del(ServerRequestInterface $request, $args): ResponseInterface
	{
		$post = $args['post'];
		$file = $this->db->get('files', '*', ['filename' => $post]);
		if(! $file)
			header("location: /");

		return view('delete', ['file' => (object) $file]);
	}

	public function download(ServerRequestInterface $request, $args): ResponseInterface
	{
		$post = $args['post'];
		$file = $this->db->get('files', '*', ['filename' => $post]);
		if(! $file)
			header("location: /");
		
		$file = (object) $file;
		$result = $this->s3->getObject(['Bucket' => $file->bucket, 'Key' => $file->path]);

		header("Content-Disposition: attachment; filename={$file->name};");
		header("Content-Type: {$result['ContentType']}");
		header('Content-Length: ' . $result['ContentLength']);
		echo $result['Body'];
		flush();
		exit;
	}

	public function upload(ServerRequestInterface $request): ResponseInterface
	{
		// $buckets = $this->config['buckets'];
		// $i = rand(0, count($buckets) -1 );
		// $bucket = $buckets[$i];
		$now = date('Y-m-d H:i:s');

		$session = $_REQUEST['session'];
		$expired = (int) $_REQUEST['expired']?? 0;
		$input = $_REQUEST;

		$config = [
			'aws' => $this->config['aws'],
			'bucket' => $input['bucket']?? '',
			'expired' => $expired,
			'thumbnail_size' => $this->config['thumbnail']['size']?? 256
		];
		
		$uploader = new Uploader($config);
		$response = $uploader->getResponse();

		$obj=[];
		foreach($response as $file) {
			$obj = [
			    'group' => $session,
			    'name' => $input['name']?? '',
			    'filename' => $input['filename']?? '',
			    'path' =>  $input['key']?? '',
			    'size' => $input['size']?? '',
			    'width' => 0,
			    'height' => 0,
			    'type' => $file->type,
			    'bucket' => $input['bucket']?? '',
			    'url' => $input['s3ObjectLocation']?? '',
			    'thumb' => $file->thumb,
			    'expired' => ($expired > 0)? date('Y-m-d', strtotime($now ." + {$expired} days")): null,
			    'created_at' => $now,
			    'updated_at' => $now,
			];
			$this->db->insert('files', $obj);
		}

		$response = new \Laminas\Diactoros\Response;
	    $response->getBody()->write(json_encode($obj));
		return $response->withAddedHeader('content-type', 'application/json')->withStatus(200);
	}

	public function upData(ServerRequestInterface $request): ResponseInterface
	{
		$json = file_get_contents('php://input');
        $data = json_decode($json);
        $now = date('Y-m-d H:i:s');
        $file = $data->file;
        $expired = $file->expired;

		// $obj=[];
		// $objs=[];
		// foreach($data->files as $file) {
		// 	$expired = $file->expired;
			$obj = [
			    'group' => $file->session,
			    'name' => $file->name,
			    'filename' => $file->filename,
			    'path' =>  $file->key,
			    'size' => $file->size,
			    'width' => $file->width,
			    'height' => $file->height,
			    'type' => $file->type?? '',
			    'bucket' => $file->bucket,
			    'url' => $file->url,
			    'thumb' => $this->createTumb($file),
			    'expired' => ($expired > 0)? date('Y-m-d', strtotime($now ." + {$expired} days")): null,
			    'created_at' => $now,
			    'updated_at' => $now,
			];
			$this->db->insert('files', $obj);
		
		// }

		$response = new \Laminas\Diactoros\Response;
	    $response->getBody()->write(json_encode($obj));
		return $response->withAddedHeader('content-type', 'application/json')->withStatus(200);
	}

	protected function filesystem($bucket)
	{
		$adapter = new AwsS3Adapter($this->s3, $bucket);
        return new Filesystem($adapter);
	}

	protected function createTumb($file)
	{
		// try {
		// 	$blob = file_get_contents($file->url);
		// 	$imagick = new \Imagick();
	 //        $imagick->readImageBlob($blob);

	 //        if($imagick){
	 //        	$contentType = $imagick->getImageMimeType();
	 //            $imagick->scaleImage($this->config['thumbnail']['size']?? 300, 0);

	 //            $result = $this->filesystem($file->bucket)->getAdapter()->getClient()->putObject([
	 //                'ContentType' => $contentType,
	 //                'Body' => $imagick->getImageBlob(),
	 //                'Bucket' => $file->bucket,
	 //                'Key' => 'thumb/'. $file->key,
	 //                'StorageClass' => 'REDUCED_REDUNDANCY',
	 //                'Tagging' => 'thumbnail=yes',
	 //                'ACL' => 'public-read'
	 //            ]);

	 //          	return $result['ObjectURL'];
	 //        }
		// } catch (\Exception $e) {
			
		// }
		return url('i/'.$file->filename);
	}

	public function thumb(ServerRequestInterface $request, $args): ResponseInterface
	{
		$post = $args['post'];
		$file = $this->db->get('files', '*', ['filename' => $post]);
		if($file){
			$image = $this->img->make($file['url'])->resize(300, null, function($c){
				$c->aspectRatio();
			});

			echo $image->response('jpg', 90);
		}
		return view('404', [], 404);
	}

	protected function mime2ext($mime)
    {
		$all_mimes = '{"png":["image\/png","image\/x-png"],"bmp":["image\/bmp","image\/x-bmp","image\/x-bitmap","image\/x-xbitmap","image\/x-win-bitmap","image\/x-windows-bmp","image\/ms-bmp","image\/x-ms-bmp","application\/bmp","application\/x-bmp","application\/x-win-bitmap"],"gif":["image\/gif"],"jpeg":["image\/jpeg","image\/pjpeg"],"xspf":["application\/xspf+xml"],"vlc":["application\/videolan"],"wmv":["video\/x-ms-wmv","video\/x-ms-asf"],"au":["audio\/x-au"],"ac3":["audio\/ac3"],"flac":["audio\/x-flac"],"ogg":["audio\/ogg","video\/ogg","application\/ogg"],"kmz":["application\/vnd.google-earth.kmz"],"kml":["application\/vnd.google-earth.kml+xml"],"rtx":["text\/richtext"],"rtf":["text\/rtf"],"jar":["application\/java-archive","application\/x-java-application","application\/x-jar"],"zip":["application\/x-zip","application\/zip","application\/x-zip-compressed","application\/s-compressed","multipart\/x-zip"],"7zip":["application\/x-compressed"],"xml":["application\/xml","text\/xml"],"svg":["image\/svg+xml"],"3g2":["video\/3gpp2"],"3gp":["video\/3gp","video\/3gpp"],"mp4":["video\/mp4"],"m4a":["audio\/x-m4a"],"f4v":["video\/x-f4v"],"flv":["video\/x-flv"],"webm":["video\/webm"],"aac":["audio\/x-acc"],"m4u":["application\/vnd.mpegurl"],"pdf":["application\/pdf","application\/octet-stream"],"pptx":["application\/vnd.openxmlformats-officedocument.presentationml.presentation"],"ppt":["application\/powerpoint","application\/vnd.ms-powerpoint","application\/vnd.ms-office","application\/msword"],"docx":["application\/vnd.openxmlformats-officedocument.wordprocessingml.document"],"xlsx":["application\/vnd.openxmlformats-officedocument.spreadsheetml.sheet","application\/vnd.ms-excel"],"xl":["application\/excel"],"xls":["application\/msexcel","application\/x-msexcel","application\/x-ms-excel","application\/x-excel","application\/x-dos_ms_excel","application\/xls","application\/x-xls"],"xsl":["text\/xsl"],"mpeg":["video\/mpeg"],"mov":["video\/quicktime"],"avi":["video\/x-msvideo","video\/msvideo","video\/avi","application\/x-troff-msvideo"],"movie":["video\/x-sgi-movie"],"log":["text\/x-log"],"txt":["text\/plain"],"css":["text\/css"],"html":["text\/html"],"wav":["audio\/x-wav","audio\/wave","audio\/wav"],"xhtml":["application\/xhtml+xml"],"tar":["application\/x-tar"],"tgz":["application\/x-gzip-compressed"],"psd":["application\/x-photoshop","image\/vnd.adobe.photoshop"],"exe":["application\/x-msdownload"],"js":["application\/x-javascript"],"mp3":["audio\/mpeg","audio\/mpg","audio\/mpeg3","audio\/mp3"],"rar":["application\/x-rar","application\/rar","application\/x-rar-compressed"],"gzip":["application\/x-gzip"],"hqx":["application\/mac-binhex40","application\/mac-binhex","application\/x-binhex40","application\/x-mac-binhex40"],"cpt":["application\/mac-compactpro"],"bin":["application\/macbinary","application\/mac-binary","application\/x-binary","application\/x-macbinary"],"oda":["application\/oda"],"ai":["application\/postscript"],"smil":["application\/smil"],"mif":["application\/vnd.mif"],"wbxml":["application\/wbxml"],"wmlc":["application\/wmlc"],"dcr":["application\/x-director"],"dvi":["application\/x-dvi"],"gtar":["application\/x-gtar"],"php":["application\/x-httpd-php","application\/php","application\/x-php","text\/php","text\/x-php","application\/x-httpd-php-source"],"swf":["application\/x-shockwave-flash"],"sit":["application\/x-stuffit"],"z":["application\/x-compress"],"mid":["audio\/midi"],"aif":["audio\/x-aiff","audio\/aiff"],"ram":["audio\/x-pn-realaudio"],"rpm":["audio\/x-pn-realaudio-plugin"],"ra":["audio\/x-realaudio"],"rv":["video\/vnd.rn-realvideo"],"jp2":["image\/jp2","video\/mj2","image\/jpx","image\/jpm"],"tiff":["image\/tiff"],"eml":["message\/rfc822"],"pem":["application\/x-x509-user-cert","application\/x-pem-file"],"p10":["application\/x-pkcs10","application\/pkcs10"],"p12":["application\/x-pkcs12"],"p7a":["application\/x-pkcs7-signature"],"p7c":["application\/pkcs7-mime","application\/x-pkcs7-mime"],"p7r":["application\/x-pkcs7-certreqresp"],"p7s":["application\/pkcs7-signature"],"crt":["application\/x-x509-ca-cert","application\/pkix-cert"],"crl":["application\/pkix-crl","application\/pkcs-crl"],"pgp":["application\/pgp"],"gpg":["application\/gpg-keys"],"rsa":["application\/x-pkcs7"],"ics":["text\/calendar"],"zsh":["text\/x-scriptzsh"],"cdr":["application\/cdr","application\/coreldraw","application\/x-cdr","application\/x-coreldraw","image\/cdr","image\/x-cdr","zz-application\/zz-winassoc-cdr"],"wma":["audio\/x-ms-wma"],"vcf":["text\/x-vcard"],"srt":["text\/srt"],"vtt":["text\/vtt"],"ico":["image\/x-icon","image\/x-ico","image\/vnd.microsoft.icon"],"csv":["text\/x-comma-separated-values","text\/comma-separated-values","application\/vnd.msexcel"],"json":["application\/json","text\/json"]}';

		$all_mimes = json_decode($all_mimes,true);

		foreach ($all_mimes as $key => $value) {
			if(array_search($mime,$value) !== false) return $key;
		}

		return false;
	}
}