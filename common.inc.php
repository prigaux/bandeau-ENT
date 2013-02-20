<?

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

function ent_url($app, $fname, $isGuest, $noLogin) {
  global $ent_base_url, $ent_base_url_guest, $cas_login_url;
  $url = $isGuest ? "$ent_base_url_guest/Guest" : $ent_base_url . ($noLogin ? '/render.userLayoutRootNode.uP' : '/Login');
  $uportalActiveTab = @$app[$isGuest ? 'uportalActiveTabGuest': 'uportalActiveTab'];
  $params = 
      "?uP_fname=$fname"
    . ($uportalActiveTab ? "&uP_sparam=activeTab&activeTab=$uportalActiveTab" : '');
  $url = "$url$params";
  return $isGuest || $noLogin ? $url : via_CAS($cas_login_url, $url);
}

function via_CAS($cas_login_url, $href) {
  return sprintf("%s?service=%s", $cas_login_url, urlencode($href));
}

function enhance_url($url, $appId, $options) {
    global $ent_base_url, $cas_login_url;
    if (@$app['useExternalURLStats'])
        $url = "$ent_base_url/ExternalURLStats?fname=$appId&service=" . urlencode($url);

    if (@$options['force_CAS'])
	$url = via_CAS($cas_login_url, $url);

    return $url;
}

function get_url($app, $appId, $isGuest, $noLogin) {
  if (isset($app['url']) && isset($app['url_bandeau_compatible'])) {
    return enhance_url($app['url'], $appId, $app);
  } else {
    return ent_url($app, $appId, $isGuest, $noLogin);
  }
}

?>