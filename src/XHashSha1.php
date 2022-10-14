<?php

namespace Webfan\cta\HashType;

class XHashSha1 implements HashTypeInterface
{
    protected $hash = null;
    protected $sep = null;
    public function __construct(string $contents = null,string $sep = null){
         if(null !== $sep){
            $this->setSeparator($sep); 
         }
        
        if(null === $this->sep){
            $this->setSeparator(\DIRECTORY_SEPARATOR); 
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
    
    
//echo ' ';
//echo $this->base_convert(299, 10, 64);
//echo ' ';
//echo base_convert(299, 10, 36);   
//    echo ' ';
//echo $this->base_convert('1239', 10, 36);
//echo ' ';
//echo base_convert(1239, 10, 36);
/*
* Created by Michael Renner  17-May-2006 03:24
*/
public function base_convert(string | int $numstring,int $frombase = 10,int $tobase = 36, bool $sanitized = true)
{

    $numstring = (string)$numstring;
    $numstring = ltrim($numstring, '0 ');
    $chars = "0123456789abcdefghijklmnopqrstuvwxyz";
    $chars .= strtoupper('abcdefghijklmnopqrstuvwxyz');
    $chars .=  '-_';
        
      if($sanitized && $tobase > 64){ 
             $chars .='.,:;';
             $chars .='!§$%&=|';
             trigger_error('Base should be less or equal 64 in '.__METHOD__, E_USER_WARNING);
       }
        
    if($tobase > strlen($chars) || $frombase > strlen($chars) || $tobase < 2 || $frombase < 2){
        throw new \Exception('Base is out of range in '.__METHOD__.' #'.__LINE__);
    }
    
    $tostring = substr($chars, 0, $tobase);

    $length = strlen($numstring);
    $result = '';
    for ($i = 0; $i < $length; $i++)
    {
        $number[$i] = strpos($chars, $numstring[$i]);
    }
    do
    {
        $divide = 0;
        $newlen = 0;
        for ($i = 0; $i < $length; $i++)
        {
            $divide = $divide * $frombase + $number[$i];
            if ($divide >= $tobase)
            {
                $number[$newlen++] = (int)($divide / $tobase);
                $divide = $divide % $tobase;
            } elseif ($newlen > 0)
            {
                $number[$newlen++] = 0;
            }
        }
        $length = $newlen;
        $result = $tostring[$divide] . $result;
    } while ($newlen !== 0);
    return $result;
}

    
    public function split($str, $l = 4) {
       $tmp = array_chunk(
        preg_split("//u", $str, -1, \PREG_SPLIT_NO_EMPTY),
       $l);
       $chunks = [];
      foreach ($tmp as $t) {
        $chunks[]= join("", $t);
      }
      return $chunks;
   }    
    
    public function __invoke(string $contents = null): array{
         if(!is_string($contents)){      
           if(null === $this->getHash()){          
             throw new \Exception('You must hash some contents before you can get a hash!');       
           }           
            return $this->getHash();
         }      
      
         $hash = sha1($contents);
         $hash = $this->base_convert($hash, 16, 36);
         $this->hash = $this->split($hash, 4);
         $this->hash[] = str_pad($this->base_convert(strlen($contents), 10, 36), 4, "0", \STR_PAD_LEFT);
        
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
