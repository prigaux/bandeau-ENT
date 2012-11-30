/* static code below */

var mylog = function() {};
if (window['console'] !== undefined) { mylog = function(s) { console.log(s); }; } 

function toggleClass(elt, classToToggle) {
    var regex = new RegExp(classToToggle, 'g');
       
    var without = elt.className.replace(regex , '');
    if (elt.className === without)
        elt.className += ' ' + classToToggle;
    else
        elt.className = without;
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

function via_CAS(url) {
  return CONF.cas_login_url + "?service=" + encodeURIComponent(url);
}

function computeHeader() {
    var app_logout_url = CONF.ent_logout_url;
    if (window.bandeau_ENT.logout_a_id)
	app_logout_url = document.getElementById(window.bandeau_ENT.logout_a_id).href;
    else if (window.bandeau_ENT.logout_url)
	app_logout_url = window.bandeau_ENT.logout_url;
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
    return "<ul class='bandeau_ENT_Menu'>\n" + li_list.join("\n") + "\n</ul>";
}

function set_div_innerHTML(content) {
    var div_id = window.bandeau_ENT.div_id || (window.bandeau_ENT.div_is_uid && DATA.person.uid) || 'bandeau_ENT';
    var elt = document.getElementById(div_id);
    if (!elt) {
	elt = document.createElement("div");
	elt.setAttribute("id", div_id);
	document.body.insertBefore(elt, document.body.firstChild);
    }
    elt.innerHTML = content;
}

function loadCSS (url) {
    var elt = document.createElement("link");
    elt.setAttribute("rel", "stylesheet");
    elt.setAttribute("type", "text/css");
    elt.setAttribute("href", url);
    document.getElementsByTagName("head")[0].appendChild(elt);
};

function loadScript (url) {
    var elt = document.createElement("script");
    elt.setAttribute("type", "text/javascript");
    elt.setAttribute("src", url);
    elt.setAttribute("async", "async");
    document.getElementsByTagName("head")[0].appendChild(elt);
}

function loadSpecificCss() {
    if (window['cssToLoadIfInsideIframe']) {
	var v = window['cssToLoadIfInsideIframe'];
	if (typeof v === "string")
	    loadCSS(v);
    }

}

function installBandeau() {
    mylog("installBandeau (time=" + DATA.time + ", wasPreviouslyAuthenticated=" + DATA.wasPreviouslyAuthenticated + ")");

    loadCSS(CONF.bandeau_ENT_url + "/bandeau-ENT.css");
    loadSpecificCss();

    var header = computeHeader();
    var menu = computeMenu(currentApp);
    var clear = "<p style='clear: both;'></p>";
    var content = "\n\n<div class='bandeau_ENT_Inner focused'>" + header + menu + clear + "</div>" + "\n\n";
    onReady(function() { 
	set_div_innerHTML(content);

	var barAccount = document.getElementById('portalPageBarAccount');
	if (barAccount) barAccount.onclick = bandeau_ENT_toggleOpen;
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
} else if (!DATA.person.uid) {
    // disabled for now
} else {
    mayInstallAndMayUpdate();
}
