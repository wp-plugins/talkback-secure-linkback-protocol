<?php

/* !
 * TalkBack client side Library.
 * @Author: Elie Bursztein (elie _AT_ cs.stanford.edu), Baptiste Gourdin (bgourdin _AT_ cs.stanford.edu)
 * @version: 1.0
 * @url: http://talkback.stanford.edu
 * @Licence: LGPL
 */
include "work/keyConfig.php";

class TalkBack
{

//!internal variables

  protected $path, $debug, $receptionPolicy, $encryption, $logPath;
  protected $privKey, $pubKey, $randString, $authPubKey, $authorityUrl, $authorityRequestUrl;
  protected $hash, $seed, $nbToken, $config, $cryptoSuite, $rcvPubKey;
  protected $notification, $lastTb;
  public $lastError;

  /**
   * Intialize an instance of the TalkBack class
   * @param $path where the keys are  stored. Can be replace with a db
   * @param $debug to enable the debug output
   * @return none
   * @author Elie Bursztein
   * @version 1.0
   */
  public function __construct($debug = FALSE)
  {
    global $tbConfig;

    $this->config = $tbConfig;
    $this->path = $tbConfig['tbDataDir'];
    $this->logPath = $tbConfig['logDir'];
    $this->debug = $debug;
    $this->authorityUrl = $tbConfig['authorityUrl'];
    $this->authorityRequestUrl = $tbConfig['authorityUrl'] . "/request.php";
    $this->cryptoSuite = "RSA-SHA1";
    $this->encryption = $tbConfig['encryption'];
    $this->receptionPolicy = $tbConfig['receptionPolicy'];
    $this->lastError = "";
  }

  /**
   * Load keys and data in memory
   * @param $priv Boolean used to tell if the private key should be loaded. Default is FALSE as the private key should be loaded only when needed
   * @return TRUE if everything is okay, false otherwise.
   * @author Elie Bursztein
   * @version 1.0
   */
  public function load($priv = FALSE, $randStr = TRUE)
  {
    global $privKeyPath;

//public key
    if (!file_exists($this->path . "/pub.key")
            || !($this->pubKey = file_get_contents($this->path . "/pub.key")))
      return $this->printDebug("Error:load Can't read the pub key()" . $this->path . "/pub.key" . "\n ");

//private key
    if ($priv) //do we need to load the priv key ?
      if ((!isset($privKeyPath)) || !file_exists($this->path . "/$privKeyPath")
              || !($this->privKey = file_get_contents($this->path . "/$privKeyPath")))
        return $this->printDebug("Error:load Can't read the private key\n ");

//random string
    if ($randStr)
      if (!file_exists($this->path . "/randomString.txt")
              || !($this->randString = file_get_contents($this->path . "/randomString.txt")))
        return $this->printDebug("Error:load Can't read the authority random string\n ");

//authority public key
    if (!file_exists($this->path . "/authority-pub.key")
            || !($this->authPubKey = file_get_contents($this->path . "/authority-pub.key")))
      return $this->printDebug("Error:load Can't read the authority pub key\n ");

    if (!function_exists("curl_init"))
	return $this->printDebug("Error:function curl_init not defined. CURL module for php must be installed in order to use Talkback ");

    return TRUE;
  }

  private function printDebug($str)
  {
    file_put_contents($this->logPath . "/errors.log", date("d/m/y : H:i:s", time()) . " : " . $str . "\n", FILE_APPEND);
    $this->lastError = $str;

    return FALSE;
  }

  /*
   * crypto functions
   */

  /**
   * Generate the public/private key pair and store then in $path directory
   * @return TRUE if everything is okay FALSE otherwise
   * @author Elie Bursztein
   * @version 1.0
   */
  public function keyGen()
  {
      global $privKeyPath;
      
      if (isset($privKeyPath))
        return TRUE;
      
    $privKeyPath = sha1(mt_rand() . mt_rand());

// Create the keypair
    if (!is_writable($this->path)) {
      print "Error: " . $this->path . " is not writable ";
      return $this->printDebug("Error:keyGen " . $this->path . " is not writable ");
    }

    $res = openssl_pkey_new();
    if (!$res)
      return $this->printDebug("Error generating keys, please verify your openssl configuration");

// Get private key
    openssl_pkey_export($res, $privatekey);
    $this->privKey = $privatekey;

//writing private key to the disk
    if ((!file_put_contents($this->path . "/" . $privKeyPath . ".key", $this->privKey)) ||
            (!file_put_contents($this->path . "/keyConfig.php", '<?php $privKeyPath="' . $privKeyPath . '.key"; ?>')))
      return $this->printDebug("Error generating keys, cannot store the private key (" . $this->path . "/$privKeyPath.key)\n ");

// Get public key
    $publickey = openssl_pkey_get_details($res);
    $this->pubKey = $publickey["key"];

//writing public key to the disk
    if (!file_put_contents($this->path . "/pub.key", $this->pubKey))
      return $this->printDebug("Error generating keys, cannot store the public key (" . $this->path . "/pub.key)\n ");

    return TRUE;
  }

  /**
   * Return the public key in its armored version
   * @return TRUE if the pubic key is loaded FALSE otherwise
   * @author Elie Bursztein
   * @version 1.0
   */
  public function keyGetPub()
  {
    if (!$this->pubKey)
      return $this->printDebug("Error:keyGetPub(): Can't read the public key \n ");
    return $this->pubKey;
  }

  /**
   * Return the crypto suite used by the client
   * @return TRUE if found FALSE otherwise
   * @author Elie Bursztein
   * @version 1.0
   */
  public function getCryptoSuite()
  {
//FIXME: store the crypto suite at generation time.
    return $this->cryptoSuite;
  }

  /*
   * Token functions
   */

  /**
   * Get the seed value and the number of token, we can use from the authority
   * @param $hash hash of the talkback
   * @return TRUE if found FALSE otherwise
   * @author Elie Bursztein
   * @version 1.0
   */
  public function getSeed()
  {
    if (file_exists($this->path . "/seeds")) {
      $lines = file($this->path . "/seeds", FILE_IGNORE_NEW_LINES);
      foreach ($lines as $line) {
        $line = explode(":", $line);
        $seed = $line[1];
        $nbToken = intval($line[2]);
        $expirationTime = $line[3];

        if (($this->hash == $line[0]) && (intval($expirationTime) > time ())) {
          $this->seed = $seed;
          $this->nbToken = $nbToken;
          return TRUE;
        }
      }
    }

    return $this->requestNewSeed();
  }

  /**
   * Save the new seed in the file "seeds"
   * @param $hash hash of the talkback
   * @param $seed seed received
   * @param $nbToken number of token available for this seed
   * @param $expirationTime  Expiration time of the seed
   * @return TRUE is the seed has been saved, FALSE otherwise
   */
  private function saveNewSeed($hash, $seed, $nbToken, $expirationTime)
  {
    $seeds = "";

    if (file_exists($this->path . "/seeds")) {
      $lines = file($this->path . "/seeds", FILE_IGNORE_NEW_LINES);
      foreach ($lines as $line) {
        $line2 = explode(":", $line);
        if (intval($line2[3]) > time ())
          $seeds .= $line . "\n";
      }
    }
    if (!file_put_contents($this->path . "/seeds", $hash . ":" . $seed . ":" . $nbToken . ":" . $expirationTime . "\n" . $seeds))
      return $this->printDebug("Error:saveNewSeed: can't write to file " . $this->path . "/seeds" . "\n");
    else
      return TRUE;
  }

