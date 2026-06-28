(function () {
    "use strict";

    var script = document.currentScript;
    var measurementId = script ? script.getAttribute("data-ga-id") || "" : "";

    if (measurementId === "") {
        return;
    }

    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function () {
        window.dataLayer.push(arguments);
    };

    window.gtag("js", new Date());
    window.gtag("config", measurementId);
})();
