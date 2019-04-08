/**
 * Created by Eyvazoff on 13/2/2018.
 */
function detectmob() {
    return !!(navigator.userAgent.match(/Android/i)
        || navigator.userAgent.match(/webOS/i)
        || navigator.userAgent.match(/iPhone/i)
        || navigator.userAgent.match(/iPad/i)
        || navigator.userAgent.match(/iPod/i)
        || navigator.userAgent.match(/BlackBerry/i)
        || navigator.userAgent.match(/Windows Phone/i))
        && (window.innerWidth <= 768);
}

function disableScroll() {
    $("body,html").css("overflow", "hidden");
}

function enableScroll() {
    $("body,html").css("overflow", "auto");
}


function tri() {
    var body = $("html, body");
    var h = $("#home-slider").height();
    body.stop().animate({scrollTop: h + 'px'}, 400, 'swing');
}

function backTop() {
    var body = $("html, body");
    body.stop().animate({scrollTop: 0}, 400, 'swing');
}

function getGlobalSpecialRequest(t) {
    $(t).hide();
    $(".special-requests-text").show();
}