  private function saveConsumedSeed($hash, $seed, $nbToken)
  {
    $seeds = "";

    if (!file_exists($this->path . "/seeds"))
      return $this->printDebug("Error:saveConsumedSeed: seeds file does not exists\n");

    $lines = file($this->path . "/seeds", FILE_IGNORE_NEW_LINES);
    foreach ($lines as $line) {
      $line2 = explode(":", $line);
      if ($line2[0] == $hash) {
        if ($nbToken != 0)
          $seeds .= $hash . ":" . $seed . ":" . $nbToken . ":" . $line2[3] . "\n";
      }
      else {
        if (intval($line2[3]) > time ())
          $seeds .= $line . "\n";
      }
    }

    if (!file_put_contents($this->path . "/seeds", $seeds))
      return $this->printDebug("Error:saveConsumedSeed: can't write to file " . $this->path . "/seeds" . "\n");
    else
      return TRUE;
  }

  /**
   * Request a new seed from the authority
   *
   * @param $hash hash of the TB
   * @return TRUE if new seed received, FALSE otherwise
   */
  private function requestNewSeed()
  {

    $hash = time() . ":" . base64_encode($this->hash);
    if (!openssl_public_encrypt($hash, $hash, $this->authPubKey))
      return $this->printDebug("Error requestNewSeed : openssl_public_encrypt returned false");

    $this->notification = array(
        'action' => "seed",
        'hash' => base64_encode($hash),
        'sender_key' => base64_encode($this->pubKey),
    );

//create signature
    $toSign = "";
    foreach ($this->notification as $key => $value)
      $toSign .= $key . '=' . $value . '&';
    $toSign = rtrim($toSign, '&');

    if (!openssl_sign($toSign, $signature, $this->privKey))
      return $this->printDebug("Error:getSeed: can't sign the data, did you load the private key ?\n");
//add it to the post variable
    $this->notification['sender_signature'] = base64_encode($signature);

//send the seed request to the authority.
    if (($result = $this->postData($this->authorityRequestUrl)) == FALSE)
      return $this->printDebug("Error:getSeed: can't send seed request to the authority. Is there any firewall issue ?\n");


    $result = $this->getAuthorityResponse($result, $this->authPubKey, $msg);
    if (!$result)
      return FALSE;


    if (!preg_match('/seed:(.+)/', $result, $matches))
      return $this->printDebug("Error:getSeed: Can't parse authority reponse ($result)\n");

//decoding the seed
    if (openssl_private_decrypt(base64_decode($matches[1]), $result, $this->privKey) == FALSE)
      return $this->printDebug("Error:getSeed: can't decrypt seed data ($result)\n");

    if ($result == "refused") {
      $this->lastError = "No more seed available, please try later";
      return FALSE;
    } else if (preg_match('/seed:(.+)&num:(.+)&expirationTime:(.+)/', $result, $matches)) {
//geeting the seed and number of token
      $this->seed = $matches[1];
      $this->nbToken = $matches[2];
      return $this->saveNewSeed($this->hash, $matches[1], $matches[2], $matches[3]);
    }
    else
      return $this->printDebug("Error:getSeed: Can't parse authority reponse ($result)\n");
  }

  /**
   * Get the current token based on the seed provided by the authority
   * The token generation is the S/KEY algorithm.
   * @return the token or -1 if no seed or -2 if the maximum number of token is sent.
   * @see getSeed()
   * @author Elie Bursztein
   * @version 1.0
   */
  public function getToken()
  {
    if ($this->nbToken == 0)
      return -2;
    if (!isset($this->seed))
      return -1;

//do a simple skey
    $token = $this->seed;
    for ($i = 0; $i < $this->nbToken; $i++)
      $token = sha1($token);
//burning one token
    $this->nbToken--;
    return $token;
  }

  /* ====================== Notification functions ====================== */

  /**
   * Send a notification: Fetch infos, getSeed from autority and if allowed, create and send notification
   * @param $url Url to send notification
   * @return String with an error message if failed, else an empty string
   * @author Baptiste Gourdin
   * @version 1.0
   * @date Apr 2010
   */
  public function sendTalkback($url, $postUrl, $postTitle, $postExcerpt, &$msg)
  {

    if ($this->isNotificationSent($postUrl, $url)) {
      $msg = "This talkback has already been sent and accepted by the receiver.";
      $this->log($url . " : " . $msg);
      return FALSE;
    }

    if (!$this->fetchBlogInfo($url)) {
      $msg = "The requested blog do not support the talkback protocol\n";
      $this->log($url . " : " . $msg);
      return FALSE;
    }

//fetch our destination blog crypto suite and public key
    if (!$this->fetchBlogCrypto($url)) {
      $msg = "The requested blog do not provide its public key, please notify it\n";
      $this->log($url . " : " . $msg);
      return FALSE;
    }


    if (!$tbData = $this->createTalkback($this->config['blogName'], $postUrl, $postTitle, $postExcerpt))
      return $this->printDebug("Error:createTalkback: the function was called with undefined variables\n");

//requesting the seed from the authority
// getting the seed should be done only one time by post.here we only post one notification so we don't implement the checking logic.
    if ($this->getSeed() == 0) {
      $msg = ($this->lastError != "") ? $this->lastError : "the authority refused to grant us a seed. Rate limiting must be exceeded. View you account status to request more notification\n";
      $this->log($url . " : " . $msg);
      return FALSE;
    }

//get the notification token
    if ((($token = $this->getToken()) == -1)) {
      $msg = "Seed not availabe\n";
      $this->log($url . " : " . $msg);
      return FALSE;
    } elseif ($token == -2) {
      $msg = "Seed exhausted, please request another token\n";
      $this->log($url . " : " . $msg);
      return FALSE;
    }

//generate the notification
    if (!$this->createNotification($tbData, $token)) {
      $msg = "can't build the notification something must be wrong, use the debug mode to know more\n";
      $this->log($url . " : " . $msg);
      return FALSE;
    }

    if (!$this->sendNotification($msg)) {
      if ($msg == "")
        $msg = "can't send notification something must be wrong, maybe the receiver is offline or the notification page is broken\n";
      $this->log($url . " : " . $msg);
      return FALSE;
    }

    $this->log($url . " : Talkback sent successfully");
    $this->logSentNotification($postUrl, $url);
    return TRUE;
  }

