<? 

require_once 'CAS.php';

include_once "config.inc.php";

$cas_login_url = "https://$cas_host$cas_context/login";

function time_before_forcing_CAS_authentication_again($different_referrer) {
  return $different_referrer ? 10 : 120; // seconds
}

$APPS["redirect-first"] = 
    array("text" => "",
	  "description" => "",
	  "users" => array(), "groups" => array(),
	  "url" => "$bandeau_ENT_url/ent.html");


function compute_wanted_attributes() {
  global $GROUPS, $minimal_attrs;

  $r = array();
  foreach ($minimal_attrs as $attr)
    $r[$attr] = 1;

  foreach ($GROUPS as $attr2regexes) {
    foreach (array_keys($attr2regexes) as $attr)
      $r[$attr] = 1;
  }
  return array_keys($r);
}

function debug_msg($msg) {
  global $debug_msgs;
  if (!$debug_msgs) $debug_msgs = '';
  $debug_msgs .= "// $msg\n";
}

function ent_url($fname, $isGuest = false) {
  global $ent_base_url;
  return $ent_base_url . ($isGuest ? "/Guest" : "/Login") . "?uP_fname=$fname";
}

function is_admin($uid) {
  global $admins;
  return in_array($uid, $admins);
}

function removeParameterFromUrl($parameterName, $url) {
   $parameterName = preg_quote($parameterName);
   $url = preg_replace("/\?$parameterName(=[^&]*)?&/", '?', $url);
   $url = preg_replace("/(&|\?)$parameterName(=[^&]*)?/", '', $url);
   return $url;
}

function getAndUnset(&$a, $prop) {
  if (isset($a[$prop])) {
    $v = $a[$prop];
    unset($a[$prop]);
    return $v;
  } else {
    return null;
  }
}

function formattedElapsedTime($prev) {
  $now = microtime(true);
  return sprintf("%dms", ($now - $prev) * 1000);
}

function initPhpCAS($host, $port, $context, $CA_certificate_file) {
  phpCAS::client(SAML_VERSION_1_1, $host, intval($port), $context, false);
  if ($CA_certificate_file)
    phpCAS::setCasServerCACert($CA_certificate_file);
  else
    phpCAS::setNoCasServerValidation();
  //phpCAS::setLang(PHPCAS_LANG_FRENCH);
}

function toggle_auth_checked_in_redirect() {
  $url = phpCAS::getServiceURL();
  $without_auth_checked = removeParameterFromUrl('auth_checked', $url);
  if ($url == $without_auth_checked) {
    $url .= (strpos($url, '?') === false ? '?' : '&') . 'auth_checked=true';
  } else {    
    $url = $without_auth_checked;
    debug_msg("removing auth_checked from url to have a clean final url: $url");
  }
  phpCAS::setFixedServiceURL($url);
}
 
function checkAuthentication_raw($noCache, $haveTicket) {
  if (isset($_GET["auth_checked"])) {
    $noCookies = !isset($_COOKIE["PHPSESSID"]);
    if ($noCookies)
      debug_msg("cookie disabled or not accepted"); 

    $_SESSION['time_before_verifying_CAS_ticket'] = microtime(true);
    $_SESSION['time_before_redirecting_to_CAS'] = getAndUnset($_SESSION, 'time_before_adding_auth_checked');

    if ($noCookies || $noCache) {
      // do not redirect otherwise 
      // - if noCookies, it will dead-loop
      // - if noCache, we must not clean url otherwise "cleanup SESSION" will be done after final redirect to clean URL
      phpCAS::setNoClearTicketsFromUrl();
    } else if ($haveTicket) {   
      // remove "auth_checked" after CAS before redirecting to final URL
      toggle_auth_checked_in_redirect();
    }

    $isAuthenticated = phpCAS::isAuthenticated();
    $wasPreviouslyAuthenticated = false;
  } else {
    // add "auth_checked" in url before redirecting to CAS
    toggle_auth_checked_in_redirect();

    $_SESSION['time_before_adding_auth_checked'] = microtime(true);
    $isAuthenticated = phpCAS::checkAuthentication();

    // NB: if we reach this point, we are either in "wasPreviouslyAuthenticated" case or after final redirect to clean URL
    $noCookies = false;
  }
  return array($isAuthenticated, $noCookies);
}

