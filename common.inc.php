<?php

include_once "config.inc.php";

$cas_login_url = "https://$cas_host$cas_context/login";
$cas_logout_url = "https://$cas_host$cas_context/logout";


function debug_msg($msg) {
  global $debug_msgs;
  if (!$debug_msgs) $debug_msgs = '';
  $debug_msgs .= "// $msg\n";
}

function startsWith($hay, $needle) {
  return substr($hay, 0, strlen($needle)) === $needle;
}
function removePrefix($s, $prefix) {
    return startsWith($s, $prefix) ? substr($s, strlen($prefix)) : $s;
}
function removePrefixOrNULL($s, $prefix) {
    return startsWith($s, $prefix) ? substr($s, strlen($prefix)) : NULL;
}

function atomic_file_put_contents($file, $content) {
  $tmp_file = $file . ".tmp";
  if (!file_put_contents($tmp_file, $content))
    exit("failed to write $tmp_file");
  if (!rename($tmp_file, $file))
    exit("failed to rename $tmp_file into $file");
}

function ent_url($app, $fname, $isGuest, $noLogin, $idpAuthnRequest_url) {
  global $ent_base_url, $ent_base_url_guest, $cas_login_url;
  $url = $isGuest ? "$ent_base_url_guest/Guest" : $ent_base_url . ($noLogin ? '/render.userLayoutRootNode.uP' : '/Login');
  $uportalActiveTab = @$app[$isGuest ? 'uportalActiveTabGuest': 'uportalActiveTab'];
  $params = 
      "?uP_fname=$fname"
    . ($uportalActiveTab ? "&uP_sparam=activeTab&activeTab=$uportalActiveTab" : '');
  $url = "$url$params";
  return $isGuest || $noLogin ? $url : 
      ($idpAuthnRequest_url ? via_idpAuthnRequest_url($idpAuthnRequest_url, $url) : via_CAS($cas_login_url, $url));
}

function via_CAS($cas_login_url, $href) {
  return sprintf("%s?service=%s", $cas_login_url, urlencode($href));
}

// quick'n'dirty version: it expects a simple mapping from url to SP entityId and SP SAML v1 url
function via_idpAuthnRequest_url($idpAuthnRequest_url, $url) {
  $spId = preg_replace('!(://[^/]*)(.*)!', '$1', $url);
  $shire = "$spId/Shibboleth.sso/SAML/POST";
  return sprintf("%s?shire=%s&target=%s&providerId=%s", $idpAuthnRequest_url, urlencode($shire), urlencode($url), urlencode($spId));
}

function idpAuthnRequest_url($idpId) {
  global $currentIdpId;
  if ($idpId !== $currentIdpId) {
     global $entityID_to_AuthnRequest_url;
     require_once 'federation-renater/entityID_to_AuthnRequest_url.inc.php';
     return $entityID_to_AuthnRequest_url[$idpId];
  }
  return;
}

function url_maybe_adapt_idp($url, $idpAuthnRequest_url) {
  if (!$idpAuthnRequest_url) return $url;
  global $currentIdpId;
  global $entityID_to_AuthnRequest_url;
  $currentAuthnRequest = $entityID_to_AuthnRequest_url[$currentIdpId];
  $url_ = removePrefixOrNULL($url, $currentAuthnRequest);
  if ($url_) {
        $url = $idpAuthnRequest_url . $url_;
        debug_msg("personalized shib url is now $url");
  }
  return $url;
}

function enhance_url($url, $appId, $options) {
    global $ent_base_url, $cas_login_url;
    if (@$options['useExternalURLStats'])
        $url = "$ent_base_url/ExternalURLStats?fname=$appId&service=" . urlencode($url);

    if (@$options['force_CAS'])
	$url = via_CAS($cas_login_url, $url);

    return $url;
}

function get_url($app, $appId, $isGuest, $noLogin, $idpAuthnRequest_url) {
  if (isset($app['url']) && isset($app[$idpAuthnRequest_url ? 'url_bandeau_compatible' : 'url_bandeau_direct'])) {
    $url = url_maybe_adapt_idp($app['url'], $idpAuthnRequest_url);
    return enhance_url($url, $appId, $app);
  } else {
    return ent_url($app, $appId, $isGuest, $noLogin, $idpAuthnRequest_url);
  }
}

?>