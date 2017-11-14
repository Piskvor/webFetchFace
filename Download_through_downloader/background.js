// fix for *special* browsers
browser = (function () {
    return window.msBrowser ||
        window.browser ||
        window.chrome;
})();

function filterTabs(tabsFound) {
	var currentTab = tabsFound[0];
	var xhr = new XMLHttpRequest();
	xhr.open("GET", loadUrl);
	xhr.send();
}

// actually sends the request out - by opening a new tab
function osm_piskvor_org_share(e) {
    // we need to hard-code this, downloader doesn't let us configure
    const josmURL = "https://dl.piskvor.org/downloader/?do=add&isScript=1";

    var url;
    if (typeof(e["target"]) === "undefined") {
        url = e.toString();
    } else {
        url = e.target.getAttribute("url");
    }
    var loadUrl = url;
    if (url.indexOf(josmURL) === -1) { // 80/20 try to avoid loops - calling /loadUrl=http://localhost/loadUrl
        loadUrl = josmURL + "&urls=" + encodeURIComponent(url);
    }
    browser.tabs.executeScript({
      code: "var xhr = new XMLHttpRequest();xhr.open('GET', '" + loadUrl + "');	xhr.send()"
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
    if (info.menuItemId != "osm-piskvor-org-dl-remote-link") {
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

