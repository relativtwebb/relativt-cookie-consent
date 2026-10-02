# Changelog

Alla ändringar av betydelse dokumenteras här. Formatet följer
[Keep a Changelog](https://keepachangelog.com/sv/1.1.0/) och versionsnumren
[Semantic Versioning](https://semver.org/lang/sv/).

Texten under en versionsrubrik används som release-text på GitHub och visas
under "Visa detaljer" i WP-admin på sajterna som kör pluginet, så skriv för
den som ska uppdatera.

## [1.3.0] - 2026-10-02

Egen meny i WP-admin, statistik, cookiedeklaration, skriptskanner och
blockering av valfria domäner, plus en viktig rättelse av "Egen kod".
Uppdateringen skapar tabellen `{prefix}rcc_banner_views` automatiskt.
Inga besökare behöver samtycka om. Cookien, rutan och de inbyggda
verktygen fungerar som i 1.2.0.

**Rättat (viktigt för sajter med Egen kod):** innehöll "Egen kod" mer än
en `<script>`-tagg kördes allt efter den första `</script>` utan samtycke,
liksom en `<img>`-pixel efter ett skript. Felet fanns sedan 1.0.0. Koden
läggs nu som JSON i `<script type="application/json" data-rcc-html-block>`
och körs först vid samtycke, i tur och ordning: ett externt skript laddar
klart innan nästa körs. `<noscript>`-delen i klistrade snippets hoppas
över, så att besöket inte räknas två gånger. Har en sajt egen kod med
flera skript: kontrollera i statistikverktygen att inga besök registrerats
utan samtycke, och höj gärna samtyckesversionen.

- **Egen meny** "Cookie Consent" med Inställningar, Statistik,
  Samtyckeslogg och Skanner. Gamla adresser under Inställningar skickas
  vidare, så bokmärken fungerar.
- **Statistik:** fördelning av val (accepterat/delvis/avvisat), val per
  dag, unika samtyckes-ID, visningar av rutan och svarsfrekvens för 7, 30
  eller 90 dagar, plus en widget på panelen. Visningarna räknas per dag
  utan uppgifter om besökaren (`POST /wp-json/rcc/v1/view`) och kan
  stängas av under Allmänt.
- **Cookiedeklaration:** kortkoden `[relativt_cookie_declaration]` visar
  sajtens cookies per kategori, byggt av aktiva verktyg, YouTube/Vimeo och
  egna rader under den nya fliken Cookiedeklaration. Ingår också i
  `/rcc/v1/config` som `cookie_declaration`.
- **Blockera domäner** (fliken Blockering, tidigare "Video & egen kod"):
  skript, inline-skript och iframes från listade domäner blockeras även
  när andra plugin eller temat skriver ut dem. En sökväg gör det smalare,
  t.ex. `www.google.com/maps` utan att röra reCAPTCHA.
- **Skanner:** hämtar sajtens sidor och listar skript och inbäddningar
  från andra domäner, om de blockeras, och föreslår kategori. En knapp
  lägger till domänen i blocklistan. Flaggar kvarglömda cookie-lösningar
  (CookieYes, Cookiebot, OneTrust).
- Blockerade iframes med flera kategorier, och iframes som redan hade en
  `class`, aktiveras nu korrekt.
- Egen kod raderas inte längre när en administratör utan behörigheten
  `unfiltered_html` sparar andra inställningar.
- "Inställningarna sparade" visas efter att man sparat.
- Nya filter: `rcc_blocked_domains`, `rcc_known_domains`,
  `rcc_filter_output_on_request`, `rcc_vendor_cookies`,
  `rcc_cookie_declaration`, `rcc_count_banner_views`,
  `rcc_dashboard_widget`, `rcc_scanner_urls`.
- Internt: Consent Mode-defaulten och GTM i läget "alltid" märks med
  `data-rcc-core` så att domänblockeringen aldrig rör dem.

## [1.2.0] - 2026-10-01

Stöd för headless-sajter: pluginet kan nu vara källa för inställningar
och samtyckeslogg åt en frontend på en annan domän som ritar sin egen
cookie-ruta. **Ingenting ändras för sajter som inte använder det nya
endpointet.** Rutan, cookien, skriptblockeringen, video-gatingen,
inställningssidan och loggen fungerar exakt som i 1.1.0, och inga besökare
behöver samtycka om.

- Nytt publikt REST-endpoint `GET /wp-json/rcc/v1/config` med texter,
  cookienamn och livslängd, samtyckesversion, loggadress,
  Consent Mode-defaulten, aktiva verktyg med kategori, Search
  Console-verifiering och utseende. Svaret cachas i fem minuter
  (`Cache-Control: public, max-age=300`).
- Egen kod och egen CSS ingår aldrig i svaret.
- Nytt filter `rcc_rest_config` för att lägga till eller ta bort fält i
  svaret.
- README har en ny sektion om headless-upplägg: cookieformat,
  samtyckesversion, Consent Mode, loggning och ett TypeScript-exempel.
- Internt: verktygens kategorier och Consent Mode-defaulten ligger nu på
  ett ställe (`rcc_vendors()`, `rcc_vendor_category()`,
  `rcc_consent_mode_defaults()`), så att sidan och endpointet alltid
  stämmer överens. Utskriften i `<head>` är oförändrad.

## [1.1.0] - 2026-09-10

Samtyckeslogg och samtyckesversion. Uppdateringen skapar tabellen
`{prefix}rcc_consent_log` automatiskt; inga besökare behöver samtycka om.

- Samtyckeslogg: varje val sparas med samtyckes-ID, tidpunkt, status
  (accepterat/avvisat/delvis), kategorier, land (från Cloudflare-headern
  eller motsvarande), samtyckesversion, plugin-version och
  webbläsarsträng. Ny sida under Inställningar → Samtyckeslogg med sökning
  på ID, filter på status och månad, CSV-export (Excel-vänlig) samt
  knappar för gallring och tömning.
- Automatisk gallring via WP-Cron: poster äldre än inställt antal
  månader (standard 12) raderas dagligen.
- Valfri saltad IP-hash i loggen (av som standard).
- Samtyckesversion under Allmänt: höj siffran när nya verktyg läggs till
  eller texter ändras, så visas rutan igen för alla med äldre samtycke.
  Cookies från 1.0.0 räknas som version 1.
- Besökaren ser sitt samtyckes-ID och tidpunkten för valet under
  kategorierna när cookie-inställningarna öppnas (etiketten är en
  inställning under Texter; tom = visas inte).
- Samtyckescookien innehåller nu `id` och `version`. `window.rcc.getConsent()`
  och `hasConsent()` returnerar null/false när versionen är inaktuell;
  ny metod `window.rcc.getConsentId()`.
- REST-endpoint `POST /wp-json/rcc/v1/consent` (anropas i bakgrunden
  med `keepalive`, påverkar aldrig rutan).
- Nya inställningar: `consent_version`, `consent_log_enabled`,
  `consent_log_retention_months`, `consent_log_ip_hash`, `consent_id_label`.
- Nya hooks: `rcc_consent_log_enabled`, `rcc_consent_log_country`,
  `rcc_consent_log_record`, `rcc_consent_logged`.
- `uninstall.php` tar även bort loggtabellen och cron-jobbet.

## [1.0.0] - 2026-08-31

Första versionen.

- Cookie-ruta med tre kategorier (nödvändiga, statistik, marknadsföring)
  och valen acceptera alla, endast nödvändiga eller per kategori.
- Blockerar skript tills samtycke: Google Analytics 4, Google Ads,
  Google Tag Manager, Meta Pixel, Hotjar, Microsoft Clarity, Microsoft/Bing
  UET, LinkedIn Insight Tag, Reddit, TikTok, Pinterest, Snapchat samt egen
  kod per kategori. Google Search Console-verifiering skrivs alltid ut
  (sätter inga cookies).
- Google Consent Mode v2: default "denied" innan val, "update" vid val,
  plus dataLayer-eventet `rcc_consent_update` för GTM-triggers. GTM kan
  laddas blockerat tills samtycke eller alltid med Consent Mode.
- Sajtbred blockering av YouTube-/Vimeo-inbäddningar (även i sidbyggare
  som Oxygen, Elementor och Bricks) med "Visa innehåll"-knapp. Fler värdar
  kan läggas till via filtret `rcc_gated_iframe_hosts`.
- Utseende-flik: layout (rad, kort vänster/höger, centrerad ruta), mörkad
  bakgrund, färger, hörnradie, typsnitt och egen CSS. Färgerna skrivs ut
  som CSS-variabler (`--rcc-bg`, `--rcc-text`, `--rcc-accent`,
  `--rcc-btn-bg`, `--rcc-btn-text`, `--rcc-radius`).
- Alla besökartexter är inställningar: rubrik, ingress, kategoritexter,
  knappar, länktext till integritetspolicyn och texterna på blockerad
  video. En engelskspråkig sajt konfigureras helt utan kodändring.
- Automatiska uppdateringar från GitHub Releases, inklusive "Visa
  detaljer" med changelog och en "Sök efter uppdatering"-länk i
  plugin-listan. Stöd för privata repon via `RCC_GITHUB_TOKEN`.
- Flytande knapp, kortkoden `[relativt_cookie_settings]` (fungerar flera
  gånger per sida) och attributet `data-rcc-open` för egna länkar.
- Valfri omladdning av sidan när ett samtycke tas bort. Samtyckescookien
  får flaggan `Secure` på https-sajter och namnet kan bytas via filtret
  `rcc_cookie_name`.
- Publikt JS-API `window.rcc` (getConsent, hasConsent, openSettings,
  acceptAll, rejectAll, save, refreshIframes) och eventet
  `rcc_consent_updated`.
- Filter och actions för utvecklare: `rcc_default_settings`,
  `rcc_settings`, `rcc_cookie_name`, `rcc_consent_mode_defaults`,
  `rcc_gated_iframe_hosts`, `rcc_gate_video_on_request`, `rcc_inline_css`,
  `rcc_script_config`, `rcc_cookie_icon_svg`, `rcc_enable_github_updates`,
  `rcc_github_token`, `rcc_gated_scripts`, `rcc_banner_top`,
  `rcc_banner_bottom`.
- Tillgänglighet: riktiga `<label>`-kopplingar för reglagen,
  `aria-expanded`, fokushantering i modalt läge, Escape stänger när ett
  val redan finns.
- `uninstall.php` som städar bort inställningarna när pluginet raderas.
