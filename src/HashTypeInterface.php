<?php

interface HashTypeInterface {
    public function getType() : array;
    public function hash($contents) : array;
    public function __toString() : string;
}
