/* static code below */

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
  return CAS_LOGIN_URL + "?service=" + encodeURIComponent(url);
}

function computeHeader() {
    var logout_url = BANDEAU_ENT_URL + '/logout.php?service=' + encodeURIComponent(ENT_LOGOUT_URL);
    return replaceAll(BANDEAU_HEADER, "<%logout_url%>", logout_url);
}

function computeLink(app) {
    var a = "<a title='" + escapeQuotes(app.description) + "' href='" + app.url + "'>" + escapeQuotes(app.text) + "</a>";
    return "<li>" + a + "</li>";
}

function computeMenu(currentApp) {
    var li_list = simpleMap(LAYOUT, function (tab) {
	var sub_li_list = simpleMap(tab.apps, function(appId) {
	    return computeLink(APPS[appId]);
	});
    
	var className = simpleContains(tab.apps, currentApp) ? "activeTab" : "inactiveTab";
	return "<li class='" + className + "'><span>" + escapeQuotes(tab.title) + "</span><ul>" + sub_li_list.join("\n") + "</ul></li>";
    });
    return "<ul class='bandeau_ENT_Menu'>\n" + li_list.join("\n") + "\n</ul>";
}

function set_div_innerHTML(content) {
    var div_id = window.bandeau_ENT.div_id || (window.bandeau_ENT.div_is_uid && PERSON.uid) || 'bandeau_ENT';
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

function specificCssHtml() {
    if (window['cssToLoadIfInsideIframe']) {
	var v = window['cssToLoadIfInsideIframe'];
	if (typeof v === "string")
	    return "<link rel='stylesheet' href='" + v + "' type='text/css' ></link>"
    }
    return '';

}



var currentApp = window.bandeau_ENT.current;

if (currentApp == "redirect-first" && LAYOUT && LAYOUT[0]) {
    document.location.href = APPS[LAYOUT[0].apps[0]].url;
} else {
    bandeauCss = "<link rel='stylesheet' href='" + BANDEAU_ENT_URL + "/bandeau-ENT.css' type='text/css' > </link>";
    specificCss = specificCssHtml();
    header = computeHeader();
    menu = computeMenu(currentApp);
    clear = "<p style='clear: both;'></p>";
    // NB: bandeauCss loaded AFTER the <div> for IE8
    content = bandeauCss + specificCss + "\n\n<div class='bandeau_ENT_Inner focused'>" + header + menu + clear + "</div>" + "\n\n" + bandeauCss;
    set_div_innerHTML(content);

    document.getElementById('portalPageBarAccount').onclick = bandeau_ENT_toggleOpen;

}
