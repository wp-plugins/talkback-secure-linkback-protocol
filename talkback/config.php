<?
$tbDataDir = dirname(__FILE__)."/work"; //Where to store the keys ? Key filename MUST BE random
$tbLogDir = dirname(__FILE__)."/log";

$tbConfig = array (
        "tbDataDir" =>$tbDataDir,
        "logDir" => $tbLogDir
);

function loadConfig ()
{
  global $tbDataDir, $tbConfig;

  $xmlDoc = new DOMDocument();
  $xmlDoc->load($tbDataDir."/config.xml");

  $config = $xmlDoc->getElementsByTagName("config");

  foreach ($config->item(0)->childNodes as $item)
  {
    if ($item->nodeName == "#text")
      continue;
    $tbConfig[$item->nodeName] = $item->nodeValue;
  }
}
loadConfig();

function saveConfig()
{
  global $tbDataDir, $tbConfig;

  $xmlDoc = new DOMDocument();
  $xmlDoc->load($tbDataDir."/config.xml");

  $config = $xmlDoc->getElementsByTagName("config");

  foreach ($config->item(0)->childNodes as $item)
  {
    if ($item->nodeName == "#text")
      continue;
    $item->nodeValue = $tbConfig[$item->nodeName];
  }
  $xmlDoc->save($tbDataDir."/config.xml");
}
?>