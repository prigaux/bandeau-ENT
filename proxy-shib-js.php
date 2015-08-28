<?php

include_once "config.inc.php";

$idp = @$_SERVER['HTTP_SHIB_IDENTITY_PROVIDER'];
$url = $idp2url[$idp];
if ($url) {
  proxyPass($url);
} else {
  exit("unknown idp " . $idp);
}

function inHeaders() {
  $header = array();
  foreach(getallheaders() as $key => $value) {
    $header[] = $key . ':' . $value;
  }
  return $header;
}

function proxyPass($url) {
  $ch=curl_init($url);
  curl_setopt($ch,CURLOPT_HTTPHEADER, inHeaders());

  curl_setopt($ch,CURLOPT_HEADER,true);
  curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
  $output=curl_exec($ch);
  $info = curl_getinfo( $ch );
  curl_close($ch);

  $resultHeader=substr($output,0,$info['header_size']);
  $content=substr($output,$info['header_size']);

  foreach (explode("\r\n", $resultHeader) as $oneHeader) {
    header($oneHeader);
  }
  echo $content;
}

?>
