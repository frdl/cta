<?php 


namespace frdl\cta;

use Exception;
use SplFileObject;
use Webfan\cta\HashType\HashTypeInterface;
use Webfan\cta\HashType\XHashSha1;

class Server
{
     const XPoweredBy = 'Webfan CTA Storage Server '.__CLASS__;
     const URIS_DIR = 'URIS_DIR';
     const CHUNKS_DIR = 'CHUNKS_DIR';
     const FILES_DIR = 'FILES_DIR';
     const HEADERS_FILE = 'h.txt';
     const FILE_HASH = 'f.txt';
     const HEADERS_AND_CHUNK_HASHES = 'hc.txt';
     const CHUNK_HASHES_FILE = 'c.txt';
     const CHUNK_CONTENTS_FILE = 'b.dat';
     const URI_REVERSE_FILE = 'r.txt';
     const WEAK_COUNTER_FILE = 'hits.txt';
     const UNIQUE_COUNTER_FILE = 'visitors.txt';
	 const REFERENCES_DIR = 'refrences';
	 const EXPIRES_FILE = 'e.txt';
	 const LAST_WRITE_FILE = 't.txt';

    protected $config = [];

    public function __construct($config, $createDirectories = false)
    {
        $this->config = [
            'chunksize' => 80,
            'delimiter' => null, //\PHP_EOL  NOT GOOD! keep NULL for fixed chunksize!,
            HashTypeInterface::class => XHashSha1::class,
        ];
        if(is_array($config)){
          $this->config = array_merge($this->config, $config);
        }

        if(!isset($this->config[self::URIS_DIR]) || !isset($this->config[self::CHUNKS_DIR]) || !isset($this->config[self::FILES_DIR])
           ||
           (
               !$createDirectories &&
           (  !is_dir($this->config[self::URIS_DIR]) || !is_writable($this->config[self::URIS_DIR])
           || !is_dir($this->config[self::CHUNKS_DIR]) || !is_writable($this->config[self::CHUNKS_DIR])
           || !is_dir($this->config[self::FILES_DIR]) || !is_writable($this->config[self::FILES_DIR])
            )
          )
          ){
            throw new Exception(sprintf('You did not configure the storage directories correctly in %s in %d!', __CLASS__, __LINE__));
        }

        if(true===$createDirectories){
           $this->createDirectories();
        }

        $class = $this->config[HashTypeInterface::class];
        if( true !== (new $class()) instanceof HashTypeInterface ){
            throw new Exception($class.' must implement '.HashTypeInterface::class);
        }
    }

    public function serializeChunk($chunk)
    {
        $bin=new \frdl\webfan\Serialize\Binary\bin;
        $chunk = $bin->serialize([
            'chunk'=>$chunk,
        ]);
        $serialized = base64_encode($chunk);
        return $serialized;
    }

    public function unserializeChunk($serialized)
    {
        $bin=new \frdl\webfan\Serialize\Binary\bin;
        $chunk = base64_decode($serialized);
        $data = $bin->unserialize($chunk);
        $chunk = $data['chunk'];
        return $chunk;
    }

    protected function createDirectories()
    {
        if(!is_dir($this->config[self::URIS_DIR])){
                    mkdir($this->config[self::URIS_DIR], 0755, true);
                }elseif(!is_writable($this->config[self::URIS_DIR]) ){
                    chmod($this->config[self::URIS_DIR], 0755);
                }

                if(!is_dir($this->config[self::CHUNKS_DIR])){
                    mkdir($this->config[self::CHUNKS_DIR], 0755, true);
                }elseif(!is_writable($this->config[self::CHUNKS_DIR]) ){
                    chmod($this->config[self::CHUNKS_DIR], 0755);
                }

                if(!is_dir($this->config[self::FILES_DIR])){
                    mkdir($this->config[self::FILES_DIR], 0755, true);
                }elseif(!is_writable($this->config[self::FILES_DIR]) ){
                    chmod($this->config[self::FILES_DIR], 0755);
                }
    }

    protected function d($hash)
    {
        $class = $this->config[HashTypeInterface::class];
        if(is_array($hash)){
            $hash = (new $class())->toString($hash, $this->config['delimiter']);
        }
        return $hash;
    }

