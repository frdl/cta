<?php

interface HashTypeInterface {
    public function getType() : array;
    public function __invoke($contents) : array;
    public function __toString() : string;
}
