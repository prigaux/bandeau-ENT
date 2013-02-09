<? 

require_once 'CAS.php';

include_once "config.inc.php";
include_once "common.inc.php";

$time_before_checking_browser_cache_is_up_to_date = 60; // seconds

function time_before_forcing_CAS_authentication_again($different_referrer) {
  return $different_referrer ? 10 : 120; // seconds
}


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

function startsWith($hay, $needle) {
  return substr($hay, 0, strlen($needle)) === $needle;
}
function removePrefix($s, $prefix) {
    return startsWith($s, $prefix) ? substr($s, strlen($prefix)) : $s;
}
function removePrefixOrNULL($s, $prefix) {
    return startsWith($s, $prefix) ? substr($s, strlen($prefix)) : NULL;
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
    $info[$attrL] = $value;
  }
  return $info;
}

function eppn2uid($eppn) {
  global $eppnDomainRegexForUid;
  if ($eppnDomainRegexForUid)
	return preg_replace("/$eppnDomainRegexForUid/", "", $eppn);
  else
	return $eppn;
}

function getShibPersonFromHeaders() {
  $person = array();
  foreach ($_SERVER as $k => $v) {
    $k = removePrefixOrNULL($k, "HTTP_");

    if ($k === "UNSCOPED_AFFILIATION") $k = "eduPersonAffiliation";
    if ($k === "PRIMARY_AFFILIATION") $k = "eduPersonPrimaryAffiliation";
    if ($k === "ORG_DN") $k = "eduPersonOrgDN";

    if ($k && !preg_match("/^(Accept|Accept_Charset|Accept_Encoding|Accept_Language|Accept_Datetime|Authorization|Cache_Control|Connection|Cookie|Content_Length|Content_MD5|Content_Type|Date|Expect|From|Host|If_Match|If_Modified_Since|If_None_Match|If_Range|If_Unmodified_Since|Max_Forwards|Pragma|Proxy_Authorization|Range|Referer|TE|Upgrade|User_Agent|Via|Warning)$/i", $k)
	&& !preg_match("/^(X_.*|Shib_Application_ID|Shib_Authentication_Instant|Shib_AuthnContext_Decl|Shib_Session_ID|Shib_Assertion_Count|Shib_Authentication_Method|Shib_AuthnContext_Class)$/i", $k)
	&& $k !== "PREFERREDLANGUAGE" && $v) {
      $person[strtolower($k)] = explode(";", $v);
    }
  }
  $person['uid'] = eppn2uid($person['eppn']);
  return $person;
}