    public function assoc(array $result): array
    {
        return [
            'uriHash' => $result[0],
            'hash' => $result[1],
            'chunks' => $result[2],
        ];
    }

    public function save(string $source, string $uri = null, array $headers = null, $touch = true, 
						 $assoc = true,
						 int $expiresAtTimestamp = null) : array
    {
        if(true === $touch){
           $fileModificationTime = gmdate('D, d M Y H:i:s', time()).' GMT';
        }

        $chunksDirectory = $this->config[self::CHUNKS_DIR];
        $chunkContentsFile = self::CHUNK_CONTENTS_FILE;
		$lastWriteFile =  self::LAST_WRITE_FILE;
        $me = &$this;
        $fn =function($XHash, $chunk, $i) use($chunksDirectory, $chunkContentsFile, $lastWriteFile, &$me) {
                                                            $chunkContentsDir = rtrim($chunksDirectory, '/\\ ')
                                                                . \DIRECTORY_SEPARATOR
                                                                . str_replace(['\\', '/'],
                                                                              [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR],
                                                                               trim($me->d($XHash))). \DIRECTORY_SEPARATOR;

                                                             if(!is_dir($chunkContentsDir)){
                                                                mkdir($chunkContentsDir, 0755, true);
                                                            }

                                                             $chunkFile = $chunkContentsDir.$chunkContentsFile;
                                                             file_put_contents($chunkFile, $me->serializeChunk($chunk));
			
			                                                 file_put_contents($chunkContentsDir.$lastWriteFile, time());
                                                 };
        list($uriHash, $hash,  $chunks) = $this->getHashes($source, $uri, $this->config['chunksize'],$this->config['delimiter'],$fn, false);


        $fileStorageDir = rtrim($this->config[self::URIS_DIR], '/\\ ')
            . \DIRECTORY_SEPARATOR . str_replace(['\\', '/'], [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR],
                                                 $this->d($hash)). \DIRECTORY_SEPARATOR;

        $uriDir = rtrim($this->config[self::URIS_DIR], '/\\ ')
            . \DIRECTORY_SEPARATOR . str_replace(['\\', '/'], [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR],
                                                 $this->d($uriHash)). \DIRECTORY_SEPARATOR;



        $reverseFile = $uriDir.self::URI_REVERSE_FILE;
        file_put_contents($reverseFile, $uri);


        if(!is_dir($uriDir)){
           mkdir($uriDir, 0755, true);
        }

        if(!is_dir($fileStorageDir)){
           mkdir($fileStorageDir, 0755, true);
        }

        $headersFile = $uriDir.self::HEADERS_FILE;
        $headersAndChunkHashesFile = $uriDir.self::HEADERS_AND_CHUNK_HASHES;
        if(file_exists($headersFile)){
          unlink($headersFile);
        }
        if(file_exists($headersAndChunkHashesFile)){
          unlink($headersAndChunkHashesFile);
        }

        $file = fopen($headersFile,"a");
        $file_ch = fopen($headersAndChunkHashesFile,"a");
        foreach($headers as $header){
            $h=explode(':', $header);
            if(
              (
                     'Content-Type' !== $h[0]
                  &&   'ETag' !== $h[0]
            //      && (true !== $touch && ('Last-Modified' !== $h[0] && 'Date' !== $h[0]))
               )
                 || // obsolete ?...:
               'HTTP//' === substr($h[0],0,strlen('HTTP//')) ||
               'Accept-' === substr($h[0],0,strlen('Accept-')) ||
               'Connection' === $h[0] ||
               'ETag' === $h[0] ||
               'Host' === $h[0] ||
               'Origin' === $h[0] ||
               'Content-Security-Policy' === $h[0] ||
               'Referrer-Policy' === $h[0] ||
               'Server' === $h[0] ||
               'Content-Security-Policy' === $h[0] ||
               'X-Powered-By' === $h[0] ||
               'Access-' === substr($h[0],0,strlen('Access-')) ||
               'X-' === substr($h[0],0,2) ||
                (true === $touch && ('Last-Modified' === $h[0] || 'Date' === $h[0]))
            ){
                continue;
            }
            fwrite($file,$header.\PHP_EOL);
            fwrite($file_ch,$header.\PHP_EOL);
        }
        if(true === $touch){
          fwrite($file,'Last-Modified: '.$fileModificationTime.\PHP_EOL);
          fwrite($file_ch,'Last-Modified: '.$fileModificationTime.\PHP_EOL);

          fwrite($file,'Date: '.$fileModificationTime.\PHP_EOL);
          fwrite($file_ch,'Date: '.$fileModificationTime.\PHP_EOL);

          fwrite($file,'X-Powered-By: '.self::XPoweredBy.\PHP_EOL);
          fwrite($file_ch,'X-Powered-By: '.self::XPoweredBy.\PHP_EOL);
        }
        fwrite($file_ch,\PHP_EOL);
        fclose($file);

        $fileHashFile = $uriDir.self::FILE_HASH;
        file_put_contents($fileHashFile, $this->d($hash));

        file_put_contents($fileStorageDir.self::FILE_HASH, $this->d($hash));
        $chunkHashesFile = $fileStorageDir.self::CHUNK_HASHES_FILE;

        if(file_exists($chunkHashesFile)){
          unlink($chunkHashesFile);
        }
        $file = fopen($chunkHashesFile,"a");
        foreach(array_keys($chunks) as $h){
            fwrite($file,$this->d($h).\PHP_EOL);
            fwrite($file_ch,$this->d($h).\PHP_EOL);
			
            $referenceFileDir = rtrim($this->config[self::CHUNKS_DIR], '/\\ ')
               . \DIRECTORY_SEPARATOR . str_replace(['\\', '/'], [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR],
                                                 $this->d($h)). \DIRECTORY_SEPARATOR
				.$this->config[self::REFERENCES_DIR]
               . \DIRECTORY_SEPARATOR
				.'f'
               . \DIRECTORY_SEPARATOR  . str_replace(['\\', '/'], [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR],
                                                 $this->d($hash)). \DIRECTORY_SEPARATOR;			
			
			
            $referenceUriDir = rtrim($this->config[self::CHUNKS_DIR], '/\\ ')
               . \DIRECTORY_SEPARATOR . str_replace(['\\', '/'], [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR],
                                                 $this->d($h)). \DIRECTORY_SEPARATOR
				.$this->config[self::REFERENCES_DIR]
               . \DIRECTORY_SEPARATOR
				.'u'
               . \DIRECTORY_SEPARATOR . str_replace(['\\', '/'], [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR],
                                                 $this->d($uriHash)). \DIRECTORY_SEPARATOR;			
			
           if(!is_dir($referenceFileDir)){
              mkdir($referenceFileDir, 0755, true);
           }

           if(!is_dir($referenceUriDir)){
               mkdir($referenceUriDir, 0755, true);    
		   }
			
			if(null !== $expiresAtTimestamp){
				file_put_contents($referenceFileDir.self::EXPIRES_FILE, $expiresAtTimestamp);
				file_put_contents($referenceUriDir.self::EXPIRES_FILE, $expiresAtTimestamp);
			}
			
        }
        fclose($file);
        fclose($file_ch);
		
		           
		$referenceUriDir = $fileStorageDir. \DIRECTORY_SEPARATOR
				.$this->config[self::REFERENCES_DIR]
               . \DIRECTORY_SEPARATOR
				.'u'
               . \DIRECTORY_SEPARATOR . str_replace(['\\', '/'], [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR],
                                                 $this->d($uriHash)). \DIRECTORY_SEPARATOR;			
					
		    if(null !== $expiresAtTimestamp){
				file_put_contents($referenceUriDir.self::EXPIRES_FILE, $expiresAtTimestamp);
			}
		
		file_put_contents($fileStorageDir.$lastWriteFile, time());
		file_put_contents($uriDir.$lastWriteFile, time());
		
        return true !== $assoc ? [$uriHash, $hash,  $chunks] : $this->assoc([$uriHash, $hash,  $chunks]);
    }

