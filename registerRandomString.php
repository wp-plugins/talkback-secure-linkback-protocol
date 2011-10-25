<?php 
require_once "talkbackClass.php";
  require_once "config.php";
?>

<script type="text/javascript">
function success(c)
{
  if (c==0)
    top.location = "<?=$tbConfig['blogUrl']?>";
  document.getElementById("Message").innerHTML = "Your talkback registration is complete, you will be redirected to your blog in "+c;
  setTimeout("success ("+(c-1)+")", 1000);
}

</script>

<b>Talkback :</b><b id="Message"> </b> <b id="counter"></b>


<?php
if (isset($_GET['token']))
{
  require_once "talkbackClass.php";
  require_once "config.php";

  $tb = new TalkBack();
  if (!$tb->load(TRUE, FALSE))
    die ("Error : Cannot load talkback plugin (".htmlentities($tb->lastError).")");
  if ($tb->saveRandString($_GET['token']))
     echo '<script type="text/javascript">success(5);</script>';
  else
    die (htmlentities($tb->lastError));
}
?>
