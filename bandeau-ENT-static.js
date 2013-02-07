/* static code below */

var mylog = function() {};
if (window['console'] !== undefined) { mylog = function(s) { console.log(s); }; } 

function head() {
    return document.getElementsByTagName("head")[0];
}

function now() {
    return Math.round(new Date().getTime() / 1000);
}

/* return true if class has been added */
function toggleClass(elt, classToToggle) {
    var regex = new RegExp(classToToggle, 'g');
       
    var without = elt.className.replace(regex , '');
    if (elt.className === without) {
        elt.className += ' ' + classToToggle;
	return true;
    } else {
        elt.className = without;
	return false;
    }
}

function simpleQuerySelectorAll(selector) {
    if (document.querySelectorAll) 
	try {
            return document.querySelectorAll(selector);
	} catch (err) {
	    return [];
	}

    // IE
    window.__qsaels = [];
    var style = addCSS(selector + "{x:expression(window.__qsaels.push(this))}");
    window.scrollBy(0, 0); // force evaluation
    head().removeChild(style);
    return window.__qsaels;
}

function simpleQuerySelector(selector) {
    if (document.querySelector) 
	try {
            return document.querySelector(selector);
	} catch (err) {
	    return null;
	}
    else
	return simpleQuerySelectorAll(selector)[0];
}

function simpleContains(a, val) {
    var len = a.length;
    for(var i = 0; i < len; i++) {
        if(a[i] == val) return true;
    }
    return false;
}

function simpleEach(a, fn) {
    var len = a.length;
    for(var i = 0; i < len; i++) {
	fn(a[i], i, a);
    }
}

function simpleFilter(a, fn) {
    var r = [];
    var len = a.length;
    for(var i = 0; i < len; i++) {
	if (fn(a[i])) r.push(a[i]);
    }
    return r;
}

function simpleMap(a, fn) {
    var r = [];
    var len = a.length;
    for(var i = 0; i < len; i++) {
	r.push(fn(a[i]));
    }
    return r;
}

