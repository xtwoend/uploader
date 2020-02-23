<?php

namespace App;

use App\Uploader;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use League\Plates\Engine;
use Medoo\Medoo;

/**
 * 
 */
class Controller
{
	protected $views;
	protected $db;
	protected $config;
	protected $s3;

	public function __construct()
	{
		$this->config = require_once('config.php');
		$this->views = new Engine('resources/views', 'html');
		$this->db = new Medoo($this->config['database']);
		$this->s3 = new S3Client($this->config['aws']);
	}

	public function index()
	{
		return $this->views->render('home');
	}

	public function view($post)
	{
		$file = $this->db->get('files', '*', ['filename' => $post]);
		if(! (count($file) > 0))
			header("location: /");


		return $this->views->render('preview', ['file' => (object) $file]);
	}

	public function show($group, $post)
	{
		$files = $this->db->select('files', '*', ['group' => $group]);
		if(! (count($files) > 0))
			header("location: /");

		return $this->views->render('show', compact('files'));
	}

	public function file($post, $name)
	{
		$file = $this->db->get('files', '*', ['filename' => $post]);
		if(! (count($file) > 0))
			header("location: /");

		
	}

	public function gallery($group)
	{
		$files = $this->db->select('files', '*', ['group' => $group]);
		if(! (count($files) > 0))
			header("location: /");

		return $this->views->render('gallery', compact('files'));
	}

	public function destroy($id)
	{
		$file = $this->db->get('files', '*', ['id' => $id]);
		if($file){
			$file = (object) $file;
		
			$fs = $this->filesystem($file->bucket);
			$c = $fs->delete($file->path);
			$t = $fs->delete('thumb/'.$file->path);
			if($c) {
				$this->db->delete('files', ['id' => $file->id]);
				return json_encode(['success' => true]);
			}
		}
		return json_encode(['success' => false]);
	}

	public function del($post)
	{
		$file = $this->db->get('files', '*', ['filename' => $post]);
		if(! (count($file) > 0))
			header("location: /");

		return $this->views->render('delete', ['file' => (object) $file]);
	}

	public function download($post)
	{
		$file = $this->db->get('files', '*', ['filename' => $post]);
		if(! (count($file) > 0))
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

	public function upload()
	{
		$buckets = $this->config['buckets'];
		$i = rand(0, count($buckets) -1 );
		$bucket = $buckets[$i];
		$now = date('Y-m-d H:i:s');

		$session = $_REQUEST['session'];
		$expired = (int) $_REQUEST['expired']?? 0;
		
		$config = [
			'aws' => $this->config['aws'],
			'bucket' => $bucket,
			'expired' => $expired,
			'thumbnail_size' => $this->config['thumbnail']['size']?? 256
		];
		
		$uploader = new Uploader($config);
		$response = $uploader->getResponse();

		$obj=[];
		foreach($response as $file) {
			$obj = [
			    'group' => $session,
			    'name' => $file->name,
			    'filename' => $file->hasKey,
			    'path' =>  $file->key,
			    'size' => $file->size,
			    'width' => $file->width?? 0,
			    'height' => $file->height?? 0,
			    'type' => $file->type,
			    'bucket' => $file->bucket,
			    'url' => $file->url,
			    'thumb' => $file->thumb,
			    'expired' => ($expired > 0)? date('Y-m-d', strtotime($now ." + {$expired} days")): null,
			    'created_at' => $now,
			    'updated_at' => $now,
			];
			$this->db->insert('files', $obj);
		}

		return json_encode($obj);
	}

	protected function filesystem($bucket)
	{
		$adapter = new AwsS3Adapter($this->s3, $bucket);
        return new Filesystem($adapter);
	}
}