function computeGroups($person) {
  global $GROUPS;

  $r = array();
  foreach ($GROUPS as $name => $attr2regexes) {
    $ok = true;
    foreach ($attr2regexes as $attr => $regexes) {
      $value = @$person[strtolower($attr)];
      if (!$value) {
	debug_msg("missing attribute $attr");
	$ok = false;
	break;
      }
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

function get_appId_url($appId) {
  global $APPS;
  $app = $APPS[$id];
  return get_url($app, $appId, false, false);
}

function person_url($url, $person) {
  $idpIds = @$person['shib_identity_provider'];
  if (!$idpIds) return $url;
  $idpId = $idpIds[0];
  global $currentIdpId;
  if ($idpId !== $currentIdpId) {
     global $entityID_to_AuthnRequest_url;
     require_once 'federation-renater/entityID_to_AuthnRequest_url.inc.php';
     $currentAuthnRequest = $entityID_to_AuthnRequest_url[$currentIdpId];
     $url_ = removePrefixOrNULL($url, $currentAuthnRequest);
     if ($url_) {
	$url = $entityID_to_AuthnRequest_url[$idpId] . $url_;
	debug_msg("personalized shib url is now $url");
     }
  }
  return $url;
}

function get_person_url($app, $appId, $person) {
  if (isset($app['url']) && isset($app['url_bandeau_compatible'])) {
    $url = person_url($app['url'], $person);
    return enhance_url($url, $appId, $app);
  } else {
    return ent_url($app, $appId, false, false);
  }
}

function exportApp($app, $appId, $person) {
  $r = array("description" => $app['description'],
	     "text" => $app['text'],
	     "url" => get_person_url($app, $appId, $person));
  foreach (array('title', 'hashelp') as $key) {
    if (isset($app[$key])) $r[$key] = $app[$key];
  }
  return $r;
}

function exportApps($person) {
  global $APPS;

  $r = array();
  foreach ($APPS as $appId => $app) {
    $r[$appId] = exportApp($app, $appId, $person);
  }
  return $r;
}

function computeValidAppsRaw($person, $groups) {
  global $APPS;

  $user = $person["uid"][0];

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
  global $LAYOUT_ALL;
  return computeOneLayoutRaw($validApps, $LAYOUT_ALL);
}

function computeLayout($person) {
  if (!@$person["uid"]) return array(array(), array());

  $groups = computeGroups($person);
  debug_msg($person["uid"][0] . " is member of groups: " . implode(" ", $groups));
  $validApps = computeValidAppsRaw($person, $groups);
  debug_msg("valid apps: " . implode(" ", $validApps));
  return array($validApps, computeLayoutRaw($validApps, $person));
}

function computeBandeauHeaderLinkMyAccount($validApps) {
  if (!in_array('CActivation', $validApps))
    return '';
  
  $s = <<<EOD
	  <li class='portalPageBarAccountAnchor'>
	    <a title='Interface de gestion de compte de l&#39;université Paris 1 Panthéon-Sorbonne.' href='%s'>
	      <span>Mon compte</span>
	    </a>
	  </li>
EOD;

  $activation_url = get_appId_url('CActivation');

  return sprintf($s, $activation_url);
}

function computeBandeauHeaderLinks($person, $validApps) {
  $s = <<<EOD
    <div class='portalPageBarLinks'>
      <div id='portalPageBarAccount'>
	<a href='#'>
          <span>%s</span>
        </a>
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
    </div>

    <div id='portalPageBarAccountInner'>
	<ul>
	  <li class='portalPageBarAccountDescr'>%s</li>
%s        
	  <li class='portalPageBarAccountAnchor portalPageBarAccountLogout'>
	    <a title='Se déconnecter' href='<%%logout_url%%>'>
	      <span>Déconnexion</span>
	    </a>
	  </li>
	</ul>
    </div>

EOD;

  $myAccount = computeBandeauHeaderLinkMyAccount($validApps);

  return sprintf($s, (@$person["displayname"] ? $person["displayname"][0] : $person["mail"][0]), 
		 (@$person["displayname"] ? $person["mail"][0] . " (" . $person["uid"][0] . ")" : $person["uid"][0]), 
		 $myAccount);
}

function computeBandeauHeader($person, $validApps) {
  $s = <<<EOD
  <div class='portalPageBar'>
%s
  </div>

  <div class='bandeau_ENT_portalLogo'>
    <a title='Universite Paris 1 Pantheon-Sorbonne: Accueil' href='http://www.univ-paris1.fr/'>

    </a>
  </div>
EOD;

  $portalPageBarLinks = $person ? computeBandeauHeaderLinks($person, $validApps) : '';

  return sprintf($s, $portalPageBarLinks);
}

function get_css_with_absolute_url($css_file) {
  global $bandeau_ENT_url;
  $s = file_get_contents($css_file);
  return preg_replace("/(url\(['\" ]*)(?!['\" ])(?!https?:|\/)/", "$1$bandeau_ENT_url/", $s);
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
session_cache_limiter('private');
session_cache_expire(0);

if (@$_SERVER['HTTP_SHIB_IDENTITY_PROVIDER']) {
  list ($isAuthenticated, $noCookies, $wasPreviouslyAuthenticated) = array(true, false, false);
  $person = getShibPersonFromHeaders();
  $is_old = false;
} else {
  $haveTicket = isset($_GET["ticket"]); // must be done before initPhpCAS which removes it
  $noCache = isset($_GET["noCache"]);
  if (@$_GET["PHPSESSID"]) $_COOKIE["PHPSESSID"] = $_GET["PHPSESSID"];
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
  $person = $uid ? ($ldap_server ? getLdapInfo("uid=$uid") : array("uid" => array($uid))) : array();
  $is_old = is_old();
}

list ($validApps, $layout) = computeLayout($person);
$bandeauHeader = computeBandeauHeader($person, $validApps);
$exportApps = exportApps($person);
$static_js = file_get_contents('bandeau-ENT-static.js');
$default_logout_url = @$ent_base_url ? $ent_base_url . '/Logout' : (@$layout[0] ? via_CAS($cas_login_url, $APPS[$layout[0]["apps"][0]]["url"]) : '');

$js_conf = array('cas_login_url' => $cas_login_url,
		 'bandeau_ENT_url' => $bandeau_ENT_url,
		 'ent_logout_url' => via_CAS($cas_logout_url, $default_logout_url), // nb: esup logout may not logout of CAS if user was not logged in esup portail, so forcing CAS logout in case
		 'time_before_checking_browser_cache_is_up_to_date' => $time_before_checking_browser_cache_is_up_to_date,
		 );

$js_data = array('person' => $person,
		 'bandeauHeader' => $bandeauHeader,
		 'apps' => $exportApps,
		 'layout' => $layout);

$js_css = array('base' => get_css_with_absolute_url('bandeau-ENT.css'),
		'desktop' => get_css_with_absolute_url('bandeau-ENT-desktop.css'));

$js_text_middle = 
  "var CONF = " . json_encode($js_conf) . ";\n\n" .
  "var DATA = " . json_encode($js_data) . ";\n\n" .
  "var CSS = " . json_encode($js_css) . ";\n\n" .
  $static_js;

$js_params = array('is_old' => $is_old,
		   'hash' => md5($js_text_middle),
		   );
if ($noCookies || @$_GET["PHPSESSID"]) $js_params['PHPSESSID'] = session_id();

$js_text = 
  "(function () {\n\n" .
  "'use strict';\n\n" .
  "var PARAMS = " . json_encode($js_params) . ";\n\n" .
  $js_text_middle .
  "}())\n";

$full_hash = md5($js_text);

if (@$_SERVER['HTTP_IF_NONE_MATCH'] === $full_hash && !@$disableLocalStorage) {
  header('HTTP/1.1 304 Not Modified');
  exit;
} else {
  header('ETag: ' . $full_hash);
}

debug_msg("request time: " . formattedElapsedTime($request_start_time));

header('Content-type: application/javascript; charset=utf8');
echo "$debug_msgs\n";
echo "window.bandeau_ENT.notFromLocalStorage = true;\n";
if (@$disableLocalStorage) {
  // for debug purpose: debugging eval'ed javascript code is tough...
  echo $js_text;
  $js_text = '';
}
echo "window.bandeau_ENT.js_text = " . json_encode($js_text) . ";\n\n";
echo "eval(window.bandeau_ENT.js_text);\n";

?>