function checkAuthentication($noCache, $haveTicket) {
  list ($isAuthenticated, $noCookies) = checkAuthentication_raw($noCache, $haveTicket);

  $before_all = getAndUnset($_SESSION, 'time_before_redirecting_to_CAS');
  $before_verif = getAndUnset($_SESSION, 'time_before_verifying_CAS_ticket');
  if ($before_verif)
    debug_msg("CAS ticket verification time: " . formattedElapsedTime($before_verif));
  if ($before_all)
    debug_msg("total CAS authentication time: " . formattedElapsedTime($before_all));

  $wasPreviouslyAuthenticated = $before_verif === null;

  return array($isAuthenticated, $noCookies, $wasPreviouslyAuthenticated);
}

function get_uid() {
  $uid = phpCAS::getUser();
  debug_msg("uid is $uid");
  if (isset($_GET['uid'])) {
	  if (is_admin($uid)) return $_GET['uid'];
	  exit("$uid is not allowed to fake a user");
  }
  return $uid;
}

function getLdapInfo($filter) {
  global $ldap_server, $ldap_bind_dn, $ldap_bind_password, $ou_people;

  $wanted_attributes = compute_wanted_attributes();

  $ds=ldap_connect($ldap_server);
  if (!$ds) exit("error: connection to $ldap_server failed");
  if ($ldap_bind_dn) {
    if (!ldap_bind($ds,$ldap_bind_dn,$ldap_bind_password)) exit("error: failed to bind using $ldap_bind_dn");
  }

  //$ds=ldap_connect($ldap_server);

  $all_entries = ldap_get_entries($ds, ldap_search($ds, $ou_people, $filter, $wanted_attributes));
  $entries = $all_entries[0];
  $info = array();
  foreach ($wanted_attributes as $attr) {
    $attrL = strtolower($attr);
    if (!isset($entries[strtolower($attrL)])) {
      debug_msg("missing attribute $attr in LDAP for user $filter");
      continue;
    }
    $value = $entries[$attrL];
    unset($value["count"]);
    $info[$attr] = $value;
  }
  return $info;
}

function computeGroups($person) {
  global $GROUPS;

  $r = array();
  foreach ($GROUPS as $name => $attr2regexes) {
    $ok = true;
    foreach ($attr2regexes as $attr => $regexes) {
      if (!isset($person[$attr])) {
	debug_msg("missing attribute $attr");
	$ok = false;
	break;
      }
      $value = $person[$attr];
      $regexes = is_array($regexes) ? $regexes : array($regexes);
      $okAttr = true;
      foreach ($regexes as $regex) {
	$okOne = false;
	foreach ($value as $v) {
	  if (preg_match("/$regex/", $v))
	    $okOne = true;
	}
	if (!$okOne) $okAttr = false;
      }
      if (!$okAttr) $ok = false;
    }
    if ($ok) $r[] = $name;	
  }
  return $r;
}


function get_url($app, $appId, $isGuest) {
  global $cas_login_url;
  if (isset($app['url']) && isset($app['url_bandeau_compatible']))
    return isset($app['force_CAS']) ? via_CAS($app['force_CAS'], $app['url']) : $app['url'];
  else
    return $isGuest ? ent_url($appId, true) : via_CAS($cas_login_url, ent_url($appId));
}

function exportApp($app, $appId, $isGuest) {
  $r = array("description" => $app['description'],
	     "text" => $app['text'],
	     "url" => get_url($app, $appId, $isGuest));
  if (isset($app['title'])) $r['title'] = $app['title'];
  return $r;
}

function exportApps($isGuest) {
  global $APPS;

  $r = array();
  foreach ($APPS as $appId => $app) {
    $r[$appId] = exportApp($app, $appId, $isGuest);
  }
  return $r;
}

