=== Relativt Cookie Consent ===
Contributors: relativt
Tags: cookies, consent, gdpr, google analytics, google tag manager, meta pixel
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lättviktig GDPR-anpassad cookie-ruta som blockerar spårningsskript och videoinbäddningar tills besökaren samtyckt.

== Beskrivning ==

Relativt Cookie Consent visar en cookie-ruta med tre kategorier och ser till att inga statistik- eller marknadsföringsskript körs innan besökaren sagt ja.

* Nödvändiga – sätts alltid, kräver inte samtycke.
* Statistik – Google Analytics 4, Hotjar, Microsoft Clarity.
* Marknadsföring – Meta Pixel, Google Ads, Microsoft/Bing Advertising (UET), LinkedIn Insight Tag, Reddit, TikTok, Pinterest, Snapchat.

Google Tag Manager kan antingen blockeras tills samtycke eller laddas direkt och styras via Google Consent Mode v2. Verktyg som saknar eget fält klistras in under "Egen kod" per kategori.

Skript och inbäddningar som andra plugin eller temat skriver ut kan blockeras per domän. Skannern under Cookie Consent → Skanner visar vad som laddas utan samtycke och lägger till domänen med ett klick.

YouTube- och Vimeo-inbäddningar blockeras sajtbrett, även när de ligger i sidbyggare som Oxygen, Elementor eller Bricks. En "Visa innehåll"-knapp ersätter videon tills rätt kategori är godkänd.

Varje samtycke loggas (samtyckes-ID, tidpunkt, kategorier, land, version) som bevis enligt GDPR, med sökning, filter och CSV-export under Cookie Consent → Samtyckeslogg. Statistiken visar fördelningen av val, visningar av rutan och svarsfrekvens.

Kortkoden [relativt_cookie_declaration] visar en cookiedeklaration byggd av de verktyg som är på. En samtyckesversion gör att alla besökare tillfrågas på nytt när ni lägger till verktyg eller ändrar texter.

Andra tillägg kan följa samtycket via window.rcc och rcc_has_consent(), eller via WP Consent API, och registrera sina cookies så att de listas i deklarationen och raderas när besökaren nekar.

Utseendet (layout, färger, radie, typsnitt) och alla texter ställs in per sajt. Nya versioner hämtas från GitHub och installeras via Uppdateringar i WP-admin.

Pluginet skriver inte om er integritetspolicy. Se till att policyn listar de verktyg ni faktiskt använder.

== Installation ==

1. Ladda upp zip-filen via Tillägg → Lägg till nytt → Ladda upp tillägg, eller lägg mappen `relativt-cookie-consent` i `/wp-content/plugins/`.
2. Aktivera pluginet.
3. Gå till Cookie Consent i vänstermenyn. Fyll i ID:n för de verktyg sajten använder, länk till integritetspolicyn och justera texter och utseende.
4. Spara. Bannern visas automatiskt för besökare som inte gjort ett val.

== Frequently Asked Questions ==

= Hur lägger jag en länk till cookie-inställningarna i footern? =

Använd kortkoden `[relativt_cookie_settings text="Cookie-inställningar"]` eller ge valfritt element attributet `data-rcc-open`.

= Vad händer när en besökare tar bort ett samtycke? =

Redan laddade skript kan inte "avladdas", men inga nya anrop görs. Kända förstapartscookies (t.ex. _ga, _fbp) och cookies som andra tillägg registrerat raderas direkt. Under Allmänt kan du välja att sidan laddas om automatiskt när ett samtycke tas bort.

= Repot är privat, hur får sajten uppdateringar? =

Lägg `define( 'RCC_GITHUB_TOKEN', 'github_pat_...' );` i wp-config.php. En fine-grained token med läsrättighet till Contents räcker.

== Changelog ==

= 1.4.0 =
* Kakregister: andra tillägg registrerar sina cookies med filtret rcc_registered_cookies. De listas i cookiedeklarationen och raderas när besökaren nekar kategorin.
* _ga, _ga_*, _gcl_*, _fbp och _fbc raderas när statistik respektive marknadsföring nekas.
* PHP-hjälparna rcc_has_consent() och rcc_get_consent(), med kontroll av samtyckesversionen.
* Ny JS-händelse rcc_ready, för tillägg som laddas före pluginets skript.
* Integration med WP Consent API: tillägg som läser API:t följer valet i rutan.
* Inga ändringar för besökarna, och inga nya inställningar.

= 1.3.0 =
* Rättat: egen kod med flera skript-taggar kunde köras utan samtycke. Koden körs nu först vid samtycke och i tur och ordning.
* Egen meny i WP-admin: Inställningar, Statistik, Samtyckeslogg, Skanner.
* Statistik över val och visningar av rutan, plus widget på panelen.
* Cookiedeklaration med kortkoden [relativt_cookie_declaration].
* Blockering av valfria domäner, även från andra plugin och temat.
* Skanner som visar skript och inbäddningar som laddas utan samtycke.

= 1.2.0 =
* Nytt REST-endpoint GET /wp-json/rcc/v1/config så att en headless-frontend kan rita en egen ruta och logga till samma samtyckeslogg.
* Nytt filter rcc_rest_config.
* Ingen ändring för sajter som inte använder endpointet.

= 1.1.0 =
* Samtyckeslogg med sökning, filter, CSV-export och automatisk gallring.
* Samtyckesversion som tvingar fram nytt samtycke när verktyg eller texter ändras.
* Besökaren ser sitt samtyckes-ID i cookie-inställningarna.
* Nytt JS-API: window.rcc.getConsentId(). Nya filter för loggen.

= 1.0.0 =
* Första versionen.
* Blockerade skript för Google (GA4, Ads, GTM), Meta, Hotjar, Clarity, Bing UET, LinkedIn, Reddit, TikTok, Pinterest, Snapchat samt egen kod per kategori.
* Sajtbred blockering av YouTube-/Vimeo-inbäddningar.
* Utseende-flik med layout, färger, radie, typsnitt och egen CSS.
* Alla besökartexter som inställningar.
* Automatiska uppdateringar från GitHub Releases.
* JS-API (window.rcc), event, filter och actions för utvecklare.
