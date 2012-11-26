<? 

require_once 'CAS.php';

include_once "config.inc.php";

$cas_login_url = "https://$cas_host$cas_context/login";


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
  return true;
}

function removeParameterFromUrl($parameterName, $url) {
   $parameterName = preg_quote($parameterName);
   return preg_replace("/(&|\?)$parameterName(=[^&]*)?/", '', $url);
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
  $adding = $url == $without_auth_checked;
  if ($adding) {
    $url .= (strpos($url, '?') === false ? '?' : '&') . 'auth_checked=true';
  } else {    
    $url = $without_auth_checked;
    debug_msg("removing auth_checked from url to have a clean final url: $url");
  }
  phpCAS::setFixedServiceURL($url);
  return $adding;
}
 
function checkAuthentication_raw() {
  if (isset($_GET["auth_checked"]) && !isset($_COOKIE["PHPSESSID"])) {
    debug_msg("cookie disabled or not accepted"); 

    // do not redirect otherwise it will dead-loop:
    phpCAS::setNoClearTicketsFromUrl();

    $_SESSION['time_before_verifying_CAS_ticket'] = microtime(true);
    return phpCAS::isAuthenticated();

  } else {

    // Either:
    // - add "auth_checked" in url before redirecting to CAS
    // - remove it after CAS before redirecting to final URL
    $added = toggle_auth_checked_in_redirect();

    if ($added) {
      $_SESSION['time_before_adding_auth_checked'] = microtime(true);
    } else {
      $_SESSION['time_before_verifying_CAS_ticket'] = microtime(true);
      $_SESSION['time_before_redirecting_to_CAS'] = getAndUnset($_SESSION, 'time_before_adding_auth_checked');
    }
    return phpCAS::checkAuthentication();
  }
}

function checkAuthentication() {
  $isAuthenticated = checkAuthentication_raw();

  $before_all = getAndUnset($_SESSION, 'time_before_redirecting_to_CAS');
  $before_verif = getAndUnset($_SESSION, 'time_before_verifying_CAS_ticket');
  if ($before_verif)
    debug_msg("CAS ticket verification time: " . formattedElapsedTime($before_verif));
  if ($before_all)
    debug_msg("total CAS authentication time: " . formattedElapsedTime($before_all));

  //setcookie("PHPSESSID", "", 1, "/"); // remove cookie to force asking again

  return $isAuthenticated;
}

function get_uid() {
  $uid = phpCAS::getUser();
  debug_msg("uid is $uid");
  if (isset($_GET['uid'])) {
	  if (is_admin($uid)) return $_GET['uid'];
     set_div_innerHTML("$uid is not allowed to fake a user");
     exit(1); 
  }
  return $uid;
}

function getLdapInfo($filter) {
  global $ldap_server, $ou_people;

  $wanted_attributes = compute_wanted_attributes();
  $ds=ldap_connect($ldap_server);

  $all_entries = ldap_get_entries($ds, ldap_search($ds, $ou_people, $filter));
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
  if (isset($app['url']))
    return isset($app['force_CAS']) ? via_CAS($app['force_CAS'], $app['url']) : $app['url'];
  else
    return $isGuest ? ent_url($appId, true) : via_CAS($cas_login_url, ent_url($appId));
}

function exportApp($app, $appId, $isGuest) {
  return array("description" => $app['description'],
	       "text" => $app['text'],
	       "url" => get_url($app, $appId, $isGuest));
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
	    <a title='Se déconnecter et sortir du portail' href='<%%logout_url%%>'>
	      <span>Déconnexion</span>
	    </a>
	  </li>
	</ul>
      </div>

      <span class='portalPageBarLogout'>
	<a title='Se déconnecter et sortir du portail' href='<%%logout_url%%>'>
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

  <div class='portalLogo'>
    <a target='_blank' title='Universite Paris 1 Pantheon-Sorbonne: Accueil' href='http://www.univ-paris1.fr/'>
      <img src='%s' />
    </a>
  </div>
  <div class='portalTitleUP1'>
      <a title='Aller à l&#39;accueil' href='%s'>
	<img src='%s' />
      </a>
  </div>
EOD;

  global $ent_base_url;
  $portalPageBarLinks = $person ? computeBandeauHeaderLinks($person) : computeBandeauHeaderLinksAnonymous();
  $accueil_url = $ent_base_url . '/render.userLayoutRootNode.uP?uP_root=root&amp;uP_sparam=activeTab&amp;activeTab=1';
  $portal_logo = $ent_base_url . '/media/skins/universality/uportal3/images/portal_logo_slim.png';
  $ent_logo = $ent_base_url . '/media/skins/universality/uportal3/images/background-bandeau-slim-ENT.png';

  return sprintf($s, $portalPageBarLinks, $portal_logo, $accueil_url, $ent_logo);
}

$request_start_time = microtime(true);
initPhpCAS($cas_host, '443', $cas_context, $CA_certificate_file);
$uid = checkAuthentication() ? get_uid() : '';
$person = $uid ? getLdapInfo("uid=$uid") : array();

$layout = computeLayout($person);
$bandeauHeader = computeBandeauHeader($person);
$exportApps = exportApps(!$person);


debug_msg("request time: " . formattedElapsedTime($request_start_time));

header('Content-type: application/javascript; charset=utf8');
echo "$debug_msgs\n";
echo "(function () {\n\n";
echo "var CAS_LOGIN_URL = " . json_encode($cas_login_url) . ";\n\n";
echo "var BANDEAU_ENT_URL = " . json_encode($bandeau_ENT_url) . ";\n\n";
echo "var ENT_LOGOUT_URL = " . json_encode($ent_base_url . '/Logout') . ";\n\n";
echo "var PERSON = " . ($person ? json_encode($person) : "{}") . ";\n\n";
echo "var BANDEAU_HEADER = " . json_encode($bandeauHeader) . ";\n\n";
echo "var APPS = " . json_encode($exportApps) . ";\n\n";
echo "var LAYOUT = " . json_encode($layout) . ";\n\n";
readfile('bandeau-ENT-static.js');
echo "}())\n";

?>
