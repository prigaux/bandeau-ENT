<? 

$ldap_server='ldap.univ-paris1.fr ldap2.univ-paris1.fr fangorn.univ-paris1.fr';
$ou_people="ou=people,dc=univ-paris1,dc=fr";

$minimal_attrs = array('displayName', 'uid', 'mail');

include_once "config.inc.php";

$cas_login_url = "https://cas.univ-paris1.fr/cas/login";
$ent_base_url = "https://esup.univ-paris1.fr";
$bandeau_dynamique_url = "https://wsgroups.univ-paris1.fr/test-bandeau-dynamique";

$test = true;
if ($test) {
  $cas_login_url = "https://cas-test.univ-paris1.fr/cas/login";
  $ent_base_url = "https://uportal3-test.univ-paris1.fr";
  $bandeau_dynamique_url = "https://ticetest.univ-paris1.fr/test-bandeau-dynamique";

  $APPS["caccueil-pers"]["url"] = "$bandeau_dynamique_url/accueil-ent-pers.html";
  $APPS["caccueil-etu"]["url"] = "$bandeau_dynamique_url/accueil-ent-etu.html";
}

$APPS["redirect-first"] = 
    array("text" => "",
	  "description" => "",
	  "users" => array(), "groups" => array(),
	  "url" => "$bandeau_dynamique_url/ent.html");


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

function ent_url($fname) {
  global $ent_base_url;
  return $ent_base_url . "/Login?uP_fname=$fname";
}

function is_admin($uid) {
  return true;
}

function get_real_uid() {
  return isset($_SERVER["HTTP_CAS_USER"]) ? $_SERVER["HTTP_CAS_USER"] : ''; # CAS-User
}

function get_uid() {
  $uid = get_real_uid();
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


function get_url($app, $appId) {
  global $cas_login_url;
  if (isset($app['url']))
    return isset($app['force_CAS']) ? via_CAS($app['force_CAS'], $app['url']) : $app['url'];
  else
    return via_CAS($cas_login_url, ent_url($appId));
}

function exportApp($app, $appId) {
  return array("description" => $app['description'],
	       "text" => $app['text'],
	       "url" => get_url($app, $appId));
}

function exportApps() {
  global $APPS;

  $r = array();
  foreach ($APPS as $appId => $app) {
    $r[$appId] = exportApp($app, $appId);
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
	    <a title='Se déconnecter et sortir du portail' href='%s'>
	      <span>Déconnexion</span>
	    </a>
	  </li>
	</ul>
      </div>

      <span class='portalPageBarLogout'>
	<a title='Se déconnecter et sortir du portail' href='%s'>
	  <span>Déconnexion</span>
	</a>
      </span>
EOD;

  global $cas_login_url, $ent_base_url;
  $activation_url = via_CAS($cas_login_url, ent_url('CActivation'));
  $logout_url = $ent_base_url . '/Logout';
  return sprintf($s, $person["displayName"][0], $person["mail"][0], $person["uid"][0], 
		 $activation_url, $logout_url, $logout_url);
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
  $bandeau_ent_url = $ent_base_url . '/media/skins/universality/uportal3/images/background-bandeau-slim-ENT.png';

  return sprintf($s, $portalPageBarLinks, $portal_logo, $accueil_url, $bandeau_ent_url);
}

$uid = get_uid();
$person = $uid ? getLdapInfo("uid=$uid") : array();

$layout = computeLayout($person);


if (!get_real_uid()) {
  setcookie("MOD_CAS_G", "", 1, "/"); // remove mod-auth-cas gateway cookie to force asking again
}

header('Content-type: application/javascript; charset=utf8');
echo "$debug_msgs\n";
echo "(function () {\n\n";
echo "var CAS_LOGIN_URL = " . json_encode($cas_login_url) . ";\n\n";
echo "var BANDEAU_DYNAMIQUE_URL = " . json_encode($bandeau_dynamique_url) . ";\n\n";
echo "var PERSON = " . ($person ? json_encode($person) : "{}") . ";\n\n";
echo "var BANDEAU_HEADER = " . json_encode(computeBandeauHeader($person)) . ";\n\n";
echo "var APPS = " . json_encode(exportApps()) . ";\n\n";
echo "var LAYOUT = " . json_encode($layout) . ";\n\n";
readfile('bandeauDynamique-static.js');
echo "}())\n";

?>
