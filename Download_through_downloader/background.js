// fix for *special* browsers
browser = (function () {
	return window.msBrowser ||
		window.browser ||
		window.chrome ||
		null;
})();

function notyf_setup_inpage__iggwrurefj() {

	if (!window.notyf_iggwrurefj) {
		if (!window.Notyf) {
			// notyf.min.js follows
			!function () {
				function n(n, t) {
					for (property in t) t.hasOwnProperty(property) && (n[property] = t[property]);
					return n
				}

				function t(n, t) {
					var e = document.createElement("div");
					e.className = "notyf__toast";
					var o = document.createElement("div");
					o.className = "notyf__wrapper";
					var i = document.createElement("div");
					i.className = "notyf__icon";
					var a = document.createElement("i");
					a.className = t;
					var r = document.createElement("div");
					r.className = "notyf__message", r.innerHTML = n, i.appendChild(a), o.appendChild(i), o.appendChild(r), e.appendChild(o);
					var c = this;
					return setTimeout(function () {
						e.className += " notyf--disappear", e.addEventListener(c.animationEnd, function (n) {
							n.target == e && c.container.removeChild(e)
						});
						var n = c.notifications.indexOf(e);
						c.notifications.splice(n, 1)
					}, c.options.delay), e
				}

				function e() {
					var n, t = document.createElement("fake"), e = {
						transition: "animationend",
						OTransition: "oAnimationEnd",
						MozTransition: "animationend",
						WebkitTransition: "webkitAnimationEnd"
					};
					for (n in e) if (void 0 !== t.style[n]) return e[n]
				}

				this.Notyf = function () {
					this.notifications = [];
					var t = {delay: 2e3, alertIcon: "notyf__icon--alert", confirmIcon: "notyf__icon--confirm", infoIcon: "notyf__icon--info"};
					arguments[0] && "object" == typeof arguments[0] ? this.options = n(t, arguments[0]) : this.options = t;
					var o = document.createDocumentFragment(), i = document.createElement("div");
					i.className = "notyf", o.appendChild(i), document.body.appendChild(o), this.container = i, this.animationEnd = e()
				}, this.Notyf.prototype.alert = function (n) {
					var e = t.call(this, n, this.options.alertIcon);
					e.className += " notyf--alert", this.container.appendChild(e), this.notifications.push(e)
				}, this.Notyf.prototype.info = function (n) {
					var e = t.call(this, n, this.options.infoIcon);
					e.className += " notyf--info", this.container.appendChild(e), this.notifications.push(e)
				}, this.Notyf.prototype.confirm = function (n) {
					var e = t.call(this, n, this.options.confirmIcon);
					e.className += " notyf--confirm", this.container.appendChild(e), this.notifications.push(e)
				}
			}(), function () {
				"function" == typeof define && define.amd ? define("Notyf", function () {
					return Notyf
				}) : "undefined" != typeof module && module.exports ? module.exports = Notyf : window.Notyf = Notyf
			}();
		}
		window.notyf_iggwrurefj = new Notyf({delay: 15000});
	}
	return window.notyf_iggwrurefj;
}

function xhr_call_inpage__iggwrurefj(url, loadend) {
	var xhr = new XMLHttpRequest();
	xhr.overrideMimeType("application/json");
	xhr.addEventListener("loadend", loadend);
	xhr.open('GET', url);
	xhr.send();
}

function xhr_call_inpage_result__iggwrurefj(event, notyf, requestedUrl, link, texts, isBlank, finalCallback) {
	var linkify = function (text) {
		return '<a href="' + link + '"' + (isBlank ? ' target="_blank"' : '') + '>' + text + '</a>';
	};
	if (!event.target) {
		notyf.alert(linkify(texts['messageResponseFailed'] + requestedUrl));
	} else if (event.target.readyState === 4) {
		var success = false;
		var response = null;
		try {
			response = JSON.parse(event.target.responseText);
			if (response.added > 0) {
				for (var c = 0; c < response.addedTitles.length; c++) {
					notyf.confirm(linkify(texts['messageResponseSuccess'] + response.addedTitles[c]));
				}
				success = true;
			}
			if (response.skipped > 0) {
				for (var d = 0; d < response.skippedUrls.length; d++) {
					notyf.confirm(linkify(texts['messageResponseSkipped'] + response.skippedUrls[d]));
				}
			}
			if (response.errors > 0) {
				for (var f = 0; f < response.errorsUrls.length; f++) {
					notyf.alert(linkify(texts['messageResponseError'] + response.errorsUrls[f]));
				}
			}
		} catch (error) {
			notyf.alert(linkify(texts['messageResponseFailed'] + requestedUrl));
		}
		if (typeof (finalCallback) === 'function') {
			finalCallback(notyf, requestedUrl, link, response, success);
		}
	} // else we don't care
}