  /**
   * Proceed a received notification.
   * @param $data	Data received with the notification ($_POST)
   * @param $fromUrl    url of the sender post
   * @param $msg	Message to send back to the sender
   * @param $blog_name	Save the blog name this parameter
   * @param $url        Save the post url this parameter
   * @param $title	Save the post title this parameter
   * @param $excerpt	Save the post excerpt this parameter
   * @return TRUE if the notification is accepted
   * @see parseNotification()
   * @see verifyPlainNotification()
   * @see validateNotification()
   * @author Baptiste Gourdin
   * @version 1.0
   * @date Apr 2010
   */
  public function notifyMe($data, $toUrl, &$msg, &$blog_name, &$fromUrl, &$title, &$excerpt)
  {
    $version = base64_decode($data['version']);
    if (($version == 'plain') && ($this->receptionPolicy == 'encrypted')) {
      $msg = "403 Restricted blog, you are not autorized to send a talback not encrypted";
      $this->logReceivedNotification("?", $toUrl, "Refused (plain talkback not accepted)");
      return FALSE;
    }
    if (($version == 'encrypted') && ($this->receptionPolicy == 'plain')) {
      $msg = "403  Restricted blog, you are not autorized to send an encrypted talback ";
      $this->logReceivedNotification("?", $toUrl, "Refused (encrypted talkback not accepted)");
      return FALSE;
    }

// parse and verify the crypto : public key and signature
    if ($version == "plain") {
      if ($this->verifyPlainNotification($data) == FALSE) {
        $msg = "403  Can't verify talkback notification, publicKey or signature is not correct\n";
        $this->logReceivedNotification("?", $toUrl, "Refused (incorrect talkback)");
        return FALSE;
      }
    } else
    if ($this->verifyEncryptedNotification($data) == FALSE) {
      $msg = "403 Can't verify talkback notification, publicKey or signature is not correct\n";
      $this->logReceivedNotification("?", $toUrl, "Refused (incorrect talkback)");
      return FALSE;
    }


    if ($this->config['WLonly']) {
      if ($this->isWhitelisted($_POST['sender_key'])) {
        $blog_name = htmlspecialchars($this->lastTb['blog_name']);
        $url = htmlspecialchars($this->lastTb['url']);
        $title = htmlspecialchars($this->lastTb['title']);
        $excerpt = htmlspecialchars($this->lastTb['excerpt']);
        $this->logReceivedNotification($url, $toUrl, "Accepted (Whitelist)");
        $msg = "200 OK";
        return TRUE;
      } else {
        $msg = "403 Restricted blog, you are not autorized to send a talback.";
        $this->logReceivedNotification("?", $toUrl, "Refused (Whitelist)");
        return FALSE;
      }
    }

    if ($this->config['queuing']) {
      $this->enqueueNotification($toUrl, $data);
      $msg = "200 OK";
      return FALSE;
    } else {
//validate with the authority
      if ($this->validateNotification() == FALSE) {
//      $msg = "403 Can't validate talkback notification with the authority\n";
        $msg = "200 OK";
        $this->logReceivedNotification($url, $toUrl, "Refused (Authority refused)");
        return FALSE;
      } else {
        $blog_name = htmlspecialchars($this->lastTb['blog_name']);
        $fromUrl = htmlspecialchars($this->lastTb['url']);
        $title = htmlspecialchars($this->lastTb['title']);
        $excerpt = htmlspecialchars($this->lastTb['excerpt']);
        $this->logReceivedNotification($fromUrl, $toUrl, "Accepted");
        $msg = "200 OK";
        return TRUE;
      }
    }
  }

  /**
   * Generate the TB with its hash, encrypt it if encryption enabled
   * @param $blogName  this blog name
   * @param $url          the post url
   * @param $title        the post title
   * @param $excerpt      the post excerpt
   * @return Returns the TB array , FALSE if an error occured
   * @author Baptiste Gourdin
   * @version 1.0
   * @date Jul 2010
   */
  private function createTalkback($blog_name, $url, $title, $excerpt)
  {
    if (!isset($blog_name) or !isset($url) or !isset($title) or !isset($excerpt))
      return $this->printDebug("Error:createNotification: the function was called with undefined variables\n");

    $tbData = array(
        'blog_name' => $blog_name,
        'url' => $url,
        'title' => $title,
        'excerpt' => $excerpt
    );

    $this->hash = sha1($tbData['blog_name'] . $tbData['url'] . $tbData['title'] . $tbData['excerpt']);
    if ($this->encryption == TRUE)
      $this->encryptData($tbData, $this->rcvPubKey);

    return $tbData;
  }

  /**
   * If encryption enabled, call createEncryptedNotification else call createPlainNotification
   * @param $tbData       TB array
   * @param $token        the token received from the authority
   * @return TRUE if okay FALSE otherwise
   * @see createEncryptedNotification()
   * @see createPlainNotification()
   * @author Elie Bursztein
   * @version 1.0
   * @date oct 2009
   */
  public function createNotification($tbData, $token)
  {
    if ($this->encryption == TRUE)
      return $this->createEncryptedNotification($tbData, $this->hash, $token);
    else
      return $this->createPlainNotification($tbData, $this->hash, $token);
  }

  /**
   * Create a plain notification based on the information used previously
   * @param $tbData       The TB array
   * @param $hash         TB hash
   * @param $token        the token received from the authority
   * @return TRUE if okay FALSE otherwise
   * @see fetchBlogInfo()
   * @see fetchBlogCrypto()
   * @see getToken()
   * @author Elie Bursztein
   * @version 1.0
   * @date oct 2009
   */
  public function createPlainNotification($tbData, $hash, $token)
  {
//constructing the signature
    $this->notification = array(
        'action' => "notification",
        'crypto' => base64_encode($this->cryptoSuite),
        'version' => base64_encode("plain"),
        'hash' => base64_encode($hash),
        'token' => base64_encode($token),
        'timestamp' => base64_encode(time()),
        'authority_key' => base64_encode($this->authPubKey),
        'sender_key' => base64_encode($this->pubKey),
        'receiver_key' => base64_encode($this->rcvPubKey),
    );

    $toSign = "";
    foreach ($this->notification as $key => $value)
      $toSign .= $key . '=' . $value . '&';
    $toSign = rtrim($toSign, '&');

    if (!openssl_sign($toSign, $signature, $this->privKey))
      return $this->printDebug("Error:createNotification: can't sign the data, did you load the private key ?\n");

    $this->notification['sender_signature'] = base64_encode($signature);

    $this->notification['blog_name'] = base64_encode($tbData['blog_name']);
    $this->notification['url'] = base64_encode($tbData['url']);
    $this->notification['title'] = base64_encode($tbData['title']);
    $this->notification['excerpt'] = base64_encode($tbData['excerpt']);
    return TRUE;
  }

  /**
   * Create a encrypted notification based on the information used previously
   * @param $tbData       The TB array
   * @param $hash         TB hash
   * @param $token        the token received from the authority
   * @return TRUE if okay FALSE otherwise
   * @author Elie Bursztein
   * @version 0.0
   */
  public function createEncryptedNotification($tbData, $hash, $token)
  {


    //FIXME The hash should be encrypted
/*    $ts = time();
    $hash = $ts . ":" . base64_encode($hash);
    if (!openssl_public_encrypt($hash, $hash, $this->authPubKey))
      return $this->printDebug("Error createNotification : openssl_public_encrypt returned false");*/
$ts = time();
    $this->notification = array(
        'action' => "notification",
        'crypto' => base64_encode($this->cryptoSuite),
        'version' => base64_encode("encrypted"),
        'hash' => base64_encode($hash),
        'token' => base64_encode($token),
        'timestamp' => base64_encode($ts),
        'authority_key' => base64_encode($this->authPubKey),
        'sender_key' => base64_encode($this->pubKey),
        'receiver_key' => base64_encode($this->rcvPubKey)
    );

    $toSign = "";
    foreach ($this->notification as $key => $value)
      $toSign .= $key . '=' . $value . '&';
    $toSign = rtrim($toSign, '&');

    if (!openssl_sign($toSign, $signature, $this->privKey))
      return $this->printDebug("Error:createNotification: can't sign the data, did you load the private key ?\n");

    $this->notification['sender_signature'] = base64_encode($signature);
    $this->notification['blog_name'] = base64_encode($tbData['blog_name']);
    $this->notification['url'] = base64_encode($tbData['url']);
    $this->notification['title'] = base64_encode($tbData['title']);
    $this->notification['excerpt'] = base64_encode($tbData['excerpt']);
    $this->notification['key'] = base64_encode($tbData['key']);
    return TRUE;
  }