function computeValidAppsRaw($person, $groups) {
  global $APPS;

  $user = isset($person["uid"]) ? $person["uid"][0] : 'guest-lo'; // uportal specific

  $r = array();
  foreach ($APPS as $appId => $app) {
    $found = false;

    if ($app["users"] && $user) {
      if (in_array($user, $app["users"]))
	$found = true;
    }

    if (!$found) {
      foreach ($app["groups"] as $group) {
	if (in_array($group, $groups))
	  $found = true;
      }
    }
    if (!$found) continue;
    
    $r[] = $appId;
  }
  return $r;
}

function via_CAS($cas_login_url, $href) {
  return sprintf("%s?service=%s", $cas_login_url, urlencode($href));
}

function computeOneLayoutRaw($validApps, $layout) {
  $r = array();
  foreach ($layout as $title => $subApps) {
    $l = array_intersect($subApps, $validApps);
    if ($l) {
      debug_msg("adding tab $title");
    
      $l = array_values($l); // needed to ensure the indexes are re-computed so that json_encode export as ["appName"] instead of {"1":"appName"}
      $r[] = array("title" => $title, "apps" => $l);
    }
  }
  return $r;
}

function computeLayoutRaw($validApps, $person) {
  global $LAYOUT_ALL, $LAYOUT_GUEST;
  return computeOneLayoutRaw($validApps, isset($person["uid"]) ? $LAYOUT_ALL : $LAYOUT_GUEST);
}

function computeLayout($person) {
  $groups = computeGroups($person);
  debug_msg((isset($person["uid"]) ? $person["uid"][0] : "anonymous") . " is member of groups: " . implode(" ", $groups));
  $validApps = computeValidAppsRaw($person, $groups);
  debug_msg("valid apps: " . implode(" ", $validApps));
  return computeLayoutRaw($validApps, $person);
}

function computeBandeauHeaderLinks($person) {
  $s = <<<EOD

      <div id='portalPageBarAccount'>
	<a href='#'>
          <span>%s</span>
        </a>
      </div>

      <div id='portalPageBarAccountInner'>
	<ul>
	  <li class='portalPageBarAccountDescr'>%s (%s)</li>
	  <li class='portalPageBarAccountAnchor'>
	    <a title='Interface de gestion de compte de l&#39;université Paris 1 Panthéon-Sorbonne.' href='%s'>
	      <span>Mon compte</span>
	    </a>
	  </li>
	  <li class='portalPageBarAccountAnchor portalPageBarAccountLogout'>
	    <a title='Se déconnecter' href='<%%logout_url%%>'>
	      <span>Déconnexion</span>
	    </a>
	  </li>
	</ul>
      </div>


      <span id='bandeau_ENT_portalPageBarToggleMenu'>
        <span>ENT</span>
        <a>
          <span>Toggle menu</span>
        </a>
      </span> 

      <span class='portalPageBarLogout'>
	<a title='Se déconnecter' href='<%%logout_url%%>'>
	  <span>Déconnexion</span>
	</a>
      </span>
EOD;

  global $cas_login_url, $ent_base_url, $bandeau_ENT_url;
  $activation_url = via_CAS($cas_login_url, ent_url('CActivation'));
  return sprintf($s, $person["displayName"][0], $person["mail"][0], $person["uid"][0], 
		 $activation_url);
}
 
function computeBandeauHeaderLinksAnonymous() {
   $s = <<<EOD
      <span id="portalPageBarLogin">
        <a title="Connexion via le Service Central d'Authentification" href="%s" >
          <span>Connexion</span>
        </a>
      </span>
EOD;

   global $cas_login_url;
   $login_url = via_CAS($cas_login_url, ent_url(''));
   return sprintf($s, $login_url);
}