function escapeQuotes(s) {
    var str = s;
    str=str.replace(/\'/g,'&#39;');
    str=str.replace(/\"/g,'&quot;');
    return str;
}

function replaceAll(s, target, replacement) {
    return s.split(target).join(replacement);
}

function onIdOrBody_rec(id, f) {
    if (id && document.getElementById(id) || document.body)
	f();
    else
	setTimeout(function () { onIdOrBody_rec(id, f) }, 9);
}

function onIdOrBody(id, f) {
    if (id && document.getElementById(id) || document.body) {
	f();
    } else if (document.addEventListener) {
	document.addEventListener('DOMContentLoaded', f);
    } else 
	onIdOrBody_rec(id, f);
}

function onReady_rec(f) {
    if (document.attachEvent ? document.readyState === "complete" : document.readyState !== "loading")
	f();
    else
	setTimeout(function () { onReady_rec(f) }, 9);
}

function onReady(f) {
    // IE10 and lower don't handle "interactive" properly
    if (document.attachEvent ? document.readyState === "complete" : document.readyState !== "loading") {
	f();
    } else if (document.addEventListener) {
	document.addEventListener('DOMContentLoaded', f);
    } else 
	onReady_rec(f);
}

function bandeau_ENT_toggleOpen() {
    toggleClass(document.getElementById('portalPageBarAccount'), 'open');
    toggleClass(document.getElementById('portalPageBarAccountInner'), 'open');

  /* TODO
    if ($('#portalPageBarAccount').hasClass('open')) {
  $(document).one('click', function () {
    $('#portalPageBarAccount').removeClass('open');
    $('#portalPageBarAccountInner').removeClass('open');
    return true;
  });
  } */

    return false;
}

function bandeau_ENT_Menu_toggle() {
    return toggleClass(document.getElementById('bandeau_ENT_Inner'), 'menuClosed');
}

function bandeau_ENT_Menu_toggleAndStore() {
    var b = bandeau_ENT_Menu_toggle();
    if (window.localStorage) localStorageSet("menuClosed", b ? "true" : "false");

    return false;
}

function installToggleMenu(hide) {
    var hideByDefault = window.bandeau_ENT.hide_menu;
    var toggleMenu = document.getElementById('bandeau_ENT_portalPageBarToggleMenu');
    if (toggleMenu) {
	toggleMenu.onclick = bandeau_ENT_Menu_toggleAndStore;
	var savedState = window.localStorage && localStorageGet("menuClosed");
	if (hide || savedState === "true" || savedState !== "false" && hideByDefault)
	    bandeau_ENT_Menu_toggle();
    }
}

function via_CAS(url) {
  return CONF.cas_login_url + "?service=" + encodeURIComponent(url);
}

function computeHeader() {
    var app_logout_url = CONF.ent_logout_url;
    var logout_url = CONF.bandeau_ENT_url + '/logout.php?service=' + encodeURIComponent(app_logout_url);
    return replaceAll(DATA.bandeauHeader, "<%logout_url%>", logout_url);
}

function computeLink(app) {
    var a = "<a title='" + escapeQuotes(app.description) + "' href='" + app.url + "'>" + escapeQuotes(app.text) + "</a>";
    return "<li>" + a + "</li>";
}

function computeMenu(currentApp) {
    var li_list = simpleMap(DATA.layout, function (tab) {
	var sub_li_list = simpleMap(tab.apps, function(appId) {
	    return computeLink(DATA.apps[appId]);
	});
    
	var className = simpleContains(tab.apps, currentApp) ? "activeTab" : "inactiveTab";
	return "<li class='" + className + "' onclick=''><span>" + escapeQuotes(tab.title) + "</span><ul>" + sub_li_list.join("\n") + "</ul></li>";
    });

    var toggleMenuSpacer = "<div class='toggleMenuSpacer'></div>\n";

    return "<ul class='bandeau_ENT_Menu'>\n" + toggleMenuSpacer + li_list.join("\n") + "\n</ul>";
}

function computeHelp(currentApp) {
    var app = DATA.apps[currentApp];
    if (app && app.hashelp) {
	var href = "http://esup-data.univ-paris1.fr/esup/aide/canal/" + currentApp + ".html";
	var onclick = "window.open('','form_help','toolbar=no,location=no,directories=no,status=no,menubar=no,resizable=yes,scrollbars=yes,copyhistory=no,alwaysRaised,width=600,height=400')";
	var a = "<a href='"+  href + "' onclick=\"" + onclick + "\" target='form_help' title=\"Voir l'aide du canal\"><span>Aide</span></a>";
	return "<div class='bandeau_ENT_Help'>" + a + "</div>";
    } else {
	return '';
    }
}

function computeTitlebar(currentApp) {
    var app = DATA.apps[currentApp];
    if (app && app.title && !window.bandeau_ENT.no_titlebar)
	return "<div class='bandeau_ENT_Titlebar'>" + escapeQuotes(app.title) + "</div>";
    else
	return '';
}
function bandeau_div_id() {
    return window.bandeau_ENT.div_id || (window.bandeau_ENT.div_is_uid && DATA.person.uid) || 'bandeau_ENT';
}
function set_div_innerHTML(div_id, content) {
    var elt = document.getElementById(div_id);
    if (!elt) {
	elt = document.createElement("div");
	elt.setAttribute("id", div_id);
	document.body.insertBefore(elt, document.body.firstChild);
    }
    elt.innerHTML = content;
}

function loadCSS (url, media) {
    var elt = document.createElement("link");
    elt.setAttribute("rel", "stylesheet");
    elt.setAttribute("type", "text/css");
    elt.setAttribute("href", url);
    if (media) elt.setAttribute("media", media);
    head().appendChild(elt);
};

function addCSS(css) {
    var elt = document.createElement('style');
    elt.setAttribute("type", 'text/css');
    if (elt.styleSheet)
	elt.styleSheet.cssText = css;
    else
	elt.appendChild(document.createTextNode(css));
    head().appendChild(elt);
    return elt;
}

function loadScript (url) {
    var elt = document.createElement("script");
    elt.setAttribute("type", "text/javascript");
    elt.setAttribute("src", url);
    elt.setAttribute("async", "async");
    head().appendChild(elt);
}

function loadSpecificCss() {
    if (window['cssToLoadIfInsideIframe']) {
	var v = window['cssToLoadIfInsideIframe'];
	if (typeof v === "string")
	    loadCSS(v);
    }

}

function logout_DOM_elt() {
    return window.bandeau_ENT.logout && simpleQuerySelector(window.bandeau_ENT.logout);
}

function asyncLogout() {
    loadScript(CONF.bandeau_ENT_url + '/logout.php?callback=window.bandeau_ENT.onAsyncLogout');
    return false;
}
window.bandeau_ENT.onAsyncLogout = function() {
    var elt = logout_DOM_elt();
    if (elt.href)
	document.location = elt.href;
    else if (elt.tagName === "FORM")
	elt.submit();
}
function installLogout() {
    var logout_buttons = "#bandeau_ENT_Inner .portalPageBarLogout, #bandeau_ENT_Inner .portalPageBarAccountLogout";
    simpleEach(simpleQuerySelectorAll(logout_buttons),
	       function (elt) { 
		   elt.onclick = asyncLogout;
	       });
}

function installBandeau() {
    mylog("installBandeau");

    loadSpecificCss();

    if (typeof CSS != 'undefined') 
	addCSS(CSS.base);
    else
	loadCSS(CONF.bandeau_ENT_url + "/bandeau-ENT.css");

    var widthForNiceMenu = 800;
    // testing min-width is not enough: in case of a non-mobile comptabile page, the width will be big.
    // also testing min-device-width will help
    var conditionForNiceMenu = '(min-width: ' + widthForNiceMenu + 'px) and (min-device-width: ' + widthForNiceMenu + 'px)';
    var smallMenu = window.matchMedia ? !window.matchMedia(conditionForNiceMenu).matches : screen.width < widthForNiceMenu;
    if (!smallMenu) {
	// on IE7&IE8, we do want to include the desktop CSS
	// but since media queries fail, we need to give them a simpler media
	var handleMediaQuery = "getElementsByClassName" in document; // not having getElementsByClassName is a good sign of not having media queries... (IE7 and IE8)
	var condition = handleMediaQuery ? conditionForNiceMenu : 'screen';

	if (typeof CSS != 'undefined') 
	    addCSS("@media " + condition + " { \n" + CSS.desktop + "}\n");
	else
	    loadCSS(CONF.bandeau_ENT_url + "/bandeau-ENT-desktop.css", condition);
    }

    var header = computeHeader();
    var menu = computeMenu(currentApp);
    var help = computeHelp(currentApp);
    var titlebar = computeTitlebar(currentApp);
    var clear = "<p style='clear: both; height: 13px; margin: 0'></p>";
    var ent_title_in_header = "<div class='bandeau_ENT_ent_title_in_header'><span>Environnement num&eacute;rique de travail</span></div>";
    var titlebar_in_header = "<div class='bandeau_ENT_titlebar_in_header'>" + titlebar + "</div>";
    var menu_and_titlebar = "<div class='bandeau_ENT_Menu_and_titlebar'>" + menu + clear + titlebar + "</div>";
    var bandeau_html = "\n\n<div id='bandeau_ENT_Inner' class='menuOpen'>" + header + ent_title_in_header + titlebar_in_header + menu_and_titlebar + help + "</div>" + "\n\n";
    onIdOrBody(bandeau_div_id(), function () { 
	set_div_innerHTML(bandeau_div_id(), bandeau_html);

	var barAccount = document.getElementById('portalPageBarAccount');
	if (barAccount) barAccount.onclick = bandeau_ENT_toggleOpen;

	onReady(function () {
	    if (logout_DOM_elt()) installLogout();
	});
	installToggleMenu(smallMenu);

	if (smallMenu && document.body.scrollTop === 0) {
	    var bandeau = document.getElementById(bandeau_div_id());

	    setTimeout(function() { 
		mylog("scrolling to " + bandeau.clientHeight);
		window.scrollTo(0, bandeau.clientHeight); 
	    }, 0);
	}
	if (window.bandeau_ENT.quirks && simpleContains(window.bandeau_ENT.quirks, 'window-resize'))
	     setTimeout(triggerWindowResize, 0);
    });

}

function triggerWindowResize() {
    var evt = document.createEvent('UIEvents');
    evt.initUIEvent('resize', true, false,window,0);
    window.dispatchEvent(evt);
}

function mayInstallBandeau() {
    if (window.bandeau_ENT.prevHash !== PARAMS.hash) {
	window.bandeau_ENT.prevHash = PARAMS.hash;
	installBandeau();
    }
}

function localStorageGet(field) {
    try {
	return localStorage.getItem(window.bandeau_ENT.localStorage_prefix + field);
    } catch (err) {
	return null;
    }
}
function localStorageSet(field, value) {
    try {
	localStorage.setItem(window.bandeau_ENT.localStorage_prefix + field, value);
    } catch (err) {}
}
function setLocalStorageCache() {
    localStorageSet(window.bandeau_ENT.localStorage_js_text_field, window.bandeau_ENT.js_text);
    localStorageSet("url", window.bandeau_ENT.url);
    localStorageSet("time", now());
}

function loadBandeauJs(params) {
    if (PARAMS.PHPSESSID && params == '')
	params = "PHPSESSID=" + PARAMS.PHPSESSID;
    loadScript(window.bandeau_ENT.url + "/bandeau-ENT-js.php" + (params ? "?" + params : ''));
}

function detectReload($time) {
    var $prev = localStorageGet('detectReload');
    if ($prev && $prev != $time) {
	mylog("reload detected, updating bandeau softly");
	loadBandeauJs('');
    }
    localStorageSet('detectReload', $time);
}

function mayUpdate() {
    if (notFromLocalStorage) {
	if (window.localStorage) {
	    mylog("caching bandeau in localStorage (" + window.bandeau_ENT.localStorage_prefix + " " + window.bandeau_ENT.localStorage_js_text_field + ")");
	    setLocalStorageCache();
	}
	if (PARAMS.is_old) {
	    mylog("server said bandeau is old, forcing full bandeau update");
	    loadBandeauJs('noCache=1');
	}
    } else {
	var age = now() - localStorageGet("time");
	if (age > CONF.time_before_checking_browser_cache_is_up_to_date) {
	    mylog("cached bandeau is old (" + age + "s), updating it softly");
	    loadBandeauJs('');
	} else {
	    // if user used "reload", the cached version of detectReload.php will change
	    window.bandeau_ENT.detectReload = detectReload;
	    loadScript(CONF.bandeau_ENT_url + "/detectReload.php");
	}
    }
}

/*var loadTime = now();*/
var currentApp = window.bandeau_ENT.current;
var notFromLocalStorage = window.bandeau_ENT.notFromLocalStorage;
window.bandeau_ENT.notFromLocalStorage = false;

if (!window.bandeau_ENT.localStorage_prefix)
    window.bandeau_ENT.localStorage_prefix = 'bandeau_ENT_';
// for old bandeau-ENT-loader.js which did not set localStorage_js_text_field:
if (!window.bandeau_ENT.localStorage_js_text_field)
    window.bandeau_ENT.localStorage_js_text_field = 'js_text';
// for old bandeau-ENT-loader.js which did not set window.bandeau_ENT.url:
if (!window.bandeau_ENT.url)
    window.bandeau_ENT.url = CONF.bandeau_ENT_url;

if (!notFromLocalStorage && window.bandeau_ENT.url !== localStorageGet('url')) {
    mylog("not using bandeau from localStorage which was computed for " + localStorageGet('url') + " whereas " + window.bandeau_ENT.url + " is wanted");
    return "invalid";
} else if (currentApp == "redirect-first" && DATA.layout && DATA.layout[0]) {
    document.location.href = DATA.apps[DATA.layout[0].apps[0]].url;
} else if (!DATA.person.uid) {
    // disabled for now

    if (notFromLocalStorage) {
	onIdOrBody(bandeau_div_id(), function () { 
	    set_div_innerHTML(bandeau_div_id(), '');
	});
	if (window.localStorage) {
	    mylog("removing cached bandeau from localStorage");
	    localStorageSet(window.bandeau_ENT.localStorage_js_text_field, '');
	}
    } else {
	// checking wether we are logged in now
	loadBandeauJs('');
    }
} else if (window.bandeau_ENT.logout && !logout_DOM_elt()) {
    onReady(function () {
	    if (logout_DOM_elt()) mayInstallBandeau();
	    mayUpdate();
    });
} else {
    mayInstallBandeau();
    mayUpdate();
}

// things seem ok, cached js_text can be kept
return "OK";