  /**
   * Verify that a plain notification is correctly signed.
   * @param $postData the notification to verify
   * @return TRUE if okay FALSE otherwise
   * @author Elie Bursztein
   * @version 1.0
   * @date oct 2009
   */
  public function verifyPlainNotification($postData)
  {
    $this->notification = array(
        'action' => $postData['action'],
        'crypto' => $postData['crypto'],
        'version' => $postData['version'],
        'hash' => $postData['hash'],
        'token' => $postData['token'],
        'timestamp' => $postData['timestamp'],
        'authority_key' => $postData['authority_key'],
        'sender_key' => $postData['sender_key'],
        'receiver_key' => $postData['receiver_key'],
    );
    $this->senderSig = base64_decode($postData['sender_signature']);

    $toVerify = "";
    foreach ($this->notification as $key => $value)
      $toVerify .= $key . '=' . $value . '&';
    $toVerify = rtrim($toVerify, '&');

    if ($this->pubKey != base64_decode($this->notification['receiver_key']))
      return $this->printDebug("Error:verifyPlainNotification: public key miss match\n");

    $this->lastTb = array(
        'blog_name' => base64_decode($postData['blog_name']),
        'url' => base64_decode($postData['url']),
        'title' => base64_decode($postData['title']),
        'excerpt' => base64_decode($postData['excerpt']));

    $hash = sha1($this->lastTb['blog_name'] . $this->lastTb['url']
                    . $this->lastTb['title'] . $this->lastTb['excerpt']);

    if ($hash != base64_decode($postData['hash']))
      return $this->printDebug("Error:verifyPlainNotification: hash don't match\n");

    if (!openssl_verify($toVerify, $this->senderSig, base64_decode($this->notification['sender_key'])) == 1)
      return $this->printDebug("Error:verifyPlainNotification: can't verify signature\n");


    $this->notification['sender_signature'] = $postData['sender_signature'];

//create our own signature
    $toSign = "";
    foreach ($this->notification as $key => $value)
      $toSign .= $key . '=' . $value . '&';
    $toSign = rtrim($toSign, '&');

    if (!openssl_sign($toSign, $signature, $this->privKey))
      return $this->printDebug("Error:validateNotification: can't sign the data, did you load the private key ?\n");

//adding our signature (note here: we can't change the action -> would break the signatures)
    $this->notification['receiver_signature'] = base64_encode($signature);

    $this->notification['blog_name'] = $postData['blog_name'];
    $this->notification['url'] = $postData['url'];
    $this->notification['title'] = $postData['title'];
    $this->notification['excerpt'] = $postData['excerpt'];

    return TRUE;
  }

  /**
   * Verify that an encrypted notification is correctly signed.
   * @param $postData the notification to verify
   * @return TRUE if okay FALSE otherwise
   * @author Elie Bursztein
   * @version 1.0
   * @date oct 2009
   */
  public function verifyEncryptedNotification($postData)
  {
    $this->notification = array(
        'action' => $postData['action'],
        'crypto' => $postData['crypto'],
        'version' => $postData['version'],
        'hash' => $postData['hash'],
        'token' => $postData['token'],
        'timestamp' => $postData['timestamp'],
        'authority_key' => $postData['authority_key'],
        'sender_key' => $postData['sender_key'],
        'receiver_key' => $postData['receiver_key'],
    );

    $this->senderSig = base64_decode($postData['sender_signature']);

    $toVerify = "";
    foreach ($this->notification as $key => $value)
      $toVerify .= $key . '=' . $value . '&';
    $toVerify = rtrim($toVerify, '&');

    if ($this->pubKey != base64_decode($this->notification['receiver_key']))
      return $this->printDebug("Error:verifyEncryptedNotification: public key miss match\n");

    if (!openssl_verify($toVerify, $this->senderSig, base64_decode($this->notification['sender_key'])) == 1)
      return $this->printDebug("Error:verifyEncryptedNotification: can't verify signature\n");

    $this->lastTb = array(
        'blog_name' => $postData['blog_name'],
        'url' => $postData['url'],
        'title' => $postData['title'],
        'excerpt' => $postData['excerpt'],
        'key' => $postData['key']
    );

    if (!$this->decrypData($this->lastTb))
      return FALSE;

    $this->lastTb['blog_name'] = base64_decode($this->lastTb['blog_name']);
    $this->lastTb['url'] = base64_decode($this->lastTb['url']);
    $this->lastTb['title'] = base64_decode($this->lastTb['title']);
    $this->lastTb['excerpt'] = base64_decode($this->lastTb['excerpt']);

    $hash = sha1($this->lastTb['blog_name'] . $this->lastTb['url']
                    . $this->lastTb['title'] . $this->lastTb['excerpt']);

    if ($hash != base64_decode($postData['hash']))
      return $this->printDebug("Error:verifyEncryptedNotification: hash don't match\n");

    $this->notification['sender_signature'] = $postData['sender_signature'];

//create our own signature
    $toSign = "";
    foreach ($this->notification as $key => $value)
      $toSign .= $key . '=' . $value . '&';
    $toSign = rtrim($toSign, '&');

    if (!openssl_sign($toSign, $signature, $this->privKey))
      return $this->printDebug("Error:validateNotification: can't sign the data, did you load the private key ?\n");

//adding our signature (note here: we can't change the action -> would break the signatures)
    $this->notification['receiver_signature'] = base64_encode($signature);


    return TRUE;
  }

  /**
   * Validate the validity of the notification with the authority
   * @return TRUE if valid FALSE otherwise
   * @author Elie Bursztein
   * @version 1.0
   * @date oct 2009
   */
  public function validateNotification()
  {
    $signature = base64_decode($this->notification['receiver_signature']);

    if (!($authUrl = $this->getAuthorityUrlFromPubKey($this->notification['authority_key'])))
      return $this->printDebug("Error:validateNotification: Unknown authority ?\n");

//send the notification to the authority to validate it.
    if (($result = $this->postData($authUrl. "/request.php")) == FALSE)
      return $this->printDebug("Error:validateNotification: can't send notification to the authority. Is there any firewall issue ($authUrl/request.php)?\n");

    $result = $this->getAuthorityResponse($result, base64_decode($this->notification['authority_key']), $msg);
    if (!$result)
      return FALSE;

//verify that it was our signature
    if (preg_match('/receiver_signature:([^&]+)/', $result, $matches)) {
      if ($matches[1] != base64_encode($signature))
        return $this->printDebug("Error:validateNotification: can't verify that it is our own signature\n");

//finishing by reading the answer
      if (preg_match('/decision:accepted/', $result))
        return TRUE;
      return $this->printDebug("Error:validateNotification: the authority rejected the notification ($result)\n");
    }
    return $this->printDebug("Error:validateNotification: Can't parse authority reponse ($result)\n");
  }