function computeBandeauHeader($person) {
  $s = <<<EOD
  <div class='portalPageBar'>
    <div class='portalPageBarLinks'>
%s
   </div>
  </div>

  <div class='bandeau_ENT_portalLogo'>
    <a target='_blank' title='Universite Paris 1 Pantheon-Sorbonne: Accueil' href='http://www.univ-paris1.fr/'>
      <img src='%s' />
    </a>
  </div>
EOD;

  global $ent_base_url;
  $portalPageBarLinks = $person ? computeBandeauHeaderLinks($person) : computeBandeauHeaderLinksAnonymous();
  $accueil_url = $ent_base_url . '/render.userLayoutRootNode.uP?uP_root=root&amp;uP_sparam=activeTab&amp;activeTab=1';
  $portal_logo = $ent_base_url . '/media/skins/universality/uportal3/images/portal_logo_slim.png';

  return sprintf($s, $portalPageBarLinks, $portal_logo, $accueil_url);
}

function url2host($url) {
  $p = parse_url($url);
  return $p ? $p["host"] : null;
}

function referer_hostname_changed() {
  if (!isset($_SERVER['HTTP_REFERER'])) return false;

  $current_host = url2host($_SERVER['HTTP_REFERER']);
  debug_msg("current_host $current_host");
  if (isset($_SESSION["prev_host"])) {
    debug_msg("prev_host " . $_SESSION["prev_host"]);
    $changed = $_SESSION["prev_host"] != $current_host;
    if ($changed) debug_msg("referer_hostname_changed: previous=" . $_SESSION["prev_host"] . " current=$current_host");
  } else {
    $changed = false;
  }
  $_SESSION["prev_host"] = $current_host;
  return $changed;
}

function is_old() {
  $max_age = time_before_forcing_CAS_authentication_again(referer_hostname_changed());
  $now = time();
  $is_old = false;
  if (isset($_SESSION["prev_time"])) {
    $age = $now - $_SESSION["prev_time"];
    $is_old = $age > $max_age;
    //debug_msg("$age > $max_age");
    if ($is_old) debug_msg("response is potentially old: age is $age (more than $max_age)");
  } else {
    $_SESSION["prev_time"] = $now;
  }
  return $is_old;
}

$request_start_time = microtime(true);

$haveTicket = isset($_GET["ticket"]); // must be done before initPhpCAS which removes it
$noCache = isset($_GET["noCache"]);
session_start();
if ($noCache && !isset($_GET["auth_checked"])) {
  // cleanup SESSION, esp. to force CAS authentification again
  debug_msg("cleaning SESSION");
  $_SESSION = array();
}
initPhpCAS($cas_host, '443', $cas_context, $CA_certificate_file);
list ($isAuthenticated, $noCookies, $wasPreviouslyAuthenticated) = checkAuthentication($noCache, $haveTicket);


if (!$isAuthenticated)
  setcookie("PHPSESSID", "", 1, "/");


$uid = $isAuthenticated ? get_uid() : '';
$person = $uid ? getLdapInfo("uid=$uid") : array();

$layout = computeLayout($person);
$bandeauHeader = computeBandeauHeader($person);
$exportApps = exportApps(!$person);
$static_js = file_get_contents('bandeau-ENT-static.js');

$is_old = is_old();

$js_conf = array('cas_login_url' => $cas_login_url,
		 'bandeau_ENT_url' => $bandeau_ENT_url,
		 'ent_logout_url' => $ent_base_url . '/Logout');

$js_data = array('person' => $person,
		 'bandeauHeader' => $bandeauHeader,
		 'apps' => $exportApps,
		 'layout' => $layout);
$js_data["hash"] = md5(json_encode(array($js_data, $static_js)));
$js_data["time"] = time();
$js_data["wasPreviouslyAuthenticated"] = $wasPreviouslyAuthenticated;
$js_data['is_old'] = $is_old;

$js_css = array('base' => file_get_contents('bandeau-ENT.css'),
		'desktop' => file_get_contents('bandeau-ENT-desktop.css'));

debug_msg("request time: " . formattedElapsedTime($request_start_time));

header('Content-type: application/javascript; charset=utf8');
echo "$debug_msgs\n";
echo "(function () {\n\n";
echo "var CONF = " . json_encode($js_conf) . ";\n\n";
echo "var DATA = " . json_encode($js_data) . ";\n\n";
echo "var CSS = " . json_encode($js_css) . ";\n\n";
echo $static_js;
echo "}())\n";

?>
