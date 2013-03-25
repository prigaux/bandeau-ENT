<? 

include_once "config.inc.php";
include_once "common.inc.php";

if (isset($_GET["uportalActiveTab"])) {
  $activeTab = $_GET["uportalActiveTab"];
  if (!tab_has_non_https_url($activeTab, isset($_GET["guest"])) && !isset($_GET["idpId"])) {
    // ok: let uportal display all channels
    $location = ent_tab_url($activeTab);
  } else {
    // gasp, there is a http:// iframe, display only one channel (nb: if the first channel is http-only, it will be displayed outside of uportal)
    $location = get_app_url($_GET["firstId"]);
  }
} else if (isset($_GET["id"])) {
  $location = get_app_url($_GET["id"]);
} else {
  exit("missing 'id=xxx' parameter");
}
header("Location: $location");

function get_app_url($id) {
  global $APPS;
  $app = $APPS[$id];
  if (!$app) $app = array(); // gasp, go on anyway...
  $idpAuthnRequest_url = isset($_GET["idpId"]) ? idpAuthnRequest_url($_GET["idpId"]) : null;
  $url = get_url($app, $id, isset($_GET["guest"]), !isset($_GET["login"]), $idpAuthnRequest_url);
  if ($url) 
    return $url;
  else
    exit("invalid app id $id");
}

function contains($s, $needle) {
  return strpos($s, $needle) !== false;
}
function tab_has_non_https_url($uportalActiveTab, $isGuest) {
  global $APPS;
  $key = $isGuest ? 'uportalActiveTabGuest' : 'uportalActiveTab';
  foreach ($APPS as $appId => $app) {
     if (@$app[$key] == $uportalActiveTab) {
	if (contains(urldecode(@$app["url"]), "http://")) {
	    error_log("forcing first app of tab " . $uportalActiveTab . " because of $appId");
	    return true;
        }
     }
  }
  return false;
}

function ent_tab_url($uportalActiveTab) {
    global $ent_base_url;
    $url = $ent_base_url . '/render.userLayoutRootNode.uP';
    $params = 
      "?uP_root=root"
    . "&uP_sparam=activeTab&activeTab=$uportalActiveTab";
    return "$url$params";
}

?>
