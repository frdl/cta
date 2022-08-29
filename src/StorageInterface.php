<?php

namespace Webfan\cta\Storage;

interface StorageInterface {

public function serializeChunk(/* (note: any data type in standard implementation possible) */ $chunk);
public function unserializeChunk($serialized);
public function save(string $source,
                     string $uri = null, 
                     array $headers = null,
                     bool $touch = true, /* alter FileModificationTime */
			   bool $assoc = true,
			   int $expiresAtTimestamp = null) : array;
public function getHashes(
        string $source,
        string $uri = null,
        int $chunksize = 80,
        string $delimiter = null,
        \callable|\closure $callback = null,
        bool $assoc = true
    ) : array;
 public function serve(string $uri = null, bool $withHeaders = true) : array;
 public function getByUri(string $uri = null, bool $verbose = false, bool $count = false, bool $withHeaders = true) : array;
 public function getChunks(
        string $source,
        \callable|\closure $callback = null,
        int $chunksize = 80,
        string $delimiter = null
    ) : array;
/* public function getFileChunks( string $filename,\callable|\closure $callback = null,int $chunksize = 80,string $delimiter = null) : array; */
 public function pruneUnreferencedFile( $filehash );
 public function pruneUnreferencedChunk( $chunkhash );
 public function unlink(string $uri );
 public function unreferenceUri( $urihash ); 
	
 //Cronjobs:
 public function pruneExpiredFiles( );
 public function pruneExpiredUris( );	
}
