<?php

namespace Webfan\cta\Storage;

interface StorageConfigInterface {
    public function getUriStrorage();
    public function getFileStrorage();
    public function getChunkStrorage();
}