var texts = {};

if (typeof browser !== 'undefined' && browser) {
	texts = {
		'messageRequestStarting': browser.i18n.getMessage("messageRequestStarting"),
		'messageResponseSuccess': browser.i18n.getMessage("messageResponseSuccess"),
		'messageResponseSkipped': browser.i18n.getMessage("messageResponseSkipped"),
		'messageResponseError': browser.i18n.getMessage("messageResponseError"),
		'messageResponseFailed': browser.i18n.getMessage("messageResponseFailed")
	};
} else if (window.location.hostname === 'dl.piskvor.org') {
	xhr_call_inpage__iggwrurefj('/downloader/Download_through_downloader/_locales/cs/messages.json',
		function(event) {
			if (event.target && event.target.readyState === 4) {
				try {
					var response = JSON.parse(event.target.responseText);
					for (var prop in response) {
						if (response.hasOwnProperty(prop) && prop.indexOf('message') === 0) {
							texts[prop] = response[prop].message;
						}
					}
				} catch (error) {
					// noop
				}
			}
		}
	);
}

if (typeof browser !== 'undefined' && browser) {

	// actually sends the request out - by opening a new tab
	function osm_piskvor_org_share(e) {
		// we need to hard-code this, downloader doesn't let us configure
		const downloaderLink = "https://dl.piskvor.org/downloader/";
		const downloaderUrl = downloaderLink + "?do=add&isScript=1";

		var url;
		if (typeof(e["target"]) === "undefined") {
			url = e.toString();
		} else {
			url = e.target.getAttribute("url");
		}
		var loadUrl = url;
		if (url.indexOf(downloaderUrl) === -1) { // 80/20 try to avoid loops - calling /loadUrl=http://localhost/loadUrl
			loadUrl = downloaderUrl + "&urls=" + encodeURIComponent(url);
		}
		var insertingCSS = browser.tabs.insertCSS({file: "notyf.css"});

		browser.tabs.executeScript({
			code: "!function(){ " + notyf_setup_inpage__iggwrurefj.toString() + ";" + xhr_call_inpage__iggwrurefj.toString() + ";" + xhr_call_inpage_result__iggwrurefj.toString() + /* define the functions in page... */
			";var notyf=notyf_setup_inpage__iggwrurefj(); notyf.info(" + JSON.stringify(texts.messageRequestStarting + ' ' + url) + "); xhr_call_inpage__iggwrurefj('" + loadUrl + "',function(e){xhr_call_inpage_result__iggwrurefj(e, notyf, '" + url + "','" + downloaderLink + "'," + JSON.stringify(texts) + "), true})}();" /* ...and call them */
		});
	}

	// create the context menu item
	browser.contextMenus.create({
		id: "osm-piskvor-org-dl-remote-link",
		title: browser.i18n.getMessage("contextMenuItemOpenInDownloader"),
		contexts: ["selection", "link", "page"]
	});

	// handle context menu click
	browser.contextMenus.onClicked.addListener(function (info) {
		// we only care about our one item
		if (info.menuItemId !== "osm-piskvor-org-dl-remote-link") {
			return;
		}
		if (info["selectionText"]) {
			// if there's a selection, load that
			osm_piskvor_org_share(info.selectionText);
		} else if (info["linkUrl"]) {
			// if there's a link, load that
			osm_piskvor_org_share(info.linkUrl);
		} else {
			// otherwise load the current page URL
			osm_piskvor_org_share(info.pageUrl);
		}
	});

}
