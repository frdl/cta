<?php

namespace Webfan\cta\Storage;

interface StorageConfigInterface {
    public function getUriStorage();
    public function getFileStorage();
    public function getChunkStorage();
}
