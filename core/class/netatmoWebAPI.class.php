<?php

class netatmoWebAPI {
  
  /*     * *************************Attributs****************************** */
  
  public $_csrf = null;
  public $_csrfName = null;
  public $_token = null;
  public $_homeID = 0;
  public $_home = null;
  public $_username = null;
  public $_password = null;
  const URL_START = 'https://my.netatmo.com';
  const URL_HOST = 'https://app.netatmo.net';
  const URL_AUTH = 'https://auth.netatmo.com';
  protected $_curlHdl = null;
  
  /*     * ***********************Methode static*************************** */
  
  function __construct($_username,$_password,$_homename=0){
    $this->_username = $_username;
    $this->_password = $_password;
    if ($_homename !== 0){
      $this->_homeName = $_homename;
      $this->_homeID = -1;
    }
  }
  
  /*     * *********************Methode d'instance************************* */
  
  public function _request($method, $url, $post=null,$_reconnect = false){
    if (!isset($this->_curlHdl)){
      $this->_curlHdl = curl_init();
      curl_setopt($this->_curlHdl, CURLOPT_COOKIEFILE, jeedom::getTmpFolder('netatmoWelcome') . '/cookie.txt');
      curl_setopt($this->_curlHdl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($this->_curlHdl, CURLOPT_HEADER, true);
      curl_setopt($this->_curlHdl, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($this->_curlHdl, CURLOPT_REFERER, 'http://www.google.com/');
      curl_setopt($this->_curlHdl, CURLOPT_COOKIEJAR, jeedom::getTmpFolder('netatmoWelcome') . '/cookie.txt');
      curl_setopt($this->_curlHdl, CURLOPT_USERAGENT, 'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:51.0) Gecko/20100101 Firefox/51.0');
      curl_setopt($this->_curlHdl, CURLOPT_ENCODING , '');
    }
    curl_setopt($this->_curlHdl, CURLOPT_URL, $url);
    if ($method == 'POST'){
      curl_setopt($this->_curlHdl, CURLOPT_POST, true);
      if (isset($post) && $this->_csrfName != null && $this->_csrf != null){
        $post .= '&'.$this->_csrfName.'='.$this->_csrf;
      }
      curl_setopt($this->_curlHdl, CURLOPT_POSTFIELDS, $post);
      if (isset($this->_token)){
        curl_setopt($this->_curlHdl, CURLOPT_HEADER, false);
        curl_setopt($this->_curlHdl, CURLOPT_HTTPHEADER, array(
          'Connection: keep-alive',
          'Content-Type: application/x-www-form-urlencoded',
          'Authorization: Bearer '.$this->_token
        )
      );
    }
  }else{
    curl_setopt($this->_curlHdl, CURLOPT_HTTPGET, true);
  }
  $response = curl_exec($this->_curlHdl);
  if ($response === false){
    throw new \Exception('cURL error: ' . curl_error($this->_curlHdl));
  }
  $response = is_json($response,$response);
  
  if(isset($response['error']) && isset($response['error']['code'])){
    if($response['error']['code'] == 2){
      if(!$_reconnect){
        $this->connect();
        return $this->_request($method, $url, $post,true);
      }
    }else{
      throw new \Exception('Error : '.json_encode($response));
    }
  }
  return $response;
}

public function connect(){
  log::add('netatmoWelcome','debug','Reconnection to netatmo web client');
  if(file_exists(jeedom::getTmpFolder('netatmoWelcome') . '/cookie.txt')){
    unlink(jeedom::getTmpFolder('netatmoWelcome') . '/cookie.txt');
  }
  $this->_token = null;
  $this->_curlHdl = null;
  $answer = $this->_request('GET', self::URL_START);
  $var = $this->getCSRF($answer);
  if($var != false){
    $this->_csrfName = $var[0];
    $this->_csrf = $var[1];
  }else{
    throw new \Exception("Couldn't find Netatmo CSRF.");
  }
  $url = self::URL_AUTH.'/en-us/access/login?message=__NOT_LOGGED';
  $answer = $this->_request('GET', $url);
  $loginTokenStart = strpos($answer, '"_token" value="') + 16;
  $loginTokenEnd = strpos($answer, '">', $loginTokenStart);
  $loginTokenLength = ($loginTokenEnd - $loginTokenStart);
  if ($loginTokenLength <= 0) {
    throw new \Exception("Couldn't find Netatmo _token on login page.");
  }
  $loginToken = substr($answer, $loginTokenStart, ($loginTokenLength));
  $url = self::URL_AUTH .'/access/postlogin';
  $post = "email=".urlencode($this->_username) ."&password=".urlencode($this->_password)."&_token=".$loginToken;
  $answer = $this->_request('POST', $url, $post);
  $cookies = explode('Set-Cookie: ', $answer);
  foreach($cookies as $var)  {
    if (strpos($var, 'netatmocomaccess_token') === 0){
      $cookieValue = explode(';', $var)[0];
      $cookieValue = str_replace('netatmocomaccess_token=', '', $cookieValue);
      $token = urldecode($cookieValue);
      if ($token != 'deleted'){
        $this->_token = $token;
        return true;
      }
    }
  }
  throw new \Exception("Couldn't find Netatmo token => ".json_encode($cookies));
}

public function getCSRF($answerString){
  preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $answerString, $matches);
  $cookies = array();
  foreach($matches[1] as $item){
    parse_str($item, $cookie);
    $cookies = array_merge($cookies, $cookie);
  }
  $cookie = null;
  $cookiename = null;
  foreach ($cookies as $name => $value){
    if (strpos($name, 'csrf') !== false){
      $cookiename = str_replace('netatmocom', '', $name);
      $cookiename = str_replace('_cookie_na', '_netatmo', $cookiename);
      return array($cookiename, $value);
    }
  }
  return false;
}

public function getHomeData(){
  return $this->_request('POST', self::URL_HOST.'/api/gethomedata&size=1');
}

/*     * **********************Getteur Setteur*************************** */

public function setToken($_token){
  $this->_token = $_token;
}

public function getToken(){
  return $this->_token;
}

}
?>