  /**
   * Send the notification to the targeted blog
   * @return TRUE if okay FALSE otherwise
   * @see sendNotification()
   * @author Elie Bursztein
   * @version 1.0
   * @date oct 2009
   */
  public function sendNotification(&$msg)
  {
    if (!isset($this->notification))
      return $this->printDebug("Error:sendNotification: the notification was not created. did you call createNotification() ? \n");
    if (!isset($this->rcvUrl))
      return $this->printDebug("Error:sendNotification: Notification page unknown. did you call fetchBlogInfo() ? \n");

    $result = $this->postData($this->rcvUrl);

    $this->saveConsumedSeed($this->hash, $this->seed, $this->nbToken);

    if (preg_match("/([0-9]{3})(.*)/", $result, $matches)) {
      switch ($matches[1]) {
        case "200":
          return TRUE;
        case "403":
          $msg = "Access forbidden (" . $matches[2] . ")";
          return FALSE;
        case "503":
          $msg = "Server error(" . $matches[2] . ")";
          return FALSE;
        default:
          return $this->printDebug("Error:sendNotification: Bad formatted answer ... ($result) ");
      }
    }
    else
      return $this->printDebug("Error:sendNotification: Bad formatted answer ...  ($result)");
  }

  /**
   * Internal function used to do the HTTP post of the notification table.
   * @param $url the url to post : either the blog or the authority
   * @return TRUE if okay FALSE otherwise
   * @see verifyPlainNotification()
   * @see validateNotification()
   * @author Elie Bursztein
   * @version 1.0
   * @date oct 2009
   */
  private function postData($url)
  {
//URLifcation of the notification data
    $notification_string = "";
    foreach ($this->notification as $key => $value)
      $notification_string .= urlencode($key) . '=' . urlencode($value) . '&';
    $notification_string = rtrim($notification_string, '&');

    if (($ch = curl_init()) == FALSE)
      return $this->printDebug("Error:sendNotification: curl_init() error, is php curl installed ?");

//creating the post
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); //don't print the return, output it into a string
    curl_setopt($ch, CURLOPT_POSTFIELDS, $notification_string);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, TRUE);
