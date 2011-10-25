<?php
/*
  Plugin Name: Talkback
  Plugin URI : http://talkback.stanford.edu
  Description : Talkback is a secure linkback protocol, It will prevent your blog from linkback spam.
  Version: 1.0
  Author: Elie Burstein, Baptiste Gourdin
  License : LGPL
 */

require_once "talkbackClass.php";
require_once "config.php";

/* ====================== Display Part ====================== */

/**
 * Add the javascript initialisation in the admin page header.
 */
function tb_admin_head()
{
  global $tbConfig;
  $tbConfig['pluginUrl'] = get_bloginfo('url') . "/wp-content/plugins/talkback-secure-linkback-protocol";
  $tbPluginUrl = $tbConfig['pluginUrl'];
?>
  <script type="text/javascript">var tbPluginUrl = '<?=$tbPluginUrl
?>';</script>
<script type="text/javascript" src="<?=$tbPluginUrl
?>/talkback.js"></script>
  <style type="text/css">

    .tbSettingsTable{
      width: 400px;
    }

    .tbSettingsTitle{
      font-weight: bold;
      background-color: #263F67;
      color : white;
    }

    .tbSettingsLog{
      font-size: xx-small;
    }
    /*  .WLdelete{
        color : #BC0B0B;
      }
      .WLdelete :hover{
        color: red;
      }*/
  </style>
<?php
  $tb = new TalkBack();
  if (!$tb->load()) {

  /*  if ($tbConfig['blogName'] != "") {

      function talkback_warning()
      {
        echo "<div id='talkback-warning' class='updated fade'><p><strong>" .
        'Talkback is almost ready</strong> You must receive a confirmation email.</p></div>.';
      }

      add_action('admin_notices', 'talkback_warning');
    } else {
*/
      function talkback_warning()
      {
        echo "<div id='talkback-warning' class='updated fade'><p><strong>" .
        __('Talkback is almost ready') . "</strong> " .
        sprintf(__('You must <a href="%1$s">register your blog</a>.'),
                "plugins.php?page=talback-configuration") . "</p></div>";
      }

      add_action('admin_notices', 'talkback_warning');
    }
  //}
}

add_action('admin_head', 'tb_admin_head');

/**
 * Add the talback header in the single post pages
 */
