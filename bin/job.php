#!/usr/bin/env php
<?php

use Aws\S3\S3Client;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use Medoo\Medoo;

require __DIR__.'/../vendor/autoload.php';

$config = require_once(__DIR__.'/../config.php');

$db = new Medoo($config['database']);
$s3 = new S3Client($config['aws']);

$files = $db->select('files', '*', ['expired' => date('Y-m-d')]);
foreach($files as $file){
	$file = (object) $file;
	$fs = new Filesystem(new AwsS3Adapter($s3, $file->bucket));
	$c = $fs->delete($file->path);
	$t = $fs->delete('thumb/'.$file->path);
	if($c) {
		$db->delete('files', ['id' => $file->id]);
		echo 'delete success';
	}
}