//posting
    $result = curl_exec($ch);
    $info = curl_getinfo($ch); //get http code
    curl_close($ch);

    if ($result == FALSE or $info['http_code'] != 200)
      return $this->printDebug("Error:postData: curl_exec() error or http error (code != 200), seems that we were not able to post to the notification page \n(" . $url . ") (" . $info['http_code'] . ")");

    return $result;
  }

  private function getAuthorityResponse($result, $authKey)
  {
    if (!preg_match('/(.+)&authority_signature:(.+)$/', $result, $matches))
      return $this->printDebug("Error:getAuthorityResponse: can't parse data ($result)\n");
//verify that the signature authority is correct
    $answer = base64_decode($matches[1]);
    $authSig = base64_decode($matches[2]);
    if (openssl_verify($answer, $authSig, $authKey) != 1)
      return $this->printDebug("Error:getAuthorityResponse: can't verify authority signature ($authKey)\n");

    if (preg_match('/serverError:(.+)/', $answer, $matches))
      return $this->printDebug("Error:getAuthorityResponse: Authority error (" . $matches[1] . ")\n");

    return $answer;
  }

  /* ====================== Queuing functions ====================== */

  /**
   * Enqueue a received notification for future authority verification
   * @param <type> $toUrl Url of the targetted post
   * @param <type> $data  Notification received
   */
  public function enqueueNotification($toUrl)
  {
    $maxQueueSize = 2;

    $line = "toUrl:" . base64_encode($toUrl) . "&";
    foreach ($this->notification as $key => $value)
      $line .= $key . ':' . base64_encode($value) . '&';
    $line = rtrim($line, '&');

    $queue = array();
    if (file_exists($this->path . "/notificationQueue"))
      $queue = file($this->path . "/notificationQueue", FILE_IGNORE_NEW_LINES);
    if (count($queue) >= $maxQueueSize)
      $this->flushNotificationQueue();

    file_put_contents($this->path . "/notificationQueue", $line . "\n", FILE_APPEND);
    return TRUE;
  }

  /**
   * Verify all queued notification with the authority
   * You can retreive the accepted notification using getAcceptedTalkbacks
   * @see getAcceptedTalkbacks()
   */
  public function flushNotificationQueue()
  {
    $lines = array();
    if (file_exists($this->path . "/notificationQueue")) {
      $lines = file($this->path . "/notificationQueue", FILE_IGNORE_NEW_LINES);
    }
    foreach ($lines as $line) {
      $this->notification = array();
      $params = explode('&', $line);
      foreach ($params as $param) {
        $val = explode(':', $param);
        if ($val[0] == 'toUrl')
          $toUrl = base64_decode($val[1]);
        else
          $this->notification[$val[0]] = base64_decode($val[1]);
      }
      if ($this->validateNotification() == FALSE)
        $this->logReceivedNotification($fromUrl, $url, "Refused (Authority refused)");
      else
        file_put_contents($this->path . "/accepted", $line . "\n", FILE_APPEND);
    }
    file_put_contents($this->path . "/notificationQueue", "");
  }

  /**
   * Retreive the accepted notifications using queuing
   * @return array indexes : toUrl = Url of the targetted post; blog_name, url, title, excerpt = data from the sender
   */
  public function getAcceptedTalkbacks()
  {
    $talkbacks = array();

    $lines = array();
    if (file_exists($this->path . "/accepted")) {
      $lines = file($this->path . "/accepted", FILE_IGNORE_NEW_LINES);
    }
    foreach ($lines as $line) {
      $data = array();
      $params = explode('&', $line[1]);
      foreach ($params as $param) {
        $val = explode(':', $param);
        if ($val[0] == 'toUrl')
          $toUrl = base64_decode($val[1]);
        $data[$val[0]] = base64_decode($val[1]);
      }
      if (base64_decode($data['version']) == "plain") {
        $accepted = array(
            'toUrl' => $toUrl,
            'blog_name' => base64_decode($data['blog_name']),
            'url' => base64_decode($data['url']),
            'title' => base64_decode($data['title']),
            'excerpt' => base64_decode($data['excerpt']));
        array_push($talkbacks, $accepted);
      } else {
        $accepted = array(
            'blog_name' => $data['blog_name'],
            'url' => $data['url'],
            'title' => $data['title'],
            'excerpt' => $data['excerpt'],
            'key' => $data['key']
        );

        if ($this->decrypData($accepted)) {
          $accepted['toUrl'] = $toUrl;
          array_push($talkbacks, $accepted);
        }
      }
    }
    return $talkbacks;
  }

  /* ====================== Registering ====================== */

  public function registerBlog($blog_name, $url, $pluginUrl, $email, $pass)
  {
    global $tbConfig;

    $pass = $blog_name . $pass;
    for ($i = 0; $i < 2048; $i++)
      $pass = sha1($pass);

    $this->notification = array(
        'blog_name' => $blog_name,
        'url' => $url,
        'pluginUrl' => $pluginUrl,
        'email' => $email,
        'password' => $pass,
        'blog_key' => $this->pubKey
    );

    $toSign = "";
    foreach ($this->notification as $key => $value)
      $toSign .= $key . '=' . $value . '&';
    $toSign = rtrim($toSign, '&');

    if (!openssl_sign($toSign, $signature, $this->privKey))
      return $this->printDebug("Error:validateNotification: can't sign the data, did you load the private key ?\n");
    $this->notification ["sender_signature"] = $signature;
    $this->notification ["action"] = "register";

    $result = $this->postData($this->authorityRequestUrl);

    $result = $this->getAuthorityResponse($result, $this->authPubKey, $msg);
    if (!$result)
      return FALSE;

    if (preg_match('/Error:(.+)/', $result, $matches)) {
      $this->lastError = "registration error (" . $matches[0] . ")";
      return FALSE;
    }

//verify that it was our signature
    if (preg_match('/sender_signature:([^&]+)/', $result, $matches)) {
      if ($matches[1] != base64_encode($signature))
        return $this->printDebug("Error:registerBlog: can't verify that it is our own signature\n");

//finishing by reading the answer
      if (preg_match('/decision:accepted/', $result)) {
        $tbConfig["blogName"] = $blog_name;
        $tbConfig["blogUrl"] = $url;
        $tbConfig["pluginUrl"] = $pluginUrl;
        $tbConfig["email"] = $email;
        saveConfig();
        return TRUE;
      }
    }
    else
      return $this->printDebug("Error:registerBlog: Bad formated answer from authority ($result)\n");
  }

  /**
   * Store the random string used by the authority to verify the blog ownership.
   * @return TRUE if everything is okay FALSE otherwise
   * @author Elie Bursztein
   * @version 1.0
   */
  public function saveRandString($token)
  {

    if (file_exists($this->path . "/randomString.txt"))
      return $this->printDebug("Error: RandomString already registered.");

    $this->notification = array();
    $this->notification['token'] = base64_encode($token);
    $this->notification['action'] = "getRandomString";

    $result = $this->postData($this->authorityRequestUrl);

    if (preg_match("/([0-9]+) (.+)/", $result, $matches)) {
      switch ($matches[1]) {
        case "403" :
          echo "Token error, please ask for a new confirmation email" . $matches[2];
          return FALSE;
        case "503" :
          return ($this->printDebug("Authority error, please try later." . $result));
        case "200" :
          if (file_put_contents($this->path . "/randomString.txt", $matches[2]) == FALSE) {
            return $this->printDebug("Error: Can't store the random string key (" . $this->path . "/randomString.txt)\n");
          }
          break;
        default:
          return $this->printDebug("Authority error, please try later. (Unknown authority code)");
      }
    } else {
	return $this->printDebug("Authority error, please try later. (Bad response format)");
    }

    $this->notification = array();
    $this->notification['action'] = "fetchMe";
    $this->notification['sender_key'] = base64_encode($this->pubKey);
    $toSign = "";
    foreach ($this->notification as $key => $value)
      $toSign .= $key . '=' . $value . '&';
    $toSign = rtrim($toSign, '&');

    if (!openssl_sign($toSign, $signature, $this->privKey))
      return $this->printDebug("Error:saveRandString: can't sign the data, did you load the private key ?\n");
    $this->notification ["sender_signature"] = $signature;
    $result = $this->postData($this->authorityRequestUrl);

    if (preg_match("/([0-9]+)(.+)/", $result, $matches)) {
      switch ($matches[1]) {
        case "403":
	  return ($this->printDebug($matches[2]));
        case "503" :
	  return ($this->printDebug("Authority error, please try later. (" . $matches[2] . ")" ));
        case "200" :
          return TRUE;
        default:
          return $this->printDebug("Authority error, please try later. (Unknown authority code)");
      }
    }
   else 
  return $this->printDebug("Authority error, please try later. (Bad response format)");
  }

  /**
   * Get the random string need by the authority to verify the blog ownership.
   * @return the randomString if everything is okay NULL otherwise
   * @author Elie Bursztein
   * @version 1.0
   */
  public function getRandString()
  {
    if (!$this->randString) {
      if ($this->debug)
        print "Error:getRandString(): Can't read the variable randString\n ";
      return NULL;
    }
    return $this->randString;
  }

  /* ====================== Discovery Functions ====================== */

  /**
   * Retreive the talkback links in the content string and send notifications
   * @param $content Content of the post
   * @param $postUrl     Url of the sender's post
   * @param $postTitle   Title of the sender's post
   * @param $postExcerpt Excerpt of the sender's post
   * @author Baptiste Gourdin
   * @version 1.0
   * @date oct 2010
   */
  public function autoDiscovery($content, $postUrl, $postTitle, $postExcerpt, &$msg)
  {
    if (preg_match_all('/<a[^>]*href="([^"]+)"[^>]*>/', $content, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $match)
        $this->sendTalkback($match[1], $postUrl, $postTitle, $postExcerpt, $msg);
    }
  }

  /**
   * Fetch the notification page and talkback version supported for the given url.
   * @param $url the url of the blog
   * @return TRUE if the blog support TalkBack FALSE otherwise
   * @author Elie Bursztein
   * @version 1.0
   * @date oct 2009
   */
  public function fetchBlogInfo($url)
  {

    $lines = file($url);
    $notificationPattern = '/<link rel="alternate" type="talkback-notification\/([^"]+)" href="([^"]+)" \/>/';
    foreach ($lines as $line) {
      if (preg_match($notificationPattern, $line, $matches)) {
        $this->rcvVer = $matches[1]; //which talkback version the receiver accept.


        if (preg_match('/^http/', $matches[2])) {
          $this->rcvUrl = $matches[2]; //talkback url notification page
        } else {
//get blog base url
          preg_match('/(.+)\/[^\/]+$/', $url, $base);
//get the public key
          $this->rcvUrl = $base[1] . "/" . $matches[2];
        }
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Fetch the crypto suite and public key used by a given blog.
   * @param $url the url of the blog
   * @return TRUE if the public key and crypto-suite were fetch FALSE otherwise
   * @author Elie Bursztein
   * @version 1.0
   * @date oct 2009
   */
  public function fetchBlogCrypto($url)
  {

    $lines = file($url);
    $publicKeyPattern = '<link rel="alternate" type="talkback-crypto\/([^"]+)" href="([^"]+)">';
    foreach ($lines as $line) {
      if (preg_match($publicKeyPattern, $line, $matches)) {
        $this->rcvCryptoSuite = $matches[1]; //which cipher suite the receiver accept.
        if (preg_match('/^http/', $matches[2])) {
          $this->rcvPubKey = implode('', file($matches[2])); //getting the public key
        } else {
//get blog base url
          preg_match('/(.+)\/[^\/]+$/', $url, $base);
//get the public key
          $this->rcvPubKey = implode('', file($base[1] . '/' . $matches[2]));
        }

//testing if the file looklike a key
        if (preg_match('/BEGIN PUBLIC KEY/', $this->rcvPubKey)) {
          return TRUE;
        } else {
          if ($this->debug)
            print "Error:fetchBlogCrypto(): the public key feched from " . $matches[2] . " seems invalid\n ";
          return FALSE;
        }
      }
    }
    return FALSE;
  }

  /* ====================== Whitelists Functions ====================== */

  /**
   * Fetch the public key from the url and save it
   * @param $url blog url to whitelist
   * @author Baptiste Gourdin
   * @version 1.0
   * @date apr 2010
   */
  public function whitelistUrl($url)
  {
    if (!$this->fetchBlogCrypto($url)) {
      $this->lastError = "Can't fetch talkback data at this urls";
      return FALSE;
    }
    $key = base64_encode($this->rcvPubKey);
    if ($this->isWhitelisted($key)) {
      $this->lastError = "Blog already in the whitelist";
      return FALSE;
    }
    if (!file_put_contents($this->path . "/whitelist",
                    $url . "\n" . $key . "\n", FILE_APPEND)) {
      $this->lastError = "Cannot write to " . $this->path . "/whitelist";
      return FALSE;
    }
    else
      return TRUE;
  }

  /**
   * Remove from whitelist
   * @param $url blog url to whitelist
   * @author Baptiste Gourdin
   * @version 1.0
   * @date apr 2010
   */
  public function removeWhitelistUrl($url)
  {
    $res = "";
    if (!file_exists($this->path . "/whitelist")) {
      $this->lastError = "This blog is not in the whitelist";
      return FALSE;
    }

    $found = false;
    $lines = file($this->path . "/whitelist", FILE_IGNORE_NEW_LINES);
    for ($i = 0; $i < count($lines); $i+=2) {
      if ($lines[$i] != $url)
        $res .= $lines[$i] . "\n" . $lines[$i + 1] . "\n";
      else
        $found = true;
    }

    if (!$found) {
      $this->lastError = "This blog is not in the whitelist";
      return FALSE;
    }
    if (!file_put_contents($this->path . "/whitelist", $res))
      $this->lastError = "Cannot write to " . $this->path . "/whitelist";

    return TRUE;
  }

  /**
   * return True if the public key is whitelisted
   * @param $pubKey public key encoded in base64
   * @author Baptiste Gourdin
   * @version 1.0
   * @date apr 2010
   */
  public function isWhitelisted($pubKey)
  {
    if (!file_exists($this->path . "/whitelist"))
      return FALSE;

    $keys = file($this->path . "/whitelist", FILE_IGNORE_NEW_LINES);
    foreach ($keys as $key) {
      if ($key == $pubKey)
        return TRUE;
    }
    return FALSE;
  }

  /**
   * Get the list of whitelisted urls
   * @return Return an array url => public key
   */
  public function getWhiteList()
  {
    $res = array();
    if (!file_exists($this->path . "/whitelist"))
      return $res;

    $lines = file($this->path . "/whitelist", FILE_IGNORE_NEW_LINES);
    for ($i = 0; $i < count($lines); $i+=2) {
      $res[$lines[$i]] = $lines[$i + 1];
    }
    return $res;
  }

  /* ====================== Authority management functions ====================== */

  public function getAuthorityUrlFromPubKey($authPubKey)
  {
    if (!file_exists($this->path . "/authorities"))
      return $this->printDebug("Error:getAuthorityUrlFromPubKey authorities file does not exist.");
    $lines = file($this->path . "/authorities", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
      $auth = explode(";", $line);
      $url = base64_decode($auth[0]);
      $key = $auth[1];
      if ($key == $authPubKey)
        return $url;
    }
    return $this->printDebug("Error:getAuthorityUrlFromPubKey authority unknown. ($authPubKey)");
  }

  /* ====================== Spam reporting Functions ====================== */

  /**
   * Report a spam to the authority
   * @param $senderPubKey Public key of the spam sender
   * @param $senderSig    Signature used to identify the spam
   * @author Baptiste Gourdin
   * @version 1.0
   * @date apr 2010
   */
  public function reportSpam($hash, $authKey)
  {
    $ts = time();

//constructing the signature
    $this->notification = array(
        'action' => "spam",
        'timestamp' => base64_encode($ts),
        'hash' => base64_encode($hash),
        'receiver_key' => base64_encode($this->pubKey),
    );

    $toSign = "";
    foreach ($this->notification as $key => $value)
      $toSign .= $key . '=' . $value . '&';
    $toSign = rtrim($toSign, '&');

    if (!openssl_sign($toSign, $signature, $this->privKey))
      return $this->printDebug("Error:reportSpam: can't sign the data, did you load the private key ?\n");

    $this->notification['receiver_signature'] = base64_encode($signature);

    if (!($authUrl = $this->getAuthorityUrlFromPubKey($authKey)))
      return $this->printDebug("Error:reportSpam unknown authority");

//send the notification to the authority to validate it.
    if (($result = $this->postData($authUrl)) == FALSE)
      return $this->printDebug("Error:reportSpam: can't send notification to the authority. Is there any firewall issue ?\n");

    if (preg_match('/503.*/', $result))
      return $this->printDebug("Error:reportSpam: Authority server error\n");

    if (preg_match('/(.+)&authority_signature=(.+)/', $result, $matches)) {
//verify that the signature authority is correct
      $answer = $matches[1];
      $authSig = $matches[2];
      if (openssl_verify($answer, base64_decode($authSig), base64_decode($authKey)) != 1)
        return $this->printDebug("Error:reportSpam: can't verify authority signature\n");

//verify that it was our signature
      if (preg_match('/receiver_signature=([^&]+)/', $answer, $matches2)) {
        if ($matches2[1] != base64_encode($signature))
          return $this->printDebug("Error:reportSpam: can't verify that it is our own signature\n");

//print "<br>passed own signature verification<br>\n";
//finishing by reading the answer
        if (preg_match('/decision=accepted/', $answer)) {
          $this->log("Spam reported");
          return TRUE;
        }
        return $this->printDebug("Error:reportSpam: the authority rejected the notification\n");
      }
      return $this->printDebug("Error:reportSpam: can't verify that it is our own signature\n");
    }
    else
      echo "Bad answer ($result)";
  }

  /**
   * Cancel a spam to the authority
   * @param $senderPubKey Public key of the spam sender
   * @param $senderSig    Signature used to identify the spam
   * @author Baptiste Gourdin
   * @version 1.0
   * @date apr 2010
   */
  public function cancelSpam($hash, $authKey)
  {
//constructing the signature
    $this->notification = array(
        'action' => "nospam",
        'timestamp' => base64_encode($ts),
        'hash' => base64_encode($hash),
        'receiver_key' => base64_encode($this->pubKey),
    );
    $toSign = "";
    foreach ($this->notification as $key => $value)
      $toSign .= $key . '=' . $value . '&';
    $toSign = rtrim($toSign, '&');

    if (!openssl_sign($toSign, $signature, $this->privKey))
      return $this->printDebug("Error:cancelSpam: can't sign the data, did you load the private key ?\n");

    $this->notification['receiver_signature'] = base64_encode($signature);

    if (!($authUrl = $this->getAuthorityUrlFromPubKey($authKey)))
      return $this->printDebug("Error:reportSpam unknown authority");

//send the notification to the authority to validate it.
    if (($result = $this->postData($authUrl)) == FALSE)
      return $this->printDebug("Error:cancelSpam: can't send notification to the authority. Is there any firewall issue ?\n");

    if (preg_match('/503.*/', $result))
      return $this->printDebug("Error:cancelSpam: Authority server error\n");

    if (preg_match('/(.+)&authority_signature=(.+)/', $result, $matches)) {

//verify that the signature authority is correct
      $answer = $matches[1];
      $authSig = $matches[2];
      if (openssl_verify($answer, base64_decode($authSig), base64_decode($authKey)) != 1)
        return $this->printDebug("Error:cancelSpam: can't verify authority signature\n");

//verify that it was our signature
      if (preg_match('/receiver_signature=([^&]+)/', $answer, $matches2)) {
        if ($matches2[1] != base64_encode($signature))
          return $this->printDebug("Error:cancelSpam: can't verify that it is our own signature\n");

//print "<br>passed own signature verification<br>\n";
//finishing by reading the answer
        if (preg_match('/decision=accepted/', $answer)) {

          $this->log("Spam canceled");
          return TRUE;
        }
        return $this->printDebug("Error:cancelSpam: the authority rejected the notification\n");
      }
      return $this->printDebug("Error:cancelSpam: can't verify that it is our own signature\n");
    }
  }

  /* ====================== log Functions ====================== */

  public function log($str)
  {
    file_put_contents($this->logPath . "/all.log", date("d/m/y : H:i:s", time()) . " : " . $str . "\n", FILE_APPEND);
  }

  public function getLogs()
  {
    $lines = file($this->logPath . "/all.log", FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);
    foreach ($lines as $key => $value)
      $lines[$key] = htmlspecialchars($lines[$key]);
    return $lines;
  }

  /**
   * Record a sent notification in the log/sent_notifications.log file
   * @param $fromUrl  url of the sender post
   * @param $toUrl  url of the receiver post
   * @author Baptiste Gourdin
   * @version 1.0
   * @date apr 2010
   */
  public function logSentNotification($fromUrl, $toUrl)
  {
    $fromUrl = base64_encode($fromUrl);
    $toUrl = base64_encode($toUrl);
    file_put_contents($this->logPath . "/sent_notifications.log", time() . ";$fromUrl;$toUrl\n", FILE_APPEND);
  }

  /**
   * Return an array with all sent notifications
   * @return  Array of [date, fromUrl, toUrl]
   * @author Baptiste Gourdin
   * @version 1.0
   * @date apr 2010
   */
  public function getSentNotifications()
  {
    $result = array();
    if (!file_exists($this->logPath . "/sent_notifications.log"))
      return;
    $lines = file($this->logPath . "/sent_notifications.log");
    foreach ($lines as $line) {
      $line = explode(";", $line);
      array_push($result, array("time" => date("d/m/y : H:i:s", $line[0]),
          "fromUrl" => htmlspecialchars(base64_decode($line[1])),
          "toUrl" => htmlspecialchars(base64_decode($line[2]))));
    }
    return $result;
  }

  /**
   * Record a received notification in the log/received_notifications.log file
   * @param $fromUrl  url of the sender post
   * @param $toUrl  url of the receiver post
   * @author Baptiste Gourdin
   * @version 1.0
   * @date apr 2010
   */
  public function logReceivedNotification($fromUrl, $toUrl, $comment)
  {
    $fromUrl = base64_encode($fromUrl);
    $toUrl = base64_encode($toUrl);
    $comment = base64_encode($comment);
    file_put_contents($this->logPath . "/received_notifications.log", time() . ";$fromUrl;$toUrl:$comment\n", FILE_APPEND);
  }

  /**
   * Return an array with all received notifications
   * @return  Array of [date, fromUrl, toUrl]
   * @author Baptiste Gourdin
   * @version 1.0
   * @date apr 2010
   */
  public function getReceivedNotifications()
  {
    $result = array();
    if (!file_exists($this->logPath . "/received_notifications.log"))
      return;
    $lines = file($this->logPath . "/received_notifications.log");
    foreach ($lines as $line) {
      $line = explode(";", $line);
      array_push($result, array("time" => date("d/m/y : H:i:s", $line[0]),
          "fromUrl" => htmlspecialchars(base64_decode($line[1])),
          "toUrl" => htmlspecialchars(base64_decode($line[2])),
          "comment" => htmlspecialchars(base64_decode($line[3]))));
    }
    return $result;
  }

  /**
   * Return an array with all sent/received notifications
   * @return  Array of [date, fromUrl, toUrl, type]
   * @author Baptiste Gourdin
   * @version 1.0
   * @date apr 2010
   */
  public function getNotifications()
  {
    $result = array();
    if (file_exists($this->logPath . "/received_notifications.log")) {
      foreach (file($this->logPath . "/received_notifications.log") as $line) {
        $line = explode(";", $line);
        $result[$line[0]] = array("time" => date("d/m/y : H:i:s", $line[0]),
            "fromUrl" => base64_decode($line[1]),
            "toUrl" => base64_decode($line[2]),
            "type" => "received");
      }
    }
    if (file_exists($this->logPath . "/sent_notifications.log")) {
      foreach (file($this->logPath . "/sent_notifications.log") as $line) {
        $line = explode(";", $line);
        $result[$line[0]] = array("time" => date("d/m/y : H:i:s", $line[0]),
            "fromUrl" => base64_decode($line[1]),
            "toUrl" => base64_decode($line[2]),
            "type" => "sent");
      }
    }

    sort($result);

    return $result;
  }

  /**
   * Return TRUE if the notification has already been send
   * @param $fromUrl  url of the sender post
   * @param $toUrl    url of the receiver post
   * @author Baptiste Gourdin
   * @version 1.0
   * @date apr 2010
   */
  public function isNotificationSent($fromUrl, $toUrl)
  {
    if (!file_exists($this->logPath . "/sent_notifications.log"))
      return FALSE;
    $lines = file($this->logPath . "/sent_notifications.log");
    foreach ($lines as $line) {
      $line = explode(";", $line);
      if (($fromUrl == base64_decode($line[1])) && ($toUrl == base64_decode($line[2])))
        return TRUE;
    }
    return FALSE;
  }

  function encryptData(&$data, $pubKey)
  {
    $key = sha1(mt_rand() . mt_rand());
    $cypher = MCRYPT_RIJNDAEL_256;

    $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);
    $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);

    foreach ($data as $k => $value) {
      $data[$k] = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, base64_encode($value), MCRYPT_MODE_CBC, $iv);
    }

    $key = base64_encode($key) . ":" . base64_encode($iv);

    if (!openssl_public_encrypt($key, $key, $pubKey))
      return $this->printDebug("Error encryptData : openssl_public_encrypt returned false");
    $data["key"] = $key;

    return TRUE;
  }

  function decrypData(&$data)
  {
    $key = base64_decode($data["key"]);
    $cypher = MCRYPT_RIJNDAEL_256;

    if (!openssl_private_decrypt($key, $key, $this->privKey))
      return $this->printDebug("Error decryptData : openssl_private_decrypt returned false");

    unset($data["key"]);

    $fields = explode(':', $key);

    $key = base64_decode($fields[0]);
    $iv = base64_decode($fields[1]);

    foreach ($data as $k => $value) {
      $data[$k] = mcrypt_decrypt($cypher, $key, base64_decode($value), MCRYPT_MODE_CBC, $iv);
    }
    return TRUE;
  }

}
?>
