/* !
 * TalkBack wordpress plugin javascript.
 * @Author: Elie Bursztein (elie _AT_ cs.stanford.edu), Baptiste Gourdin (bgourdin _AT_ cs.stanford.edu)
 * @version: 1.0
 * @url: http://talkback.stanford.edu
 * @Licence: LGPL
 */

function tbRegister(authorityUrl)
{
  var form = document.forms['registerForm'];
  var url = authorityUrl + "/registration.php?blogName="+escape(form.blogName.value);
  url += "&blogURL="+escape(form.blogUrl.value);
  url += "&pluginURL="+escape(form.pluginUrl.value);
  url += "&email="+escape(form.email.value);
  url += "&blogPublicKey="+escape(form.pubKey.value);

  window.open(url,'Talkback registration','height=400,width=400');
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
  var form = document.forms['talkbackConfig'];
  params += "&WL="+form.enableWhitelisting.checked;
  params += "&AU="+form.enableAutodiscovery.checked;
//  params += "&QU="+form.enableQueuing.checked;
  params += "&EN="+form.enableEncryption.checked;
  params += "&RP="+form.acceptSelect.value;
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