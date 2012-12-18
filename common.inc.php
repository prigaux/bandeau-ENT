<?

include_once "config.inc.php";

$cas_login_url = "https://$cas_host$cas_context/login";


function debug_msg($msg) {
  global $debug_msgs;
  if (!$debug_msgs) $debug_msgs = '';
  $debug_msgs .= "// $msg\n";
}

function ent_url($fname, $isGuest = false, $uportalActiveTab = '') {
  global $ent_base_url, $ent_base_url_guest;
  return ($isGuest ? "$ent_base_url_guest/Guest" : "$ent_base_url/Login")
    . "?uP_fname=$fname"
    . ($uportalActiveTab ? "&uP_sparam=activeTab&activeTab=$uportalActiveTab" : '');
}

function via_CAS($cas_login_url, $href) {
  return sprintf("%s?service=%s", $cas_login_url, urlencode($href));
}

function get_url($app, $appId, $isGuest) {
  global $cas_login_url;
  if (isset($app['url']) && isset($app['url_bandeau_compatible']))
    return isset($app['force_CAS']) ? via_CAS($app['force_CAS'], $app['url']) : $app['url'];
  else
    return $isGuest ? ent_url($appId, true, @$app['uportalActiveTab']) : via_CAS($cas_login_url, ent_url($appId, false, @$app['uportalActiveTab']));
}

?>