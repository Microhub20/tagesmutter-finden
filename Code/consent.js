/**
 * Cookie-Einwilligung + Google Analytics 4 (mein Tageskind)
 *
 * Rechtlicher Ansatz (bewusst die strenge Variante für Deutschland):
 * Google Analytics wird ERST NACH ausdrücklicher Einwilligung geladen (§ 25 Abs. 1 TDDDG,
 * Art. 6 Abs. 1 lit. a DSGVO). Vorher geht KEINE Anfrage an Google – auch kein
 * "cookieless ping". Zusätzlich setzen wir den Google-Einwilligungsmodus (Consent Mode v2)
 * auf "denied", damit die Signale auch dann korrekt sind, wenn später weitere
 * Google-Dienste dazukommen.
 *
 * "Ablehnen" ist gleichwertig gestaltet wie "Akzeptieren" – eine Einwilligung ist sonst
 * nicht freiwillig und damit unwirksam.
 *
 * Widerruf jederzeit über window.tmkCookieEinstellungen() (im Footer + Datenschutzerklärung).
 */
(function () {
  'use strict';

  var MESS_ID = 'G-TZGWE83965';
  var KEY     = 'tmk-consent';   // Wert: 'granted' | 'denied'

  // ---------- Google Consent Mode v2: Grundzustand = alles abgelehnt ----------
  window.dataLayer = window.dataLayer || [];
  function gtag() { window.dataLayer.push(arguments); }
  window.gtag = gtag;
  gtag('consent', 'default', {
    ad_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied',
    analytics_storage: 'denied',
    functionality_storage: 'granted',
    security_storage: 'granted'
  });

  // ---------- Analytics nachladen (nur nach Einwilligung) ----------
  var geladen = false;
  function analyticsLaden() {
    if (geladen) return;
    geladen = true;
    gtag('consent', 'update', { analytics_storage: 'granted' });
    var s = document.createElement('script');
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + MESS_ID;
    document.head.appendChild(s);
    gtag('js', new Date());
    gtag('config', MESS_ID, { anonymize_ip: true });
  }

  function merke(wert) { try { localStorage.setItem(KEY, wert); } catch (e) {} }
  function gemerkt()   { try { return localStorage.getItem(KEY); } catch (e) { return null; } }

  // ---------- Banner ----------
  var box = null;

  function schliessen() {
    if (!box) return;
    box.parentNode && box.parentNode.removeChild(box);
    box = null;
  }

  function entscheide(wert) {
    merke(wert);
    schliessen();
    if (wert === 'granted') analyticsLaden();
  }

  function bannerZeigen() {
    if (box) return;
    box = document.createElement('div');
    box.className = 'cc-banner';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-live', 'polite');
    box.setAttribute('aria-label', 'Hinweis zu Cookies');
    box.innerHTML =
      '<div class="cc-inner">' +
        '<div class="cc-text">' +
          '<b>Dürfen wir mitzählen?</b>' +
          '<p>Wir würden gern anonym auswerten, welche Seiten euch weiterhelfen – mit Google Analytics. ' +
          'Das ist völlig freiwillig: Die Seite funktioniert ohne Zustimmung genauso. ' +
          'Mehr dazu in der <a href="/datenschutz.html">Datenschutzerklärung</a>.</p>' +
        '</div>' +
        '<div class="cc-btns">' +
          '<button type="button" class="cc-btn cc-ablehnen">Nur Notwendige</button>' +
          '<button type="button" class="cc-btn cc-ok">Einverstanden</button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(box);
    box.querySelector('.cc-ok').addEventListener('click', function () { entscheide('granted'); });
    box.querySelector('.cc-ablehnen').addEventListener('click', function () { entscheide('denied'); });
  }

  // ---------- Widerruf / erneute Auswahl ----------
  window.tmkCookieEinstellungen = function () {
    try { localStorage.removeItem(KEY); } catch (e) {}
    // Bereits geladenes Analytics kann nicht "entladen" werden – wir stoppen die
    // Datenerhebung über den Einwilligungsmodus und bitten um eine neue Auswahl.
    gtag('consent', 'update', { analytics_storage: 'denied' });
    bannerZeigen();
  };

  // ---------- Start ----------
  function start() {
    // Footer-Link „Cookie-Einstellungen" (Widerruf) anbinden – ohne inline-JavaScript
    var links = document.querySelectorAll('a.cc-link');
    for (var i = 0; i < links.length; i++) {
      links[i].addEventListener('click', function (ev) {
        ev.preventDefault();
        window.tmkCookieEinstellungen();
      });
    }

    var wahl = gemerkt();
    if (wahl === 'granted')      analyticsLaden();
    else if (wahl !== 'denied')  bannerZeigen();   // noch keine Entscheidung getroffen
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