    public function getHashes(
        string $source,
        string $uri = null,
        int $chunksize = 80,
        string $delimiter = null,
        \callable|\closure $callback = null,
        $assoc = true
    ) : array {
        $me = &$this;
        $class = $this->config[HashTypeInterface::class];
        $XHashSha1 = new $class($uri);
        $uhash = $XHashSha1();

            $source = $this->serializeChunk($source);

        $chunks = [];
        $fn = function($XHash, $chunk, $i) use (&$chunks, $callback, &$me){
              $chunks[$me->d($XHash)] = $chunk;
              if(is_callable($callback)){
                 call_user_func_array($callback, [$XHash, $chunk, $i]);
              }
        };

        $hash = $this->getChunks($source, $fn, $chunksize, $delimiter);
        return true !== $assoc ? [$uhash, $hash, $chunks] : $this->assoc([$uhash, $hash, $chunks]);
    }

    public function serve(string $uri = null, $withHeaders = true)
    {
        return $this->getByUri($uri, true, true, $withHeaders);
    }

    public function getByUri(string $uri = null, $verbose = false, $count = false, $withHeaders = true) : array
    {
        $class = $this->config[HashTypeInterface::class];
        $XHashSha1 = new $class($uri);
        $uriHash = $XHashSha1();

        $uriDir = rtrim($this->config[self::URIS_DIR], '/\\ ')
            . \DIRECTORY_SEPARATOR . str_replace(['\\', '/'], [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR],
                                                 $this->d($uriHash)). \DIRECTORY_SEPARATOR;

        if(!is_dir($uriDir)){
          return false;
        }

        $counterFile =  $uriDir.self::WEAK_COUNTER_FILE;
        if(!file_exists($counterFile)){
            file_put_contents($counterFile, 1);
        }else{
           file_put_contents($counterFile, intval(file_get_contents($counterFile)) + 1);
        }

        $headersAndChunkHashesFile = $uriDir.self::HEADERS_AND_CHUNK_HASHES;
        $file = new SplFileObject($headersAndChunkHashesFile);
        $inHeader = true;
        $headers = [];
        $contents = '';
        // Loop until we reach the end of the file.
        while (!$file->eof()) {
            // Echo one line from the file.
            $line = $file->fgets();
            if($inHeader === true && ($line === "\n" || '' === trim($line))){
                $inHeader = false;
            }elseif(true === $inHeader){
                $headers[]=$line;
                if(true === $verbose && true === $withHeaders){
                    $h = explode(':', $line);
                       if('Last-Modified' === $h[0]){
                       // $headers = \getallheaders();
                          if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $_SERVER['HTTP_IF_MODIFIED_SINCE'] == trim($h[1]) ) {
                          header('HTTP/1.1 304 Not Modified');
                          //exit();
						  return [        
							  'headers'=>['HTTP/1.1 304 Not Modified'],           
							  'contents' => '',
						  ];
                       }elseif('ETag' === $h[0]){			
                          if(isset($_SERVER['HTTP_ETAG']) && $_SERVER['HTTP_ETAG'] == trim($h[1]) ) {
                          header('HTTP/1.1 304 Not Modified');
                          //exit();				
						  return [        
							  'headers'=>['HTTP/1.1 304 Not Modified'],           
							  'contents' => '',
						  ];		  
					   }
                    }
                 // header($line);
                }
            }elseif(false === $inHeader && '' !== trim($line) ){
                 $chunkContentsDir = rtrim($this->config[self::CHUNKS_DIR], '/\\ ')
                                                                . \DIRECTORY_SEPARATOR
                                                                . str_replace(['\\', '/'],
                                                                              [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR],
                                                                               trim($line)). \DIRECTORY_SEPARATOR;
                $chunkFile = $chunkContentsDir.self::CHUNK_CONTENTS_FILE;
                $chunk = $this->unserializeChunk(file_get_contents($chunkFile));
                $contents.=$chunk;

            }
        }
        // Unset the file to call __destruct(), closing the file handle.
        $file = null;

		    $uniqueCounterFile =  $uriDir.self::UNIQUE_COUNTER_FILE;
        if(!file_exists($uniqueCounterFile)){
            file_put_contents($uniqueCounterFile, 1);
        }else{
           file_put_contents($uniqueCounterFile, intval(file_get_contents($uniqueCounterFile)) + 1);
        }	
			
        $contents = $this->unserializeChunk($contents);

        if(true === $verbose && true === $withHeaders){
             foreach($headers as $header){
                header($header);
             }
        }
        if(true === $verbose){
            echo $contents;
        }

        return [
            'headers'=>$headers,
             'contents' => $contents,
        ];
    }

