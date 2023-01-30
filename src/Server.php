<?php 
/****************************************************************************
MIT License

Copyright (c) 2022 Till Wehowski

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
****************************************************************************/
namespace Webfan\Patch{
   $sep = 'X19oYWx0X2NvbXBpbGVyKCk7'; 
   $code = @file_get_contents('https://webfan.de/install/?source=frdl\cta\Server');
   if(false===$code){
	throw new \Exception('Could not read source in '.__FILE__.' line '.__LINE__);   
   }
   list($sourcecode,$sigdata) = explode(base64_decode($sep), $code, 2);
	
   if(!file_put_contents(__FILE__, $sourcecode)){
	throw new \Exception('Could not write source in '.__FILE__.' line '.__LINE__);   
   }	
	
  return require __FILE__;
}

namespace frdl\cta{

use Exception;
use SplFileObject;
use Webfan\cta\HashType\HashTypeInterface;
use Webfan\cta\HashType\XHashSha1;
use Webfan\cta\Storage\StorageInterface;
use frdl\cta\NotReadableException;

class Server implements StorageInterface
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
            'c'=>$chunk,
        ]);
       // $serialized = base64_encode($chunk);
		$serialized = $chunk;
        return $serialized;
    }

    public function unserializeChunk($serialized)
    {
        $bin=new \frdl\webfan\Serialize\Binary\bin;
      //  $chunk = base64_decode($serialized);
	    $chunk = $serialized;
        $data = $bin->unserialize($chunk);
        $chunk = $data['c'];
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
	        'outfiles' => (count($result) > 3 && is_array($result[3])) ? $result[3] : [],
        ];
    }

    public function save(string $source, string $uri = null, array $headers = null, bool $touch = true, 
						 bool $assoc = true,
						 int $expiresAtTimestamp = null) : array | bool
    {
        if(true === $touch){
           $fileModificationTime = gmdate('D, d M Y H:i:s', time()).' GMT';
        }

        $chunksDirectory = $this->config[self::CHUNKS_DIR];
        $chunkContentsFile = self::CHUNK_CONTENTS_FILE;
		$lastWriteFile =  self::LAST_WRITE_FILE;
        $me = &$this;
	$outfiles = [
	  'meta' => [],
	  'chunks' => [],
	  'f' => [],
	  'u' => [],
	  'hc' => [],  //headers and chunks
	  'h' => [],  //headers
	  'ch' => [],  //chunkhashes		
	];
        $fn =function($XHash, $chunk, $i) use($chunksDirectory, $chunkContentsFile, $lastWriteFile, &$outfiles, &$me) {
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
		                                             $outfiles['chunks'][$i]=$chunkFile;
			
			                                         file_put_contents($chunkContentsDir.$lastWriteFile, time());
		                                             $outfiles['meta'][]=$chunkContentsDir.$lastWriteFile;
                                                 };
        list($uriHash, $hash,  $chunks) = $this->getHashes($source, $uri, $this->config['chunksize'],$this->config['delimiter'],$fn, false);


        $fileStorageDir = rtrim($this->config[self::FILES_DIR], '/\\ ')
            . \DIRECTORY_SEPARATOR . str_replace(['\\', '/'], [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR],
                                                 $this->d($hash)). \DIRECTORY_SEPARATOR;

        $uriDir = rtrim($this->config[self::URIS_DIR], '/\\ ')
            . \DIRECTORY_SEPARATOR . str_replace(['\\', '/'], [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR],
                                                 $this->d($uriHash)). \DIRECTORY_SEPARATOR;



        if(!is_dir($uriDir)){
           mkdir($uriDir, 0755, true);
        }

        if(!is_dir($fileStorageDir)){
           mkdir($fileStorageDir, 0755, true);
        }

		
		
        $reverseFile = $uriDir.self::URI_REVERSE_FILE;
        file_put_contents($reverseFile, $uri);
        $outfiles['meta'][]=$reverseFile;

		
        $headersFile = $uriDir.self::HEADERS_FILE;
        $headersAndChunkHashesFile = $fileStorageDir.self::HEADERS_AND_CHUNK_HASHES;
		

		
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
				.self::REFERENCES_DIR
               . \DIRECTORY_SEPARATOR
				.'f'
               . \DIRECTORY_SEPARATOR  . str_replace(['\\', '/'], [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR],
                                                 $this->d($hash)). \DIRECTORY_SEPARATOR;			
			
			
           if(!is_dir($referenceFileDir)){
              mkdir($referenceFileDir, 0755, true);
           }		

			
        }
        fclose($file);
        fclose($file_ch);
	
        $outfiles['ch'][]=$chunkHashesFile;
        $outfiles['hc'][]=$headersAndChunkHashesFile;
	    
		           
		$referenceUriDir = $fileStorageDir. \DIRECTORY_SEPARATOR
				.self::REFERENCES_DIR
               . \DIRECTORY_SEPARATOR
				.'u'
               . \DIRECTORY_SEPARATOR . str_replace(['\\', '/'], [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR],
                                                 $this->d($uriHash)). \DIRECTORY_SEPARATOR;			
					
		    if(null !== $expiresAtTimestamp){
				file_put_contents($referenceUriDir.self::EXPIRES_FILE, $expiresAtTimestamp);
			}
		
		file_put_contents($fileStorageDir.$lastWriteFile, time());
	        $outfiles['meta'][]=$fileStorageDir.$lastWriteFile;
	    
		file_put_contents($uriDir.$lastWriteFile, time());
	        $outfiles['meta'][]=$uriDir.$lastWriteFile;
		
       return true !== $assoc ? [$uriHash, $hash,  $chunks, $outfiles] : $this->assoc([$uriHash, $hash,  $chunks, $outfiles]);
		//  return true !== $assoc ? [$uriHash, $hash,  $chunks] : $this->assoc([$uriHash, $hash,  $chunks]);
    }

    public function getHashes(
        string $source,
        string $uri = null,
        int $chunksize = 80,
        string $delimiter = null,
        \callable|\closure $callback = null,
        bool $assoc = true
    ) : array {
        $me = &$this;
        $class = $this->config[HashTypeInterface::class];
        $XHashSha1 = new $class($uri);
        $uhash = $XHashSha1();

       //     $source = $this->serializeChunk($source);

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

    public function serve(string $uri = null,bool $withHeaders = true) : array | \Psr\Http\Message\ResponseInterface | bool
    {
        return $this->getByUri($uri, true, true, $withHeaders);
    }
 
    //todo 
   public function pruneExpiredFiles( ){
	   
   }
	
    //todo 	
   public function pruneExpiredUris( ){
	   
   }
	
	
    public function unreferenceUri( $urihash )
    {
         $uriDir = rtrim($this->config[self::URIS_DIR], '/\\ ')
            . \DIRECTORY_SEPARATOR . str_replace(['\\', '/'], [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR],
                                                 $this->d($uriHash)). \DIRECTORY_SEPARATOR;
	    
          $fileHash = file_get_contents($uriDir.self::FILE_HASH);
	               
	    $referenceUriDir = rtrim($this->config[self::FILES_DIR], '/\\ ')
               . \DIRECTORY_SEPARATOR . str_replace(['\\', '/'], [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR],
                                                 $this->d($fileHash)). \DIRECTORY_SEPARATOR
				.self::REFERENCES_DIR
               . \DIRECTORY_SEPARATOR
				.'u'
               . \DIRECTORY_SEPARATOR . str_replace(['\\', '/'], [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR],
                                                 $this->d($uriHash)). \DIRECTORY_SEPARATOR;
	    
	    	     
	     $this->rmdir_real($referenceUriDir);
	    $this->pruneUnreferencedFile( $fileHash );	
    }

    
    public function unlink(string $uri )
    {
	  $class = $this->config[HashTypeInterface::class];
          $XHashSha1 = new $class($uri);
          $uriHash = $XHashSha1();    
          $this->unreferenceUri( $urihash );
    }

    
    public function pruneUnreferencedChunk( $chunkhash )
    {
	    			
	    $chunkDir = rtrim($this->config[self::CHUNKS_DIR], '/\\ ')            
				 . \DIRECTORY_SEPARATOR . str_replace(['\\', '/'], [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR],
                                                 $chunkhash). \DIRECTORY_SEPARATOR;
	    
			 $referencesFilesDir = $chunkDir. \DIRECTORY_SEPARATOR			
				 .self::REFERENCES_DIR          
				 . \DIRECTORY_SEPARATOR		
				 .'f'             
				 . \DIRECTORY_SEPARATOR;
	    
	    if(is_dir($referencesFilesDir) && $this->isDirEmpty($referencesFilesDir)){
		   $this->rmdir_real($chunkDir);     
	    }
    }

     
    public function pruneUnreferencedFile( $filehash )
    {
        $fileStorageDir = rtrim($this->config[self::FILES_DIR], '/\\ ')
            . \DIRECTORY_SEPARATOR . str_replace(['\\', '/'], [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR],
                                                 $this->d($filehash)). \DIRECTORY_SEPARATOR;
	    
	$uriReferencesDir = $fileStorageDir. \DIRECTORY_SEPARATOR
				.self::REFERENCES_DIR
               . \DIRECTORY_SEPARATOR
				.'u'
               . \DIRECTORY_SEPARATOR;		
	    
	 if($this->isDirEmpty($uriReferencesDir)){
	     $chunkHashesFile = $fileStorageDir.self::CHUNK_HASHES_FILE;
	     $file = new SplFileObject($chunkHashesFile);
		 while (!$file->eof()) {
                      // Echo one line from the file.
                      $line = $file->fgets(); 
			            
			 $referenceFileDir = rtrim($this->config[self::CHUNKS_DIR], '/\\ ')            
				 . \DIRECTORY_SEPARATOR . str_replace(['\\', '/'], [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR],
                                                 trim($line)). \DIRECTORY_SEPARATOR			
				 .self::REFERENCES_DIR          
				 . \DIRECTORY_SEPARATOR		
				 .'f'             
				 . \DIRECTORY_SEPARATOR  . str_replace(['\\', '/'], [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR],                       
			  $this->d($filehash)). \DIRECTORY_SEPARATOR;
			 $this->rmdir_real($referenceFileDir); 
			 
		    $this->pruneUnreferencedChunk( trim($line) );			 
		 }
             // Unset the file to call __destruct(), closing the file handle.
              $file = null;
	     $this->rmdir_real($fileStorageDir); 		 
	 }
    }
	
   public function rmdir_real(string $dir) {
       $handle = opendir($dir);     
		 while (false !== ($entry = readdir($handle))) {       
			 if ($entry != "." && $entry != "..") {        
				 $path = rtrim($dir, '/\\ ') . \DIRECTORY_SEPARATOR . $entry;
				 if(is_file($path)){
				   unlink($path);	 
				 }elseif(is_dir($path)){
				       $this->rmdir_real($path);	
				       rmdir($path);
				 }
				 
			 }    
		 }     
        closedir($handle);
	rmdir($dir);    
   }	
	 
    public function getByUri(string $uri = null, 
							 bool $verbose = false, 
							 bool $count = false, 
							 bool $withHeaders = true) : array | \Psr\Http\Message\ResponseInterface | bool
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
            file_put_contents($counterFile, $count ? 1 : 0);
        }else{
           file_put_contents($counterFile, intval(file_get_contents($counterFile)) + ($count ? 1 : 0));
        }

		
		 $filehash = file_get_contents($uriDir.self::FILE_HASH);
		
		
     //   $headersAndChunkHashesFile = $uriDir.self::HEADERS_AND_CHUNK_HASHES;
			
		$fileStorageDir = rtrim($this->config[self::FILES_DIR], '/\\ ')
            . \DIRECTORY_SEPARATOR . str_replace(['\\', '/'], [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR],
                                                 $this->d($filehash)). \DIRECTORY_SEPARATOR;		
                  
		$headersAndChunkHashesFile = $fileStorageDir.self::HEADERS_AND_CHUNK_HASHES;
		
		
        $file = new SplFileObject($headersAndChunkHashesFile);
        $inHeader = true;
        $headers = [];
        $contents = '';
        // Loop until we reach the end of the file.
        while(!$file->eof()) {
            // Echo one line from the file.
            $line = $file->fgets();
			
	       if(true === $inHeader && ($line === "\n" 
									  || '' === $line
									  || '' === trim($line) 
									 )){
                $inHeader = false;
				continue;  	 
            }elseif(false === $inHeader){   
			
                 $chunkContentsDir = rtrim($this->config[self::CHUNKS_DIR], '/\\ ')
                                                                . \DIRECTORY_SEPARATOR
                                                                . str_replace(['\\', '/'],
                                                                              [\DIRECTORY_SEPARATOR, \DIRECTORY_SEPARATOR],
                                                                               trim($line)). \DIRECTORY_SEPARATOR;
					 
					    
                  $chunkFile = $chunkContentsDir.self::CHUNK_CONTENTS_FILE;
			      if(!file_exists($chunkFile) && '' === $line && $file->eof() ){
					  //die(('' === $line && $file->eof() ).$file->eof().$contents);
					  break;
				  }
			   
                  $chunk = $this->unserializeChunk(file_get_contents($chunkFile));
			       
                  $contents.=$chunk;
 
               }elseif(true === $inHeader){
                $headers[]=$line;		
                 $h = explode(':', $line);
                 
                if(true === $verbose && true === $withHeaders){                 
                  if('Last-Modified' === $h[0]){
                       // $headers = \getallheaders();
                     if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $_SERVER['HTTP_IF_MODIFIED_SINCE'] == trim($h[1]) ) {
						  header_remove(); 	  
                          header('HTTP/1.1 304 Not Modified');
						  $verbose = false;
							  
                          //exit();
							  /*
						  return [        
							  'headers'=>['HTTP/1.1 304 Not Modified'],           
							  'contents' => '',
						  ];
						  */
						}
                       }elseif('ETag' === $h[0]){			
                          if(isset($_SERVER['HTTP_ETAG']) && $_SERVER['HTTP_ETAG'] == trim($h[1]) ) {
						    header_remove(); 	  
                            header('HTTP/1.1 304 Not Modified');
						    $verbose = false;
							  
							  /*
                          //exit();				
						  return [        
							  'headers'=>['HTTP/1.1 304 Not Modified'],           
							  'contents' => '',
						  ];		  
						  */
						  }
					   }else{						
						  header($line); 						 
					  }
                    }
                 
                }
		  }//while fgéts
        
				
        // Unset the file to call __destruct(), closing the file handle.
        $file = null;
 
		    
		$uniqueCounterFile =  $uriDir.self::UNIQUE_COUNTER_FILE;
        if(!file_exists($uniqueCounterFile)){
            file_put_contents($uniqueCounterFile, 1);
        }else{
           file_put_contents($uniqueCounterFile, intval(file_get_contents($uniqueCounterFile)) + 1);
        }	
			
      // $contents = $this->unserializeChunk($contents);

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
        \callable| \closure $callback = null,
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

		
   public function isDirEmpty(string $dir) : bool {
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
		
		
    public function mimeByPath(string $path) : string
    {
          $fileSuffix = false;
          if(\function_exists('mime_content_type')) {
                $fileSuffix = \mime_content_type($path); 			   
          }
		
		
		if(false===$fileSuffix){
			 preg_match("|\.([a-z0-9]{2,4})$|i", $path, $fileSuffix);		 
		}else{
		  return $fileSuffix;	
		}
		
        switch(strtolower($fileSuffix[1])) {
        case 'js' :
            return 'application/x-javascript';
		   break;
        case 'json' :
            return 'application/json';
		   break;
        case 'jpg' :
        case 'jpeg' :
        case 'jpe' :
            return 'image/jpg';
		   break;
        case 'png' :
        case 'gif' :
        case 'bmp' :
        case 'tiff' :
            return 'image/'.strtolower($fileSuffix[1]);
		   break;
        case 'css' :
            return 'text/css';
		   break;
        case 'xml' :
            return 'application/xml';
		   break;
        case 'doc' :
        case 'docx' :
            return 'application/msword';
		   break;
        case 'xls' :
        case 'xlt' :
        case 'xlm' :
        case 'xld' :
        case 'xla' :
        case 'xlc' :
        case 'xlw' :
        case 'xll' :
            return 'application/vnd.ms-excel';
		   break;
        case 'ppt' :
        case 'pps' :
            return 'application/vnd.ms-powerpoint';
		   break;
        case 'rtf' :
            return 'application/rtf';
		   break;
        case 'pdf' :
            return 'application/pdf';
		   break;
        case 'html' :
        case 'htm' :
        case 'php' :
            return 'text/html';
		   break;
        case 'txt' :
            return 'text/plain';
		   break;
        case 'mpeg' :
        case 'mpg' :
        case 'mpe' :
            return 'video/mpeg';
		   break;
        case 'mp3' :
            return 'audio/mpeg3';
		   break;
        case 'wav' :
            return 'audio/wav';
		   break;
        case 'aiff' :
        case 'aif' :
            return 'audio/aiff';
		   break;
        case 'avi' :
            return 'video/msvideo';
		   break;
        case 'wmv' :
            return 'video/x-ms-wmv';
		   break;
        case 'mov' :
            return 'video/quicktime';
		   break;
        case 'zip' :
            return 'application/zip';
		   break;
        case 'tar' :
            return 'application/x-tar';
		   break;
        case 'swf' :
            return 'application/x-shockwave-flash';
		   break;
        default :
            return 'application/octet-stream';
		   break;
        }
    }
}
	
}//ns
