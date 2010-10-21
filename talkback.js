function tbRegister()
{
  var request = new XMLHttpRequest();
  var params = "action=register";
  var form = document.forms['registerForm'];
  if (form.password.value != form.passwordVerif.value)
  {
    alert("Your passwords are not the sames.");
    return;
  }
  params += "&blogName="+form.name.value;
  params += "&blogUrl="+form.url.value;
  params += "&pluginUrl="+form.pluginUrl.value;
  params += "&email="+form.email.value;
  params += "&password="+form.password.value;
  request.open("POST", tbPluginUrl+"/talkbackRequest.php", false);
  request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
  request.setRequestHeader("Content-length", params.length);
  request.setRequestHeader("Connection", "close");
  request.send(params);
  if (request.responseText == "")
    window.location.reload();
  else
    alert(request.responseText); //FIXME : print error message
}

function tbSendTalkback (postInfoStr)
{
  var request = new XMLHttpRequest();
  var url = document.getElementById('talkback_url').value;
  var params = "action=sendTalkback&url="+url+"&postInfoStr="+postInfoStr;
  request.open("POST", tbPluginUrl+"/talkbackRequest.php", false);
  request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
  request.setRequestHeader("Content-length", params.length);
  request.setRequestHeader("Connection", "close");
  request.send(params);
  document.getElementById("tbDebug").innerHTML = request.responseText;
}

function tbWhitelist (url, add)
{
  var request = new XMLHttpRequest();
  var params = "url="+url;
  if (add)
    params += "&action=whitelistUrl";
  else
    params += "&action=removeWhitelistUrl";
  request.open("POST", tbPluginUrl+"/talkbackRequest.php", false);
  request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
  request.setRequestHeader("Content-length", params.length);
  request.setRequestHeader("Connection", "close");
  request.send(params);
  if (request.responseText == "")
     window.location.reload();
  else
    alert(request.responseText);
}

function tbUpdateConfig ()
{
  var request = new XMLHttpRequest();
  var params = "action=updateConf";
  params += "&WL="+document.talkbackConfig.enableWhitelisting.checked;
  params += "&AU="+document.talkbackConfig.enableAutodiscovery.checked;
  params += "&QU="+document.talkbackConfig.enableQueuing.checked;
  params += "&EN="+document.talkbackConfig.enableEncryption.checked;
  params += "&RP="+document.talkbackConfig.acceptSelect.value;
  request.open("POST", tbPluginUrl+"/talkbackRequest.php", false);
  request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
  request.setRequestHeader("Content-length", params.length);
  request.setRequestHeader("Connection", "close");
  request.send(params);
}

function tbShow (id)
{
  if (document.getElementById(id).style.display == "none")
    document.getElementById(id).style.display = "block";
  else
    document.getElementById(id).style.display = "none";
}