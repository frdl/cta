<?php

interface HashTypeInterface {
    public function getType() : string;
    public function hash($contents) : array;
    public function __toString() : string;
}
