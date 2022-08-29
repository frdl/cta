<?php

namespace Webfan\cta\HashType;

class XHashSha1 implements HashTypeInterface
{
    protected $hash = null;
    public $sep = '/';
    public function __construct(string $contents = null){
         if(null !== $contents){
            $this($contents); 
         }
    }  
    public function getHash(){
        return $this->hash;
    }
    public function getType(): array {
        return [          
           '1.3.6.1.4.1.37553.8.1.8.1.16606.1.56234465',
        ];
    }
    public function __invoke($contents): array{
         if(!is_string($contents)){      
           if(null === $this->getHash()){          
             throw new \Exception('You must hash some contents before you can get a hash!');       
           }           
            return $this->getHash();
         }      
      
         $this->hash = [   
            sha1($contents),         
            strlen($contents),
         ];
       return $this->hash;
    }
    public function __toString(): string{
       if(null === $this->getHash()){
          throw new \Exception('You must hash some contents before you can get a string!'); 
       }
       return implode($this->sep, array_reverse($this->hash));
    }
}
