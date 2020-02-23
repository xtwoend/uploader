<?php

namespace App;

use App\Thumb;
use Aws\S3\S3Client;
use League\Flysystem\AdapterInterface;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\FileExistsException;
use League\Flysystem\Filesystem;
use WebThumbnailer\WebThumbnailer;

class Uploader
{
    const DS = DIRECTORY_SEPARATOR;

    /**
     * @var  Filesystem
     */
    private $filesystem;
    private $name;
    private $bucket;
    private $prefix;
    private $extension;
    private $hasKey;
    private $thumbnail_size;

    protected $response;
    /**
     * Dropzone constructor.
     * @param Filesystem $filesystem
     */
    public function __construct($config)
    {
        $this->name = time();
        $this->bucket = $config['bucket'];
        $this->thumbnail_size = $config['thumbnail_size'];

        $client = new S3Client($config['aws']);
        $adapter = new AwsS3Adapter($client, $this->bucket);

        $this->filesystem = new Filesystem($adapter);
        $this->prefix = date('Y/m/d/');
        $this->hasKey = str_random(10);
        $this->upload();
    }

    public function upload()
    {
    	$upload = isset($_FILES['files']) ? $_FILES['files'] : null;

    	$files = [];
    	
		if ($upload || isset($_SERVER['HTTP_X_FILE_NAME'])) {
			$fileTempName = $upload['tmp_name'];
			$stream = fopen($fileTempName, 'r+');
			$fileName = (isset($_SERVER['HTTP_X_FILE_NAME']) ? $_SERVER['HTTP_X_FILE_NAME'] : $upload['name']);
        	$keyName =  $this->getKeyName($stream);

            list($width, $height) = getimagesize($fileTempName);
            
        	$response = $this->filesystem->writeStream($keyName, $stream, [
        		'visibility' => AdapterInterface::VISIBILITY_PUBLIC
        	]);
            if($response){
            	$file = $this->fileInfo($keyName);
            	$file['name'] = $fileName;
            	$file['key'] = $keyName;
                $file['width'] = $width;
                $file['height'] = $height;
            	$file['hasKey'] = $this->hasKey;
                $file['thumb'] = $this->createThumbnail($file, $keyName);
            	$files[] = (object) $file;
            }
		}

    	$this->response = $files;

    	return $files;
    }

    public function createThumbnail($file, $keyName)
    {
        try{
            $blob = file_get_contents($file['url']);

            $imagick = new \Imagick();
            $imagick->readImageBlob($blob);

            if($imagick){
                
                $contentType = $imagick->getImageMimeType();
                $imagick->scaleImage($this->thumbnail_size, 0);

                $result = $this->filesystem->getAdapter()->getClient()->putObject([
                    'ContentType' => $contentType,
                    'Body' => $imagick->getImageBlob(),
                    'Bucket' => $this->bucket,
                    'Key' => 'thumb/'. $keyName,
                    'StorageClass' => 'REDUCED_REDUNDANCY',
                    'Tagging' => 'thumbnail=yes',
                    'ACL' => 'public-read'
                ]);

                $url = $result['ObjectURL'];
                return $url;
            }
        }
        catch (\Exception $e) {
            
        }
    }

    public function getResponse()
    {
    	return $this->response;
    }

    private function fileInfo($path)
    {
    	return [
    		'url' 	=> $this->filesystem->getAdapter()->getClient()->getObjectUrl($this->bucket, $path),
    		'type' 	=> $this->filesystem->getMimetype($path),
    		'bucket' => $this->bucket,
    		'size' 	=> $this->filesystem->getSize($path)
    	];
    }

    private function exists($path)
    {
    	return $this->filesystem->has($path);
    }

    private function getKeyName($file)
    {	
    	$extension = $this->mime2ext(mime_content_type($file));
	            
    	$name = $this->hasKey . ".{$extension}";
    	if($this->exists($this->prefix . $name)){
    		$this->hasKey = str_random(10);
    		return $this->getKeyName($file);
    	}

    	return $this->prefix . $name;
    }

    protected function hasImageFileExtension($file_path) {
        return !!preg_match('/\.(gif|jpe?g|png)$/i', $file_path);
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