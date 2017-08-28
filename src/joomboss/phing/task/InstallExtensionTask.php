<?php
namespace joomboss\phing\task;

class InstallExtensionTask extends \Task {

  /**
   * The message passed in the buildfile.
   */
  private $url = null;
  private $version="2.5";
  private $user=null;
  private $password=null;
  private $source=null;
  private $ftpUser=null;
  private $ftpPassword=null;

  public function setUrl($str) {
    $this->url = $str;
  }
  public function setVersion($str) {
    $this->version = $str;
  }
  public function setUser($str) {
    $this->user = $str;
  }
  public function setPassword($str) {
    $this->password = $str;
  }
  public function setSource($str) {
    $this->source = $str;
  }
  public function setFtpuser($str) {
    $this->ftpUser = $str;
  }
  public function setFtppassword($str) {
    $this->ftpPassword = $str;
  }

  /**
   * The init method: Do init steps.
   */
  public function init() {
    // nothing to do here
  }

  /**
   * The main entry point method.
   */
  public function main() {
    $j = null;
    if($this->version =="1.5"){
      $j = new JoomlaClient($this->url);
    }else{
      $j = new Joomla2Client($this->url);
    }
    if(!$j->login($this->user, $this->password)){
      throw new \Exception($j->getErrorMessage());
    }
    if( !$j->installComponent($this->source, $this->ftpUser, $this->ftpPassword )){
      throw new \Exception($j->getErrorMessage());
    }
  }
}

class JoomlaClient{
  protected $cookieFile;
  protected $baseUrl;
  protected $errorMessage;
  public function __construct($url){
    $url = trim($url);
    $this->baseUrl = substr($url, -1) =="/" ? substr($url, 0, -1) : $url;
    $this->baseUrl .= "/administrator/index.php";
    $this->cookieFile = tempnam(sys_get_temp_dir(), "phing_cookie");
  }
  public function setErrorMessage($str){
    $this->errorMessage = $str;
  }
  public function getErrorMessage(){
    return $this->errorMessage;
  }
  public function login($login, $pass){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->baseUrl);
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
    curl_setopt($ch, CURLOPT_HTTPHEADER,array("Expect:"));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $body = curl_exec($ch);
    $token = $this->getToken($body);

    $params = array(
        "option"=>"com_login",
        "task"=>"login",
        "username"=>$login,
        "passwd"=>$pass,
        $token=>"1");
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->baseUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $body = curl_exec($ch);
    if(strpos($body, "Edit User") !== false || strpos($body, "User Manager") || strpos($body, "Control Panel") !== false){
      return true;
    }else{
      $this->setErrorMessage("Unable to login");
      return false;
    }
  }

  public function installComponent($archieveFile,
      $ftpUser, $ftpPassword){
    curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $body = curl_exec($ch);
    $token = $this->getToken($body);
    //echo $token;
    $params = array(
        "install_package"=>"@".$archieveFile,
        $token=>1,
        "installtype"=>"upload",
        "task"=>"doInstall",
        "option"=>"com_installer",
        "username"=>$ftpUser,
        "password"=>$ftpPassword
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->baseUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $body = curl_exec($ch);
    preg_match("/<dd class=\"error message fade\">\\s*<ul>\\s*<li>(.*?)<\\/li>/i", $body, $matches);
    if(isset($matches[1]) && $matches[1]){
      $this->setErrorMessage($matches[1]);
      return false;
    }else{
      return true;
    }
  }

  protected function getToken($body){
    $result="";
    preg_match("/<input type=\"hidden\" name=\"([a-z0-9]+)\" value=\"1\" \\/>/i", $body, $matches);
    if(isset($matches[1])){
      $result = $matches[1];
    }
    return $result;
  }
}
class Joomla2Client extends JoomlaClient{
  public function __construct($url){
    parent::__construct($url);
  }

  var $error_markers = array(
      "<div class=\"alert alert-error\">",
      "<dd class=\"error message fade\">"
  );

  var $error_message_regexps = array(
      "/<dd class=\"error message fade\">\\s*<ul>\\s*<li>(.*?)<\\/li>/i",
      "/<div class=\"alert-message\">(.*?)<\\/div>/i"
  );
  public function installComponent($packageFile, $ftpUser, $ftpPassword){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->baseUrl."?option=com_installer");
    curl_setopt ($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $body = curl_exec($ch);
    $token = $this->getToken($body);
    //echo $token;
    $params = array(
        "install_package"=>new \CURLFile($packageFile),
        $token=>1,
        "installtype"=>"upload",
        "task"=>"install.install",
        "option"=>"com_installer"//,
        //"username"=>$ftpUser,
        //"password"=>$ftpPassword
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
    curl_setopt($ch, CURLOPT_URL, $this->baseUrl."?option=com_installer&view=install");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt ($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $body = curl_exec($ch);
    
    $success = true;
    $message = "";
    foreach($this->error_markers as $error_marker){
        if(strpos($body, $error_marker)!==false){
            foreach($this->error_message_regexps as $regexp){
              preg_match_all($regexp, $body, $matches);
              if(isset($matches[1]) && is_array($matches[1])){
                  var_dump($matches[1]);
                  foreach($matches[1] as $match){
                    $message .= $match . "\n";
                  }
              }
            }
            $this->setErrorMessage($message);
            return false;
        }
    }
    return true;
  }
}

