(function () {
    var mylog = function() {};
    if (window['console'] !== undefined) { mylog = function(s) { console.log(s); }; } 
    //else { mylog = function(s) { alert(s); }; }

    if (parent == window) load_bandeau_ENT();

    function loadScript(url) {
	var script = document.createElement("script");
	script.setAttribute("type", "text/javascript");
	script.setAttribute("src", url);
	script.setAttribute("async", "async");
	document.getElementsByTagName("head")[0].appendChild(script);
    }
      
    function load_bandeau_ENT() {
	var url = window.bandeau_ENT.url || "https://front-test.univ-paris1.fr/bandeau-ENT/bandeau-ENT-js.php";
	try {
	    if (window.localStorage && localStorage.getItem("bandeau_ENT_js_text")) {
		mylog("loading bandeau from localStorage ");
		var val = eval(localStorage.getItem("bandeau_ENT_js_text"));
		if (val === "OK") return;
		else throw (new Error("invalid return value " + val));
	    }
	} catch (err) {
	    mylog("load_bandeau_ENT: " + err.message);
	    localStorage.setItem("bandeau_ENT_js_text", '');
	}

	loadScript(url);
    }

})();

