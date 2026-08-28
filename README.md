# Relativt Cookie Consent

Lättviktigt WordPress-plugin för GDPR-anpassat cookie-samtycke. Blockerar spårningsskript och videoinbäddningar tills besökaren sagt ja, utan externa tjänster, jQuery eller månadsavgift.

Byggt av [Relativt](https://relativt.se) för våra kundsajter. Fritt att använda under GPL v2 eller senare.

## Vad pluginet gör

- Visar en cookie-ruta med tre kategorier: **Nödvändiga**, **Statistik** och **Marknadsföring**. Besökaren kan acceptera alla, bara nödvändiga eller välja per kategori.
- Skriver ut spårningsskript som `<script type="text/plain" data-cookiecategory="...">`. De körs inte förrän besökaren samtyckt till rätt kategori, och de finns kvar i sidan så att ingen omladdning behövs.
- Har färdiga fält för Google Analytics 4, Google Ads, Google Tag Manager, Google Search Console-verifiering, Meta Pixel, Hotjar, Microsoft Clarity, Microsoft/Bing UET, LinkedIn Insight Tag, Reddit, TikTok, Pinterest och Snapchat. Andra verktyg klistras in under "Egen kod" per kategori.
- Skickar Google Consent Mode v2-signaler (`denied` som utgångsläge, `update` vid val) och ett dataLayer-event för GTM-triggers.
- Blockerar YouTube- och Vimeo-iframes sajtbrett, även i sidbyggare som Oxygen, Elementor och Bricks, genom att skriva om sidans HTML innan den skickas till besökaren. En "Visa innehåll"-knapp ersätter videon tills rätt samtycke finns.
- Låter varje sajt styra utseendet (layout, färger, radie, typsnitt, egen CSS) och alla texter från WP-admin.
- Hämtar nya versioner från det här repots GitHub Releases och visar dem under Uppdateringar i WP-admin.

## Så ser det ut

Tre layouter, alla med färger och radie från Utseende-fliken. Typsnittet ärvs från temat.

| Rad i full bredd (standard) | Kort i hörnet | Centrerad ruta |
| --- | --- | --- |
| ![Rad](docs/layout-bar.png) | ![Kort](docs/layout-card.png) | ![Centrerad](docs/layout-center.png) |

## Installation på en kundsajt

1. Ladda ner `relativt-cookie-consent.zip` från senaste [releasen](../../releases/latest).
2. I WP-admin: Tillägg → Lägg till nytt → Ladda upp tillägg. Aktivera.
3. Gå till Inställningar → Cookie Consent och fyll i:
   - **Verktyg**: ID:n för de verktyg sajten faktiskt använder. Tomma fält skrivs aldrig ut.
   - **Video & egen kod**: om video ska blockeras och vilken kategori YouTube/Vimeo tillhör, plus egen kod för verktyg utan eget fält.
   - **Texter**: rubrik, ingress, kategoribeskrivningar och knapptexter. Här byter du språk på hela rutan om sajten är på engelska.
   - **Utseende**: layout, färger och typsnitt så rutan passar sajtens profil.
   - **Allmänt**: länk till integritetspolicyn, hur länge samtycket sparas, flytande knapp.
4. Kontrollera sajten i ett inkognitofönster: inga anrop till Google, Meta osv. ska göras innan du klickat i rutan. Verktyget "Nätverk" i webbläsarens utvecklarverktyg visar det tydligt.

Installera från release-zippen, inte från "Download ZIP" på repots startsida. Den senare packar upp till en mapp med fel namn, vilket ställer till det för uppdateringarna.

### Byta från en annan cookie-lösning

Avaktivera den gamla lösningen i samma veva som den här aktiveras, så att besökarna inte får två rutor. Samtyckescookien heter `relativt_cookie_consent`, så besökare som samtyckt i det gamla verktyget får frågan en gång till. Behöver en sajt behålla ett tidigare cookienamn går det via filtret:

```php
add_filter( 'rcc_cookie_name', fn() => 'gammalt_cookienamn' );
```

## Kategorier och vad som blockeras

| Verktyg | Kategori | Notering |
| --- | --- | --- |
| Google Analytics 4 | Statistik (eller Marknadsföring) | Kryssa i "Behandla som marknadsföring" om GA4 är kopplat mot Google Ads-remarketing eller Google Signals. |
| Google Ads | Marknadsföring | |
| Google Tag Manager | Statistik eller Marknadsföring | Se avsnittet om GTM nedan. |
| Google Search Console | Blockeras inte | Ren ägarskaps-verifiering, sätter inga cookies. |
| Meta Pixel | Marknadsföring | |
| Hotjar, Microsoft Clarity | Statistik | |
| Microsoft/Bing UET, LinkedIn, Reddit, TikTok, Pinterest, Snapchat | Marknadsföring | |
| YouTube, Vimeo | Valbart, standard Marknadsföring | |
| Egen kod | Valbart per fält | Kräver behörigheten `unfiltered_html` för att spara `<script>`-taggar. |

## Google Tag Manager

Två lägen under Verktyg:

**Blockerad tills samtycke (standard).** Containern laddas först när besökaren samtyckt till statistik eller marknadsföring. Säkrast om ni inte hanterar samtycke inne i GTM. Taggarna i containern bör ändå ha samtyckeskontroller inställda, så att en marknadsföringstagg inte körs för en besökare som bara godkänt statistik.

**Alltid, med Consent Mode v2.** Containern laddas direkt. Pluginet skickar `gtag('consent', 'default', ...)` med allt nekat innan GTM laddas och `gtag('consent', 'update', ...)` när besökaren väljer. Dessutom pushas eventet `rcc_consent_update` till dataLayer med variablerna `rcc_statistics` och `rcc_marketing` (true/false), som ni kan använda som trigger. I det här läget måste varje tagg i GTM ha "Ytterligare samtycke krävs" eller inbyggda samtyckeskontroller, annars sätter de cookies utan samtycke.

## Länkar till cookie-inställningarna

Besökaren ska kunna ändra sitt val när som helst. Tre sätt:

- Den flytande knappen (på/av och position under Allmänt).
- Kortkoden `[relativt_cookie_settings text="Cookie-inställningar" class="min-klass"]`, t.ex. i footern eller på integritetspolicysidan.
- Attributet `data-rcc-open` på valfritt element, t.ex. en menylänk: `<a href="#" data-rcc-open>Cookie-inställningar</a>`.

## För utvecklare

### Egna blockerade skript

Allt som skrivs ut som `<script type="text/plain" data-cookiecategory="statistics">` (eller `marketing`, eller `"statistics marketing"` för endera) aktiveras av pluginet. Externa skript anges med `data-cookiesrc` i stället för `src`. Lägg dem i `<head>` via actionen `rcc_gated_scripts`:

```php
add_action( 'rcc_gated_scripts', function ( $settings ) {
	rcc_blocked_script_open( 'statistics', 'data-cookiesrc="https://example.com/stats.js" async' );
	echo '</script>';
} );
```

Block som blandar `<script>` och andra taggar (t.ex. `<img>`) markeras med `data-html-block="1"` och innehåller rå HTML.

### JavaScript-API

```js
window.rcc.getConsent();          // { necessary: true, statistics: false, marketing: true, timestamp: "..." } eller null
window.rcc.hasConsent( 'marketing' );
window.rcc.openSettings();        // öppnar rutan med kategorivalen
window.rcc.acceptAll();
window.rcc.rejectAll();
window.rcc.save( { statistics: true, marketing: false } );
window.rcc.refreshIframes();      // om ni själva lägger in blockerade iframes efter sidladdning

document.addEventListener( 'rcc_consent_updated', function ( e ) {
	console.log( e.detail ); // samtyckesobjektet
} );
```

### Filter och actions

| Hook | Typ | Användning |
| --- | --- | --- |
| `rcc_default_settings` | filter | Egna standardvärden, t.ex. byråns husfärger, innan admin sparat något. |
| `rcc_settings` | filter | Åsidosätt sparade värden i kod (t.ex. tomt GA-ID på staging). |
| `rcc_cookie_name` | filter | Namnet på samtyckescookien. |
| `rcc_consent_mode_defaults` | filter | Utgångsläget för Consent Mode v2. |
| `rcc_gated_iframe_hosts` | filter | Fler värdar att blockera, t.ex. `'google.com' => 'marketing'` för Google Maps. |
| `rcc_gate_video_on_request` | filter | Returnera false för att hoppa över video-gatingen på en viss förfrågan. |
| `rcc_inline_css` | filter | CSS-variablerna och den egna CSS:en som skrivs ut. |
| `rcc_script_config` | filter | Konfigurationen som skickas till JS (`rccSettings`). |
| `rcc_cookie_icon_svg` | filter | Ikonen på den flytande knappen. |
| `rcc_enable_github_updates` | filter | Returnera false för att stänga av uppdateringskontrollen. |
| `rcc_github_token` | filter | Alternativ till konstanten `RCC_GITHUB_TOKEN`. |
| `rcc_gated_scripts` | action | Skriv ut egna blockerade skript i `<head>`. |
| `rcc_banner_top`, `rcc_banner_bottom` | action | Egen markup i rutan, t.ex. en logotyp. |

### CSS

Alla klasser har prefixet `.rcc-`: `.rcc-banner`, `.rcc-banner--bar|card-left|card-right|center`, `.rcc-banner__heading`, `.rcc-category`, `.rcc-switch`, `.rcc-btn--primary|outline|text`, `.rcc-reopen`, `.rcc-inline-link`, `.rcc-iframe-overlay`. Färgerna från Utseende-fliken finns som CSS-variabler på `:root`: `--rcc-bg`, `--rcc-text`, `--rcc-accent`, `--rcc-btn-bg`, `--rcc-btn-text`, `--rcc-radius`, `--rcc-font`.

## Uppdateringar via GitHub

Pluginet har `Update URI: https://github.com/relativtwebb/relativt-cookie-consent` i huvudet och hakar in i WordPress vanliga uppdateringskontroll via filtret `update_plugins_github.com`. Kundsajterna frågar GitHub efter senaste releasen (cachas sex timmar), och en ny version dyker upp under Uppdateringar precis som för plugin från wordpress.org. "Visa detaljer" visar release-texten. Länken "Sök efter uppdatering" i plugin-listan tömmer cachen och kontrollerar direkt.

Repot som används står i konstanten `RCC_GITHUB_REPO` i `relativt-cookie-consent.php`. Byter repot ägare eller namn: uppdatera konstanten, `Plugin URI` och `Update URI` i huvudet innan nästa release.

**Privat repo.** Lägg i wp-config.php på kundsajten:

```php
define( 'RCC_GITHUB_TOKEN', 'github_pat_...' );
```

En fine-grained personal access token med läsrättighet till Contents för just det här repot räcker. Utan token fungerar uppdateringar bara om repot är publikt.

## Släppa en ny version

1. Höj versionsnumret på tre ställen: `Version:` i plugin-huvudet, `RCC_VERSION` och `Stable tag:` i `readme.txt`. Lint-workflowet stoppar om de skiljer sig.
2. Skriv en sektion `## [1.2.0] - ÅÅÅÅ-MM-DD` i `CHANGELOG.md`. Texten blir release-text på GitHub och changelog i WP-admin.
3. Committa, tagga och pusha:

   ```bash
   git commit -am "Version 1.2.0"
   git tag v1.2.0
   git push && git push origin v1.2.0
   ```

4. GitHub Actions bygger `relativt-cookie-consent.zip` (utan `.github`, README och liknande) och skapar releasen med zippen som bilaga. Kundsajterna ser uppdateringen inom sex timmar, eller direkt via "Sök efter uppdatering".

Workflowet vägrar bygga om taggen inte matchar versionen i pluginet.

## Att stämma av innan lansering

- **Integritetspolicyn** ska lista de verktyg som faktiskt körs, med kategori och lagringstid. Pluginet skriver inte om policyn.
- **GA4-kategorin**: kopplat mot Google Ads eller Google Signals räknas som marknadsföring.
- **Video-gatingen** är ett bästa-möjliga-skydd. Testa på en sida med inbäddad video. Lazy-laddade iframes som använder `data-src` i stället för `src` fångas inte.
- **Egen kod** som klistras in måste själv respektera kategorin den ligger under.
- **Samtyckets giltighetstid**: standard 180 dagar. IMY rekommenderar att samtycke inhämtas på nytt åtminstone årligen.

## Utveckling

Inga byggsteg, inga beroenden. Klona repot rakt in i `wp-content/plugins/relativt-cookie-consent/` på en lokal WordPress och aktivera.

```bash
git clone https://github.com/relativtwebb/relativt-cookie-consent.git
```

Syntaxkontroll lokalt:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
for f in assets/js/*.js; do node --check "$f"; done
```

Filstruktur:

```
relativt-cookie-consent.php        Huvudfil: konstanter, aktivering, uppdaterare
uninstall.php                      Städar bort inställningar vid radering
includes/settings.php              Standardvärden, sanering, inställningssidan
includes/vendors.php               Skripten för varje verktyg
includes/frontend.php              Banner, video-gating, kortkod, tillgångar
includes/class-rcc-github-updater.php  Uppdateringar från GitHub Releases
assets/css/relativt-cookie-consent.css Frontend-styling (CSS-variabler)
assets/js/relativt-cookie-consent.js   Frontend-logik och JS-API
assets/css/admin.css, assets/js/admin.js  Inställningssidan
```

## Licens

GPL v2 eller senare. Se [LICENSE](LICENSE).
