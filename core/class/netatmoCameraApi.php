<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

class NetatmoCameraAPI {
  
  /*     * *************************Attributs****************************** */
  
  public $csrf = null;
  public $csrfName = null;
  public $token = null;
  public $home = null;
  
  /*     * ***********************Methode static*************************** */
  
  /*     * *********************Methode d'instance************************* */
  
  function __construct($_username, $_password, $home_id=null,$_csrf=null,$_csrfName=null,$_token=null){
    if($_csrf != null && $_csrfName != null && $_token != null){
      $this->csrf = $_csrf;
      $this->csrfName = $_csrfName;
      $this->token = $_token;
      try {
        $this->getDatas($home_id);
        return;
      } catch (\Exception $e) {
        
      }
    }
    $this->connect($_username,$_password);
    $this->getDatas($home_id);
  }
  
  protected function getCSRF($answerString){
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
        $cookiename = str_replace('_cookie_na', '_netatmo', str_replace('netatmocom', '', $name));
        return array($cookiename, $value);
      }
    }
    return false;
  }
  
  protected function connect($_username,$_password){
    $answer = $this->_request('GET', 'https://my.netatmo.com');
    $var = $this->getCSRF($answer);
    if ($var != false){
      $this->csrfName = $var[0];
      $this->csrf = $var[1];
    }else{
      throw new \Exception("Couldn't find Netatmo CSRF.");
    }
    $answer = $this->_request('GET', 'https://auth.netatmo.com/en-us/access/login?message=__NOT_LOGGED');
    $loginTokenStart = strpos($answer, '"_token" value="') + 16;
    $loginTokenEnd = strpos($answer, '">', $loginTokenStart);
    $loginTokenLength = ($loginTokenEnd - $loginTokenStart);
    if ($loginTokenLength <= 0) {
      throw new \Exception("Couldn't find Netatmo token on login page.");
    }
    $loginToken = substr($answer, $loginTokenStart, ($loginTokenLength));
    $post = "email=".urlencode($_username)."&password=".urlencode($_password)."&_token=".$loginToken;
    $answer = $this->_request('POST', 'https://auth.netatmo.com/access/postlogin', $post);
    $cookies = explode('Set-Cookie: ', $answer);
    foreach($cookies as $var){
      if (strpos($var, 'netatmocomaccess_token') === 0){
        $token =  urldecode(str_replace('netatmocomaccess_token=', '', explode(';', $var)[0]));
        if ($token != 'deleted'){
          $this->token = $token;
          return true;
        }
      }
    }
    throw new \Exception("Couldn't find Netatmo token.");
  }
  
  public function setOutAlert($_type,$_mode='record'){
    $result = json_decode($this->_request(
      'POST',
      'https://app.netatmo.net/api/updatehome',
      'home_id='.$this->home['id'].'&presence_settings['.$_type.']='.$_mode
    ));
    if(!isset($result['status']) || $result['status'] != 'ok'){
      throw new \Exception('Error on setOutAlert for '.$_type.' => '.json_encode($result));
    }
  }
  
  public function getDatas($home_id = null){
    $datas = json_decode($this->_request('POST','https://app.netatmo.net/api/gethomedata'), true);
    if(isset($datas['error']) && isset($datas['error']['code'])){
      throw new \Exception('Error : '.json_encode($datas));
    }
    if($home_id == null){
      $homedata = $datas['body']['homes'][0];
    }else{
      foreach ($datas['body']['homes'] as $home){
        if ($home['id'] == $home_id){
          $homedata = $home;
          break;
        }
      }
    }
    if(!isset($homedata)){
      throw new \Exception('Home not found : '.$home_id);
    }
    $data = array(
      'id' => $homedata['id'],
      'name' => $homedata['name'],
      'share_info' => $homedata['share_info'],
      'gone_after' => $homedata['gone_after'],
      'smart_notifs' => $homedata['smart_notifs'],
      'presence_record_humans' => $homedata['presence_record_humans'],
      'presence_record_vehicles' => $homedata['presence_record_vehicles'],
      'presence_record_animals' => $homedata['presence_record_animals'],
      'presence_record_alarms' => $homedata['presence_record_alarms'],
      'presence_record_movements' => $homedata['presence_record_movements'],
      'presence_notify_from' => gmdate('H:i', $homedata['presence_notify_from']),
      'presence_notify_to' => gmdate('H:i', $homedata['presence_notify_to']),
      'presence_enable_notify_from_to' => $homedata['presence_enable_notify_from_to'],
      'place' => $homedata['place']
    );
    $this->home = $data;
    return true;
  }
  
  protected function _request($method, $url, $post=null){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_COOKIEJAR, '');
    curl_setopt($ch, CURLOPT_COOKIEFILE, '');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_REFERER, 'http://www.google.com/');
    curl_setopt($ch, CURLOPT_USERAGENT, 'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:51.0) Gecko/20100101 Firefox/51.0');
    curl_setopt($ch, CURLOPT_ENCODING , '');
    curl_setopt($ch, CURLOPT_URL, $url);
    if ($method == 'POST'){
      curl_setopt($ch, CURLOPT_POST, true);
      if (isset($post)) $post .= '&'.$this->csrfName.'='.$this->csrf;
      curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
      if (isset($this->token)){
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Connection: keep-alive',
          'Content-Type: application/x-www-form-urlencoded',
          'Authorization: Bearer '.$this->token)
        );
      }
    }else{
      curl_setopt($ch, CURLOPT_HTTPGET, true);
    }
    $response = curl_exec($ch);
    if ($response === false){
      throw new \Exception('cURL error: ' . curl_error($ch));
    }
    return $response;
  }
}
?>
