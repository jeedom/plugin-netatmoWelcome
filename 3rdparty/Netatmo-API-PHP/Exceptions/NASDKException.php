<?php
/**
* Exception thrown by Netatmo SDK
*/
class NASDKException extends Exception
{
  public function __construct($code, $message)
  {
    parent::__construct($message, $code);
  }
}

if(!class_exists('NASDKError')){
  class NASDKError
  {
    const UNABLE_TO_CAST = 601;
    const NOT_FOUND = 602;
    const INVALID_FIELD = 603;
    const FORBIDDEN_OPERATION = 604;
  }
}
if(!class_exists('NASDKErrorException')){
  class NASDKErrorException extends Exception
  {
    public function __construct($code, $message)
    {
      parent::__construct($message, $code);
    }
  }
}

if(!class_exists('NASDKErrorCode')){
  class NASDKErrorCode
  {
    const UNABLE_TO_CAST = 601;
    const NOT_FOUND = 602;
    const INVALID_FIELD = 603;
    const FORBIDDEN_OPERATION = 604;
  }
}
?>