function tb_wp_head()
{
  global $wp_query, $tbConfig;

  if (!is_single())
    $postID = "";
  else
    $postID = $wp_query->post->ID;

  $notify = "both";

  $tb = new TalkBack();
  if ($tb->load(FALSE)) {
    $tbPluginUrl = $tbConfig['pluginUrl'];
?>
    <meta http-equiv="TalkBack-Id" content="<?=$tb->getRandString()
?>" />
<link rel="alternate" type="talkback-notification/<?=$tbConfig['receptionPolicy']
?>" href="<?=$tbConfig['blogUrl'] . "/?p=$postID" ?>" />
<link rel="alternate" type="talkback-crypto/<?=$tb->getCryptoSuite()
?>" href="<?=$tbConfig['pluginUrl'] . '/publickey.php' ?>" />
<?php
    }
  }

  add_action('wp_head', 'tb_wp_head');

  /**
   * Add the new talkback elements in the dashboard
   */
  function tb_menu()
  {
    add_meta_box("talkback_section_id", "Send Talkbacks", "tb_edit_post", "post", "normal", "high");
    add_submenu_page('plugins.php', __('Talkback configuration'), __('Talkback configuration'), 'administrator', 'talback-configuration', 'tb_configuration');
    add_submenu_page('plugins.php', __('Talkback history'), __('Talkback history'), 'administrator', 'talback-history', 'tb_history');
    add_submenu_page('plugins.php', __('Talkback logs'), __('Talkback Logs'), 'administrator', 'talback-logs', 'tb_logs');
  }

  /**
   * Display the blog registration page
   */
  function tb_register_blog($tb, $notRegistered)
  {
    global $tbConfig;
        global $current_user;    

/*    if (!$notRegistered) {
?>
      <h3>Talkback registration</h3><b>Thanks for registering, you will receive shortly an email with the confirmation link. Please click on it to complete the registration.</b>
<?
    } else*/ {        
      $tbConfig['blogName'] = get_bloginfo('name');
      $tbConfig['blogUrl'] = get_bloginfo('url');
      $tbConfig['pluginUrl'] = get_bloginfo('url') . "/wp-content/plugins/talkback-secure-linkback-protocol";
      $tbConfig['email'] = $current_user->user_email;
      
      saveConfig();
?>
      <h2>Talkback registration</h2>
      <div id="registerDiv">
        <div>Please fill the following field to continue.<br/><br/></div>
            
        <?php 
        if ($tb->keyGen()) { 

      ?>
      <form name="registerForm" >
        <input type="hidden" name="pluginUrl" value="<?=htmlentities($tbConfig['pluginUrl'])?>"/>
        <input type="hidden" name="pubKey" value="<?=base64_encode($tb->keyGetPub()) ?>"/>
      <table class="form-table">
      <tr valign="top"><th scope="row"><label for="name">Blog&#39;s name :</label></th>
        <td><input type="text" name="blogName" value="<?=htmlentities($tbConfig['blogName'])?>"/></td></tr>
      <tr><th  scope="row">Blog&#39;s url :</th>
        <td><input type="text" name="blogUrl" value="<?=htmlentities($tbConfig['blogUrl'])?>"/></td></tr>
      <tr><th scope="row">Email : </th>
        <td><input type="text" name="email" value="<?=htmlentities($tbConfig['email'])?>"/></td></tr>
       <tr><th colspan="2"><input class="button-primary" type="button" value="Register" onclick="tbRegister('<?=$tbConfig['authorityUrl']?>')" /></th></tr>
       </table>
       </form>
                   
<?php } else { ?>
                   <br/><b>Your keys couldn&#39;t been generated.</b>
                   <input type="button" value="retry" onclick="window.location.reload();"/>
<?php } ?> 
       </div>
<?php
               }
             }

             /*
              * Display the talkback settings page
              */

             function tb_configuration()
             {
               global $tbConfig;
           

               echo '<div class="wrap">';
               $tb = new TalkBack();
               if (!$tb->load(FALSE)) {
                 if ($tbConfig['blogUrl'] == "")
                   tb_register_blog($tb, true);
                 else
                   tb_register_blog($tb, false);
               }
               else {
                 $WLcheck = ($tbConfig['WLonly']) ? "checked" : "";
                 $ADcheck = ($tbConfig['autodiscovery']) ? "checked" : "";
                 $QUcheck = ($tbConfig['queuing']) ? "checked" : "";
                 $ENcheck = ($tbConfig['encryption']) ? "checked" : "";
                 $RPselect = $tbConfig['receptionPolicy'];
?>
                 <center>
                   <h2>Talkback settings.</h2>
                   <br/>
                   <form name="talkbackConfig" action="">
                     <table class="tbSettingsTable">
                       <tr class="tbSettingsTitle"><td><b>Account</b></td></tr>
                       <tr ><td>
                           Blog's name : <?=$tbConfig['blogName']
?> <br/>
                           Blog's url  : <?=$tbConfig['blogUrl']
?> <br/>
                           Email : <?=$tbConfig['email']
?> <br/>
                         </td></tr>
                       <tr class="tbSettingsTitle"><td><b>Whitelisting</b></td></tr>
                       <tr><td>
                           <input type="checkbox" name="enableWhitelisting" <?=$WLcheck ?> onchange="tbUpdateConfig();"> Enable whitelisting.<br/><br/>

                 Trust this blog (url) : <input type="text" id="WLurl"/><input type="button" value="add" onclick="tbWhitelist(document.getElementById('WLurl').value ,true)"/>
<?php
                 $wl = $tb->getWhiteList();
                 if (count($wl) > 0) {
?>
                   <br/><br/>
                   <table id="WLTable" style="text-align:center; display: <?=$WLTableDisplay ?>; width : 100%">
                 <thead><tr><th colspan="2">Your whitelist</th></tr></thead>
                 <tbody id="whiteList">
<?php
                   foreach ($wl as $url => $key) {
?><tr><td style="text-align: left"><?=$url ?></td><td style="width:20%"><a style="cursor : pointer" onclick="tbWhitelist('<?=$url ?>',false)">remove</a></td></tr><?php;
        } ?>
            </tbody>
          </table>
<?php } ?>
        </td></tr>
      <tr class="tbSettingsTitle"><td><b>Autodiscovery <span style="color: white; font-size: small; cursor: pointer" onclick="tbShow('autodiscoHelp')">?</span></b></td></tr>
      <tr><td>
          <div id="autodiscoHelp" style="display: none; border : 1px solid black">Autodiscovery mechanism allows your blog to send notifications automatically when a blog is published/updated.</div>
          <input type="checkbox" name="enableAutodiscovery" <?=$ADcheck ?> onchange="tbUpdateConfig();"> Enable Autodiscovery.<br/><br/>
        </td></tr>

      <!--tr class="tbSettingsTitle"><td><b>Queuing</b></td></tr>
      <tr><td>
          <input type="checkbox" name="enableQueuing" <?=$QUcheck ?> onchange="tbUpdateConfig();"> Enable Queuing.<br/><br/>
        </td></tr-->

      <tr class="tbSettingsTitle"><td ><b>Encryption</b></td></tr>
      <tr><td>
          <input type="checkbox" name="enableEncryption" <?=$ENcheck ?> onchange="tbUpdateConfig();"> Enable Encryption.<br/><br/>
        </td></tr>

      <tr class="tbSettingsTitle"><td ><b>Reception policy</b></td></tr>
      <tr><td>
          Accept <select name="acceptSelect" onchange="tbUpdateConfig();">
            <option <?=($RPselect == "plain") ? "selected" : "" ?> value="plain">plain</option>
            <option <?=($RPselect == "encrypted") ? "selected" : "" ?> value="encrypted">encrypted</option>
            <option <?=($RPselect == "both") ? "selected" : "" ?> value="both">both</option>
          </select><br/><br/>
        </td></tr>
    </table>
  </form>
</center>
<?php
    }
  }

  function tb_history()
  {
    $tb = new TalkBack();

    if (!$tb->load(FALSE)) {
      echo "<b>Error loading talkback.</b>";
      return;
    }
?>
    <div class="wrap">
      <h2>Talkback history</h2>
      <table class="widefat fixed" cellspacing="0">
        <thead>
          <tr><th>Date</th><th>From</th><th>To</th><th></th></tr>
        </thead>
        <tbody>
      <?php
      $c = 0;
      foreach ($tb->getNotifications() as $n) {
        $class = ($c % 2 == 0) ? "" : 'class="alternate"';
        echo "<tr $class><td>" . $n['time'] . "</td><td >" . htmlentities($n['fromUrl']) . "</td><td >" . htmlentities($n['toUrl']);
        if ($n['type'] == "sent")
          echo '<td class="tbSettingsLog"><span style="color:green">sent</span></td>';
        else
          echo '<td class="tbSettingsLog"><span style="color:blue">received</td>';
        echo "</td></tr>";
        $c++;
      }
      ?>
          </tbody>
        </table>
      </div>
<?php
    }

    function tb_logs()
    {
      $tb = new TalkBack();

      if (!$tb->load(FALSE)) {
        echo "<b>Error loading talkback.</b>";
        return;
      }
?>
      <div class="wrap">
        <h2>Talkback logs</h2>
        <table class="widefat fixed" cellspacing="0">
          <tbody>
    <?php
      $c = 0;
      foreach ($tb->getLogs() as $line) {
        $class = ($c++ % 2 == 0) ? "" : 'class="alternate"';
        echo "<tr $class><td>" . htmlentities($line) . "</td></tr>";
      }
    ?>
            </tbody>
        </table>
      </div>
<?php
    }

    add_action('admin_menu', 'tb_menu');


    /*
     * Content of the "send talkback" box in the edit post page.
     */

    function tb_edit_post()
    {
      global $tbConfig, $post;
      $post_id = $post->ID;

      $postInfo = array(
          "id" => $post->ID,
          "title" => $post->post_title,
          "excerpt" => $post->post_excerpt,
          "url" => $tbConfig['blogUrl'] . "?p=" . $post->ID,
      );

      $postInfoStr = urlencode(base64_encode(serialize($postInfo)));

      $tb = new TalkBack();
      if (!$tb->load(FALSE)) {
        echo "<b>Error loading Talkback, please verify the settings.</b>";
        return;
      }
?>
      Send Talkback to :<br/>
      <input type="text" style="width : 99%" id="talkback_url" /><br/>
      (Separate multiple URLs with spaces)<br/>
      <input type="button" style="float : right" value="Send" onclick="tbSendTalkback('<?=$postInfoStr ?>')" /><br/>
      <div id="tbDebug"> </div><br/>

<?php
      if ($tbConfig['autodiscovery'])
        echo "<b>Autodiscovery activated</b> : If you link other talkback available blogs, they’ll be notified automatically.<br/>";
      else
        echo "Using Autodiscovery mechanism, all linked talkback available blogs will be notified automatically. You can activate it in the talkback <a href=\"plugins.php?page=talback-configuration\">settings page</a>.<br/>";
      echo 'You can see all sent talkback in the <a href="plugins.php?page=talback-history">history page</a>.';
?>
<?php
    }

    /**
     * Content of Dashboard-Widget
     */
    function tb_wp_dashboard_widget()
    {
?>
  <table style="width:99%; text-align : center">
    <tbody>
      <tr><th>Post title</th><th>Talkbacks</th><th style="color : orange">Pending</th><th style="color : green">Approved</th><th style="color : red">Spam</th></tr>
    <?php
      $myposts = get_posts('');
      foreach ($myposts as $post) {
        $tbs = get_talkback_meta($post->ID);
        if ($tbs) {
    ?>
      <tr>
        <td><?=$post->post_title
    ?></td>
        <td><?=(count($tbs))
    ?></td>
        <td><div style="color : orange">0</div></td>
        <td><div style="color : green">0</div></td>
        <td><div style="color : red">0</div></td>
      </tr>
      <tr><td colspan="5">
          <table>
<?php foreach ($tbs as $tb) {
?>
            <tr><td>
                <a href="<?=base64_decode($tb['url']) ?>"><?=base64_decode($tb['title']) ?></a></td>
            <td><span style="color : green; cursor : pointer" onclick="tbWhitelist('<?=$tb['key'] ?>', 'tbDebug')" >whitelist</span></td><td>spam</td></tr>
    <?php } ?>
            </table>
          </td></tr>
<?php
      }
    }
?>
      </tbody>
    </table>
    <div id="tbDebug"> </div>
<?php
  }

  /**
   * add Dashboard Widget via function wp_add_dashboard_widget()
   */
  function tb_wp_dashboard_setup()
  {
    wp_add_dashboard_widget('tb_wp_dashboard_widget', __('Talkbacks widget'), 'tb_wp_dashboard_widget');
  }

  /**
   * use hook, to integrate new widget
   */
