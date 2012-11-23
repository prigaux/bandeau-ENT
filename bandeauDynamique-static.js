/* static code below */

function toggleClass(elt, classToToggle) {
    var regex = new RegExp(classToToggle, 'g');
       
    var without = elt.className.replace(regex , '');
    if (elt.className === without)
        elt.className += ' ' + classToToggle;
    else
        elt.className = without;
}

function bandeauDynamiqueToggleOpen() {
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

function addslashes(s) {
    var str = s;
    str=str.replace(/\\/g,'\\\\');
    str=str.replace(/\'/g,'\\\'');
    str=str.replace(/\"/g,'\\"');
    str=str.replace(/\0/g,'\\0');
    return str;
}

function via_CAS(url) {
  return CAS_LOGIN_URL + "?service=" + encodeURIComponent(url);
}

function computeLink(app) {
    var a = "<a title='" + addslashes(app.description) + "' href='" + app.url + "'>" + addslashes(app.text) + "</a>";
    return "<li>" + a + "</li>";
}

function computeMenu(currentApp) {
    var li_list = simpleMap(LAYOUT, function (tab) {
	var sub_li_list = simpleMap(tab.apps, function(appId) {
	    return computeLink(APPS[appId]);
	});
    
	var className = simpleContains(tab.apps, currentApp) ? "activeTab" : "inactiveTab";
	return "<li class='" + className + "'><span>" + addslashes(tab.title) + "</span><ul>" + sub_li_list.join("\n") + "</ul></li>";
    });
    return "<ul class='bandeauDynamiqueMenu'>\n" + li_list.join("\n") + "\n</ul>";
}

function set_div_innerHTML(content) {
    var div_id = window.bandeauDynamique.div_id || 'bandeauDynamique';
    var elt = document.getElementById(div_id);
    if (!elt) {
	elt = document.createElement("div");
	elt.setAttribute("id", div_id);
	document.body.insertBefore(elt, document.body.firstChild);
    }
    elt.innerHTML = content;
}


var currentApp = window.bandeauDynamique.current;

if (currentApp == "redirect-first" && LAYOUT && LAYOUT[0]) {
    set_div_innerHTML("document.location.href = '" + LAYOUT[0].title + "'");
    document.location.href = APPS[LAYOUT[0].apps[0]].url;
} else if (!PERSON.uid && 0) {
    set_div_innerHTML("<a href='" + via_CAS(APPS[currentApp].url) + "'>Connection</a>");
} else {
    css = "<link rel='stylesheet' href='" + BANDEAU_DYNAMIQUE_URL + "/bandeauDynamique.css' type='text/css' />";
    menu = computeMenu(currentApp);
    clear = "<div style='clear: both; height: 1em'></div>";
    content = css + "\n\n<div class='bandeauDynamiqueInner focused'>" + BANDEAU_HEADER + menu + clear + "</div>";
    set_div_innerHTML(content);

    document.getElementById('portalPageBarAccount').onclick = bandeauDynamiqueToggleOpen;

}
