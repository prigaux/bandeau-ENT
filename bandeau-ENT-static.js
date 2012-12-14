/* static code below */

var mylog = function() {};
if (window['console'] !== undefined) { mylog = function(s) { console.log(s); }; } 

function head() {
    return document.getElementsByTagName("head")[0];
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
        return document.querySelectorAll(selector);

    // IE
    window.__qsaels = [];
    var style = addCSS(selector + "{x:expression(window.__qsaels.push(this))}");
    window.scrollBy(0, 0); // force evaluation
    head().removeChild(style);
    return window.__qsaels;
}

function simpleQuerySelector(selector) {
    if (document.querySelector) 
        return document.querySelector(selector);
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

function onReady_rec(f) {
    if (document.body)
	f();
    else
	setTimeout(function () { onReady_rec(f) }, 9);
}

function onReady(f) {
    if (document.body)
	f();
    else if (document.addEventListener)
	document.addEventListener('DOMContentLoaded', f);
    else 
	onReady_rec(content);
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
    if (window.localStorage) localStorage.setItem("bandeau_ENT_menuClosed", b ? "true" : "false");

    return false;
}

function installToggleMenu(hide) {
    var hideByDefault = window.bandeau_ENT.hide_menu;
    var toggleMenu = document.getElementById('bandeau_ENT_portalPageBarToggleMenu');
    if (toggleMenu) {
	toggleMenu.onclick = bandeau_ENT_Menu_toggleAndStore;
	var savedState = window.localStorage && localStorage.getItem("bandeau_ENT_menuClosed");
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
	return "<li class='" + className + "'><span>" + escapeQuotes(tab.title) + "</span><ul>" + sub_li_list.join("\n") + "</ul></li>";
    });

    var toggleMenuSpacer = "<div class='toggleMenuSpacer'></div>\n";

    return "<ul class='bandeau_ENT_Menu'>\n" + toggleMenuSpacer + li_list.join("\n") + "\n</ul>";
}

function computeTitlebar(currentApp) {
    var app = DATA.apps[currentApp];
    if (app && app.title && !window.bandeau_ENT.no_titlebar)
	return "<div class='bandeau_ENT_Titlebar'>" + escapeQuotes(app.title) + "</div>";
    else
	return '';
}
function bandeau_div_id() {
    window.bandeau_ENT.div_id || (window.bandeau_ENT.div_is_uid && DATA.person.uid) || 'bandeau_ENT';
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
    mylog("installBandeau (time=" + DATA.time + ", wasPreviouslyAuthenticated=" + DATA.wasPreviouslyAuthenticated + ")");

    loadSpecificCss();

    if (typeof CSS != 'undefined') 
	addCSS(CSS.base);
    else
	loadCSS(CONF.bandeau_ENT_url + "/bandeau-ENT.css");

    var widthForNiceMenu = 800;
    var conditionForNiceMenu = '(min-width: ' + widthForNiceMenu + 'px)';
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
    var titlebar = computeTitlebar(currentApp);
    var clear = "<p style='clear: both; height: 13px; margin: 0'></p>";
    var ent_title_in_header = "<div class='bandeau_ENT_ent_title_in_header'>Environnement num&eacute;rique de travail</div>";
    var titlebar_in_header = "<div class='bandeau_ENT_titlebar_in_header'>" + titlebar + "</div>";
    var menu_and_titlebar = "<div class='bandeau_ENT_Menu_and_titlebar'>" + menu + clear + titlebar + "</div>";
    var bandeau_html = "\n\n<div id='bandeau_ENT_Inner' class='focused menuOpen'>" + header + ent_title_in_header + titlebar_in_header + menu_and_titlebar + "</div>" + "\n\n";
    onReady(function () { 
	set_div_innerHTML(bandeau_div_id(), bandeau_html);

	var barAccount = document.getElementById('portalPageBarAccount');
	if (barAccount) barAccount.onclick = bandeau_ENT_toggleOpen;

	if (logout_DOM_elt()) installLogout();
	installToggleMenu(smallMenu);

	if (smallMenu && document.body.scrollTop === 0) {
	    var bandeau = document.getElementById(bandeau_div_id());

	    setTimeout(function() { 
		mylog("scrolling to " + bandeau.clientHeight);
		window.scrollTo(0, bandeau.clientHeight); 
	    }, 0);
	}
    });

}

function mayInstallBandeau() {
    if (window.bandeau_ENT.prevHash !== DATA.hash) {
	window.bandeau_ENT.prevHash = DATA.hash;
	installBandeau();
    }
}

function update() {
    mylog("updating bandeau");
    loadScript(CONF.bandeau_ENT_url + "/bandeau-ENT-js.php?noCache=1");
}

function mayInstallAndMayUpdate() {
    mayInstallBandeau();
    if (DATA.is_old) update();
}

/*var loadTime = now();*/
var currentApp = window.bandeau_ENT.current;

if (currentApp == "redirect-first" && DATA.layout && DATA.layout[0]) {
    document.location.href = DATA.apps[DATA.layout[0].apps[0]].url;
} else if (!DATA.person.uid || window.bandeau_ENT.logout && !logout_DOM_elt()) {
    // disabled for now
} else {
    mayInstallAndMayUpdate();
}