//add_action('wp_dashboard_setup', 'tb_wp_dashboard_setup');

  /* ====================== Processing Part ====================== */

  /**
   * Save the talback : insert a comment and save the pubkey in meta data
   * @param $post_id
   * @param $data
   */
  function save_talkback($post_id, $blog_name, $url, $title, $excerpt, $authUrl)
  {
    $comment = array(
        'comment_post_ID' => $post_id,
        'comment_author' => $title,
        'comment_author_email' => '',
        'comment_author_url' => $url,
        'comment_content' => $excerpt,
        'comment_type' => '', // XXX Need to allow a new type for trackbacks
        'comment_parent' => 0,
        'user_id' => '',
        'comment_author_IP' => '',
        'comment_agent' => '',
        'comment_date' => current_time('mysql'),
        'comment_approved' => 0,
    );
    $comment_id = wp_insert_comment($comment);
    delete_comment_meta($comment_id, 'talkback');
    $meta = $_POST['hash'] . ":" . $_POST['authority_key'];
    add_comment_meta($comment_id, 'talkback', $meta, true);
    return TRUE;
  }

  /**
   * Function called to perform a notification on a single post page
   */
  function tb_wp()
  {
    if (!is_single())
      return;

    global $tbConfig, $wp_query;
    $post_id = $wp_query->post->ID;

    if (isset($_POST['action'])
            and ($_POST['action'] == "notification")) {
      $tb = new TalkBack();
      if (!$tb->load(TRUE))
        die("503 Talkback can't be loaded.");

      if ($tb->notifyMe($_POST, $tbConfig['blogUrl'] . "?p=$post_id", $msg, $blog_name, $url, $title, $excerpt))
        save_talkback($post_id, $blog_name, $url, $title, $excerpt);
      die($msg);
    }
  }

  add_action('wp', 'tb_wp');

  /**
   * Runs when the status of a comment changes.
   * Used to report spam to the authority.
   *
   * @param $comment_id  modified comment id
   * @param $status "spam", or 0/1 for disapproved/approved.
   */
  function tb_wp_set_comment_status($comment_id, $status)
  {
    global $tbConfig;

    $meta = get_comment_meta($comment_id, 'talkback');
    $comment = get_comment($comment_id);
    $infos = explode(":", $meta[0]);
    $hash = base64_decode($infos[0]);
    $authKey = $infos[1];


    if ($status == "spam") {
      $tb = new TalkBack(TRUE);
      if ($tb->load(TRUE))
        $tb->reportSpam($hash, $authKey);
    }
    else if ($comment->comment_approved == "spam") {
      $tb = new TalkBack();
      if ($tb->load(TRUE))
        $tb->cancelSpam($hash, $authKey);
    }
  }

  add_action('wp_set_comment_status', 'tb_wp_set_comment_status', 10, 2);

  function tb_save_post($post_id)
  {
    global $tbConfig;
    static $firstSavePostCall = 0;

    if ($firstSavePostCall == 0)
      $firstSavePostCall = 1;
    else
      return;

    $post = get_post($post_id);

    $content = $post->post_content;

    $tb = new TalkBack();
    if ($tb->load(TRUE))
      $tb->autoDiscovery($content, $tbConfig['blogUrl'] . "/?p=" . $post_id, $post->post_title, $post->post_excerpt);
  }

  add_action('save_post', 'tb_save_post', 10, 2);
?>
