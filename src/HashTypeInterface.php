<?php
namespace Webfan\cta\HashType;

interface HashTypeInterface {
    public function getType() : array;
    public function __invoke(string $contents = null) : array;
    public function __toString() : string;
}
