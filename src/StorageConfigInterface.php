<?php

namespace Webfan\cta\Storage;

interface StorageConfigInterface {
    public function getUriStorage();
    public function getFileSrorage();
    public function getChunkStorage();
}
