<?php

require_once "talkbackClass.php";
require_once "config.php";

if (!isset($_POST['action']))
  die("No action to perform.");


switch ($_POST['action']) {
  case 'register':
    $blogName = $_POST['blogName'];
    $blogUrl = $_POST['blogUrl'];
    $pluginUrl = $_POST['pluginUrl'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $tb = new TalkBack(TRUE);
    if (!$tb->load(TRUE, FALSE))
      die("Error loading Talkback, please verify the settings.");
    if (!$tb->registerBlog($blogName, $blogUrl, $pluginUrl, $email, $password))
    {
      if ($tb->lastError != "")
        echo $tb->lastError;
      else
        echo "An error occured, for more information see the errors log file";
    }
    break;
  case 'keygen':
    $tb = new TalkBack();
    if ($tb->keyGen())
      echo "Ok";
    else
      echo "Ko";
    break;

  case 'sendTalkback':
    if (!isset($_POST['url']) || !isset($_POST['postInfoStr']))
      die("Error in parameters");
    $postInfo = unserialize(base64_decode($_POST['postInfoStr']));
    if ($postInfo == FALSE)
      die("Can't unserialize ");
    $url = $_POST['url'];
    $postTitle = $postInfo['title'];
    $postExcerpt = $postInfo['excerpt'];
    $postUrl = $postInfo['url'];
    $tb = new TalkBack();
    if (!$tb->load(TRUE))
      die("Error loading Talkback, please verify the settings.");
    if (!$tb->sendTalkback($url, $postUrl, $postTitle, $postExcerpt, $msg))
      die($msg);
    echo "<b>The talkback has been sent.<b/>";
    break;

  case 'whitelistUrl':
    $tb = new TalkBack($tbConfig['dataDir'], FALSE);
    if (!$tb->load(TRUE))
      die("Error loading Talkback, please verify the settings.");
    if (!$tb->whitelistUrl($_POST['url']))
      die($tb->lastError);
    break;
  case 'removeWhitelistUrl':
    $tb = new TalkBack($tbConfig['dataDir'], FALSE);
    if (!$tb->load(TRUE))
      die("Error loading Talkback, please verify the settings.");
    if (!$tb->removeWhitelistUrl($_POST['url']))
      die("Ko");
    break;

  case 'updateConf' :
    $tbConfig['WLonly'] = ($_POST['WL'] == "true") ? 1 : 0;
    $tbConfig['queuing'] = ($_POST['QU'] == "true") ? 1 : 0;
    $tbConfig['autodiscovery'] = ($_POST['AU'] == "true") ? 1 : 0;
    $tbConfig['encryption'] = ($_POST['EN'] == "true") ? 1 : 0;
    $tbConfig['receptionPolicy'] = $_POST['RP'];
    saveConfig();
    break;

  default:
    die("Unknown action.");
}
?>