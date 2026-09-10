# Changelog

Alla ändringar av betydelse dokumenteras här. Formatet följer
[Keep a Changelog](https://keepachangelog.com/sv/1.1.0/) och versionsnumren
[Semantic Versioning](https://semver.org/lang/sv/).

Texten under en versionsrubrik används som release-text på GitHub och visas
under "Visa detaljer" i WP-admin på sajterna som kör pluginet, så skriv för
den som ska uppdatera.

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