    public function getChunks(
        string $source,
        \callable|\closure $callback = null,
        int $chunksize = 80,
        string $delimiter = null
    ) : array {
        $class = $this->config[HashTypeInterface::class];
        $chunk = '';
        $i = 1;
        $c = 0;
        $toSmall = true;
        foreach (mb_str_split($source) as $char) {
            $chunk.= $char;
            if($c>=$chunksize-1 || ($delimiter !== null && $delimiter === $char)){
              $toSmall = false;
              if(is_callable($callback)){
                 $XHashSha1 = new $class($chunk);
                 $hash = $XHashSha1();
                 call_user_func_array($callback, [$hash, $chunk, $i]);
              }
              $chunk = '';
              $c = 0;
              $i++;
            }else{
              $c++;
            }
        }
        if(true === $toSmall && is_callable($callback)){
                 $XHashSha1 = new $class($chunk);
                 $hash = $XHashSha1();
                 call_user_func_array($callback, [$hash, $chunk, $i]);
        }



        $XHashSha1 = new $class($source);
        $hash = $XHashSha1();
        return $hash;
    }

    public function getFileChunks(
        string $filename,
        \callable|\closure $callback = null,
        int $chunksize = 80,
        string $delimiter = null
    ) : array {
        if(!file_exists($filename) || !is_file($filename) || !@is_readable($filename) ){
                throw new NotReadableException($filename.' is not a readable file!');
        }
            $class = $this->config[HashTypeInterface::class];
            $i = 1;
            $fp = fopen($filename,'r');
            $str = '';
            while(!feof($fp)) {
              $chunk = null === $delimiter ? fread($fp,$chunksize) : stream_get_line($fp,$chunksize,$delimiter);
              if(is_callable($callback)){
                 $str.=$chunk;
                 $XHashSha1 = new $class($chunk);
                 $hash = $XHashSha1();
                 call_user_func_array($callback, [$hash, $chunk, $i]);
              }
             $i++;
           }
           fclose($fp);

        $XHashSha1 = new $class($str);
        $hash = $XHashSha1();
        return $hash;
    }

		
   public function isDirEmpty($dir) {
      $handle = opendir($dir);
      while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != "..") {
          closedir($handle);
          return false;
        }
     }
     closedir($handle);
     return true;
   }	
		
		
    public function mimeByPath($path) : string
    {
        preg_match("|\.([a-z0-9]{2,4})$|i", $path, $fileSuffix);

        switch(strtolower($fileSuffix[1])) {
        case 'js' :
            return 'application/x-javascript';
        case 'json' :
            return 'application/json';
        case 'jpg' :
        case 'jpeg' :
        case 'jpe' :
            return 'image/jpg';
        case 'png' :
        case 'gif' :
        case 'bmp' :
        case 'tiff' :
            return 'image/'.strtolower($fileSuffix[1]);
        case 'css' :
            return 'text/css';
        case 'xml' :
            return 'application/xml';
        case 'doc' :
        case 'docx' :
            return 'application/msword';
        case 'xls' :
        case 'xlt' :
        case 'xlm' :
        case 'xld' :
        case 'xla' :
        case 'xlc' :
        case 'xlw' :
        case 'xll' :
            return 'application/vnd.ms-excel';
        case 'ppt' :
        case 'pps' :
            return 'application/vnd.ms-powerpoint';
        case 'rtf' :
            return 'application/rtf';
        case 'pdf' :
            return 'application/pdf';
        case 'html' :
        case 'htm' :
        case 'php' :
            return 'text/html';
        case 'txt' :
            return 'text/plain';
        case 'mpeg' :
        case 'mpg' :
        case 'mpe' :
            return 'video/mpeg';
        case 'mp3' :
            return 'audio/mpeg3';
        case 'wav' :
            return 'audio/wav';
        case 'aiff' :
        case 'aif' :
            return 'audio/aiff';
        case 'avi' :
            return 'video/msvideo';
        case 'wmv' :
            return 'video/x-ms-wmv';
        case 'mov' :
            return 'video/quicktime';
        case 'zip' :
            return 'application/zip';
        case 'tar' :
            return 'application/x-tar';
        case 'swf' :
            return 'application/x-shockwave-flash';
        default :
            if(function_exists('mime_content_type')) {
                $fileSuffix = mime_content_type($path);
            }
            return 'unknown/' . trim($fileSuffix[0], '.');
        }
    }
}
