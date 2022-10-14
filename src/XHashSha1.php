<?php

namespace Webfan\cta\HashType;

class XHashSha1 implements HashTypeInterface
{
    protected $hash = null;
    protected $sep = '/';
    public function __construct(string $contents = null,string $sep = null){
         if(null !== $sep){
            $this->setSeparator($sep); 
         }
         if(null !== $contents){
            $this($contents); 
         }
    }  
    public function setSeparator(string $sep){
        $this->sep = $sep; 
        return $this;
    }
    public function getHash(){
        return $this->hash;
    }
    public function getType(): array {
        return [          
           '1.3.6.1.4.1.37553.8.1.8.1.16606.1.56234465',
        ];
    }
    public function __invoke(string $contents = null): array{
         if(!is_string($contents)){      
           if(null === $this->getHash()){          
             throw new \Exception('You must hash some contents before you can get a hash!');       
           }           
            return $this->getHash();
         }      
      
         $hash = sha1($contents);
        
         $this->hash = [       
            substr($hash, 0, 4),   
            substr($hash, 5, strlen($hash)),    
            strlen($contents),
         ];
       return $this->hash;
    }
    public function toString(array $hash, string $sep = null): string{
       return implode(null === $sep ? $this->sep : $sep, array_reverse($hash));
    }
    public function __toString(): string{
       if(null === $this->getHash()){
          throw new \Exception('You must hash some contents before you can get a string!'); 
       }
       return $this->toString($this->getHash(), $this->sep);
    }
}
