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
  public $error = null;
  public $_csrf = null;
  public $_csrfName = null;
  public $_token = null;
  public $_homeID = 0;
  public $_homeName = null;
  public $_timeZone = null;
  public $_home = null;
  protected $_fullDatas;
  protected $_Netatmo_user;
  protected $_Netatmo_pass;
  protected $_urlStart = 'https://my.netatmo.com';
  protected $_urlHost = 'https://app.netatmo.net';
  protected $_urlAuth = 'https://auth.netatmo.com';
  protected $_curlHdl = null;
  
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
        $cookiename = str_replace('netatmocom', '', $name);
        $cookiename = str_replace('_cookie_na', '_netatmo', $cookiename);
        return array($cookiename, $value);
      }
    }
    return false;
  }
  
  protected function connect(){
    $url = $this->_urlStart;
    $answer = $this->_request('GET', $url);
    $var = $this->getCSRF($answer);
    if ($var != false){
      $this->_csrfName = $var[0];
      $this->_csrf = $var[1];
    }else{
      $this->error = "Couldn't find Netatmo CSRF.";
      return false;
    }
    $url = $this->_urlAuth.'/en-us/access/login?message=__NOT_LOGGED';
    $answer = $this->_request('GET', $url);
    $loginTokenStart = strpos($answer, '"_token" value="') + 16;
    $loginTokenEnd = strpos($answer, '">', $loginTokenStart);
    $loginTokenLength = ($loginTokenEnd - $loginTokenStart);
    if ($loginTokenLength <= 0) {
      $this->error = "Couldn't find Netatmo _token on login page.";
      return false;
    }
    $loginToken = substr($answer, $loginTokenStart, ($loginTokenLength));
    $url = $this->_urlAuth.'/access/postlogin';
    $post = "email=".$this->_Netatmo_user."&password=".$this->_Netatmo_pass."&_token=".$loginToken;
    $answer = $this->_request('POST', $url, $post);
    $cookies = explode('Set-Cookie: ', $answer);
    foreach($cookies as $var){
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
    $this->error = "Couldn't find Netatmo token.";
    return false;
  }
  
  function __construct($Netatmo_user, $Netatmo_pass, $homeName=0,$_csrf=null,$_csrfName=null,$_token=null){
    $this->_Netatmo_user = urlencode($Netatmo_user);
    $this->_Netatmo_pass = urlencode($Netatmo_pass);
    if ($homeName !== 0){
      $this->_homeName = $homeName;
      $this->_homeID = -1;
    }
    if($_csrf != null && $_csrfName != null && $_token != null){
      $this->_csrf = $_csrf;
      $this->_csrfName = $_csrfName;
      $this->_token = $_token;
      if (!$this->getDatas()){
        $this->connect();
        $this->getDatas();
      }
    }elseif ($this->connect()){
      $this->getDatas();
    }
  }
  
  public function setHumanOutAlert($value=1){
    $mode = null;
    if ($value == 0) $mode = 'ignore';
    if ($value == 1) $mode = 'record';
    if ($value == 2) $mode = 'record_and_notify';
    if (!isset($mode)) return array('error'=>'Set 0 for ignore, 1 for record, 2 for record and notify');
    $setting = 'presence_settings[presence_record_humans]';
    $url = $this->_urlHost.'/api/updatehome';
    $post = 'home_id='.$this->_home['id'].'&'.$setting.'='.$mode;
    $answer = $this->_request('POST', $url, $post);
    $answer = json_decode($answer, true);
    return array('result'=>$answer);
  }
  
  public function setAnimalOutAlert($value=1){
    $mode = null;
    if ($value == 0) $mode = 'ignore';
    if ($value == 1) $mode = 'record';
    if ($value == 2) $mode = 'record_and_notify';
    if (!isset($mode)) return array('error'=>'Set 0 for ignore, 1 for record, 2 for record and notify');
    $setting = 'presence_settings[presence_record_animals]';
    $url = $this->_urlHost.'/api/updatehome';
    $post = 'home_id='.$this->_home['id'].'&'.$setting.'='.$mode;
    $answer = $this->_request('POST', $url, $post);
    $answer = json_decode($answer, true);
    return array('result'=>$answer);
  }
  
  public function setVehicleOutAlert($value=1){
    $mode = null;
    if ($value == 0) $mode = 'ignore';
    if ($value == 1) $mode = 'record';
    if ($value == 2) $mode = 'record_and_notify';
    if (!isset($mode)) return array('error'=>'Set 0 for ignore, 1 for record, 2 for record and notify');
    $setting = 'presence_settings[presence_record_vehicles]';
    $url = $this->_urlHost.'/api/updatehome';
    $post = 'home_id='.$this->_home['id'].'&'.$setting.'='.$mode;
    $answer = $this->_request('POST', $url, $post);
    $answer = json_decode($answer, true);
    return array('result'=>$answer);
  }
  
  public function setOtherOutAlert($value=1){
    $mode = null;
    if ($value == 0) $mode = 'ignore';
    if ($value == 1) $mode = 'record';
    if ($value == 2) $mode = 'record_and_notify';
    if (!isset($mode)) return array('error'=>'Set 0 for ignore, 1 for record, 2 for record and notify');
    $setting = 'presence_settings[presence_record_movements]';
    $url = $this->_urlHost.'/api/updatehome';
    $post = 'home_id='.$this->_home['id'].'&'.$setting.'='.$mode;
    $answer = $this->_request('POST', $url, $post);
    $answer = json_decode($answer, true);
    return array('result'=>$answer);
  }
  
  public function getDatas($eventNum=100){
    $url = $this->_urlHost.'/api/gethomedata'."&size=".$eventNum;
    $answer = $this->_request('POST', $url);
    $jsonDatas = json_decode($answer, true);
    $this->_fullDatas = $jsonDatas;
    if ($this->_homeID == -1){
      $var = $this->getHomeByName();
      if (!$var == true) return $var;
    }
    $homedata = $this->_fullDatas['body']['homes'][$this->_homeID];
    $data = array(
      'id' => $homedata['id'],
      'name' => $homedata['name'],
      'share_info' => $homedata['share_info'],
      'gone_after' => $homedata['gone_after'],
      'smart_notifs' => $homedata['smart_notifs'],
      'presence_record_humans' => $homedata['presence_record_humans'], //Presence
      'presence_record_vehicles' => $homedata['presence_record_vehicles'], //Presence
      'presence_record_animals' => $homedata['presence_record_animals'], //Presence
      'presence_record_alarms' => $homedata['presence_record_alarms'], //Presence
      'presence_record_movements' => $homedata['presence_record_movements'], //Presence
      'presence_notify_from' => gmdate('H:i', $homedata['presence_notify_from']), //Presence
      'presence_notify_to' => gmdate('H:i', $homedata['presence_notify_to']), //Presence
      'presence_enable_notify_from_to' => $homedata['presence_enable_notify_from_to'], //Presence
      'notify_movements' => $homedata['notify_movements'], //welcome
      'record_movements' => $homedata['record_movements'], //welcome
      'notify_unknowns' => $homedata['notify_unknowns'], //welcome
      'record_alarms' => $homedata['record_alarms'], //welcome
      'record_animals' => $homedata['record_animals'], //welcome
      'notify_animals' => $homedata['notify_animals'], //welcome
      'events_ttl' => $homedata['events_ttl'], //welcome
      'place' => $homedata['place']
    );
    $this->_home = $data;
    $this->_homeName = $homedata['name'];
    $this->_timeZone = $homedata['place']['timezone'];
    return true;
  }
  
  protected function getHomeByName(){
    $fullData = $this->_fullDatas['body']['homes'];
    $idx = 0;
    foreach ($fullData as $home){
      if ($home['name'] == $this->_homeName){
        $this->_homeID = $idx;
        return true;
      }
      $idx ++;
    }
    $this->error = "Can't find home named ".$this->_homeName;
  }
  
  protected function _request($method, $url, $post=null){
    if (!isset($this->_curlHdl)){
      $this->_curlHdl = curl_init();
      curl_setopt($this->_curlHdl, CURLOPT_COOKIEJAR, '');
      curl_setopt($this->_curlHdl, CURLOPT_COOKIEFILE, '');
      curl_setopt($this->_curlHdl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($this->_curlHdl, CURLOPT_HEADER, true);
      curl_setopt($this->_curlHdl, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($this->_curlHdl, CURLOPT_REFERER, 'http://www.google.com/');
      curl_setopt($this->_curlHdl, CURLOPT_USERAGENT, 'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:51.0) Gecko/20100101 Firefox/51.0');
      curl_setopt($this->_curlHdl, CURLOPT_ENCODING , '');
    }
    curl_setopt($this->_curlHdl, CURLOPT_URL, $url);
    if ($method == 'POST'){
      curl_setopt($this->_curlHdl, CURLOPT_POST, true);
      if ( isset($post)) $post .= '&'.$this->_csrfName.'='.$this->_csrf;
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
    echo 'cURL error: ' . curl_error($this->_curlHdl);
  }else{
    return $response;
  }
}
}
?>
