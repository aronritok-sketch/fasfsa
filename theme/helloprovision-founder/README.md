# HelloProVision Founder – WordPress téma

Áron Ritók-Filip személyes márkaoldalának témája, a jóváhagyott „Wheatpaste” (utcai plakát) dizájnban.
Önálló klasszikus téma blokkszerkesztővel. **Nem kell hozzá plugin** (a CRM-bekötéshez a HelloProVision
leads mu-plugin ajánlott, lásd „CRM-bekötés”): a leadkezelő, az SEO, a séma,
az analitika és a Growth Scorecard is a témában van.

- WordPress 6.5+ (6.8-on tesztelve), PHP 8.0+
- Betűk (Anton, Archivo, Caveat Brush) a témában, OFL licenc – nincs külső betűtöltés
- Minden oldal blokkokból áll, szerkeszthető a Gutenbergben

## Telepítés (5 perc)

1. **Megjelenés → Témák → Új hozzáadása → Téma feltöltése**, válaszd a `helloprovision-founder-<verzió>.zip`-et (a `theme/dist/` mappából), majd *Aktiválás*.
2. **Megjelenés → HelloProVision setup → Import pages & menus.**
   Létrehozza az összes oldalt (főoldal, szolgáltatások, rólam, esettanulmányok, insights, stratégiai hívás,
   köszönőoldal, scorecard, jogi oldalak), a 4 menüt, egy esettanulmányt és egy cikket, és beállítja a
   kezdőlapot, a blogoldalt és a permalinkeket (`/insights/%postname%/`).
   WP-CLI-ből: `wp hpvf import-demo` (felülírás: `--force`), ellenőrzőlista: `wp hpvf checklist`.
3. **Megjelenés → Testreszabás → HelloProVision site**: cégnév, telefon, e-mail (pontosan úgy, ahogy a
   Google Cégprofilban szerepel), foglalási link (Cal.com / Calendly), hova menjenek a leadek, GA4 vagy GTM.
4. A setup oldalon lévő **Launch checklist** mutatja, mi hiányzik még élesítés előtt, és felsorolja azokat
   az oldalakat, ahol még ellenőrizendő adat (placeholder) van.

## Szerkesztés

- **Szövegek**: bármelyik oldalt megnyitod, és a blokkokat közvetlenül átírod. A kiemelések a szerkesztő
  eszköztárában vannak: *Pink highlight* (kiemelés), *Marker circle* (kézzel rajzolt kör),
  *Scribble underline* (aláhúzás), *Hand-written note* (kézírás), *Fact to verify* (ellenőrizendő adat).
- **Ellenőrizendő adatok**: a narancs szaggatott keretes részeket (számok, ügyfélnevek, vélemények) csak
  a bejelentkezett szerkesztők látják kiemelve. Amíg nincs valós adat, cseréld le vagy töröld őket.
- **Fotók**: a *Photo (polaroid)* és *Project screenshot* blokk oldalsó paneljén a *Choose image* gombbal médiatárból választasz képet.
  Amíg nincs fotó, a dizájnos polaroid-helyőrző látszik. Fájlokat a témába is tehetsz:
  `assets/img/people/aron-hero.jpg`, `assets/img/projects/imperial-kitchens-1.jpg` stb.
- **Szekciók**: a blokkbeszúró *Patterns* fülén a *HelloProVision: sections* kategóriában minden főoldali szekció
  külön is beilleszthető (hero, ticker, szolgáltatáskártyák, folyamat, GYIK, CTA…), a *HelloProVision: full pages*
  alatt pedig teljes oldalsablonok vannak új szolgáltatás- vagy városoldalhoz.
- **SEO**: a szerkesztő jobb oldali paneljén (*Search & sharing*) SEO-cím, meta leírás, noindex, oldal
  szerepe (szolgáltatás, rólam, köszönőoldal…) és a szolgáltatás neve a Service sémához.
- **Esettanulmány**: *Case studies → Add new*, a *Case study facts* panelben ügyfél, helyszín, szolgáltatások,
  idővonal, fő eredmény + forrás. A hub és a főoldal kártyái ebből töltődnek.
- **Cikkek**: *Posts*; a kategória (Websites / Local SEO / Growth) adja a témát és a `/insights/<téma>/` oldalt.

## Leadkezelő (Leads menü)

Minden beküldés (stratégiai hívás űrlap, Growth Scorecard) leadként jön létre, **e-mail cím szerint
összevonva**: ha ugyanaz az ember előbb kitölti a scorecardot, aztán hívást kér, egy adatlapon látszik minden.

- **Lista**: szakasz szerinti fülek (New → Contacted → Qualified → Call booked → Proposal sent → Won / Lost),
  forrásszűrő, keresés névre/e-mailre/telefonra/weboldalra, olvasatlan jelölés, lejárt válaszidő jelzés,
  csoportos áthelyezés, **CSV export** (Excel-biztos).
- **Adatlap**: elérhetőség, iparág, város, kihívás, büdzsé, időzítés, honnan jött (oldal + UTM), scorecard
  eredmény területenként, jegyzetek és teljes idővonal; jobb oldalt szakasz, üzleti érték, következő
  follow-up dátum, elvesztés oka.
- **Értesítések**: új leadről e-mail a beállított címre; a látogató visszaigazolást kap (a scorecardosok
  az eredményüket és a 3 következő lépést is).
- **GA4**: ha a Testreszabásban megadod a Measurement Protocol API secretet, a *Qualified* és *Won*
  szakaszba mozgatás `qualified_lead` / `close_convert_lead` eseményként megy a GA4-be.
- **Adatvédelem**: a WordPress adatexport/-törlés eszközei (Eszközök → Személyes adatok) a leadeket is kezelik.
- **Spamvédelem**: honeypot, minimális kitöltési idő, IP-alapú korlát (6 beküldés / 10 perc).
- Ha a JavaScript nem fut, az űrlap akkor is működik (sima POST → foglalási oldal / köszönőoldal).

## CRM-bekötés (HelloProVision CRM)

Minden új lead (stratégiai hívás, Growth Scorecard) a témában marad, **és** átmegy a HelloProVision CRM-be
érdeklődőként (`inc/crm-bridge.php`). A küldés a látogató válasza után, a háttérben történik, a kitöltést
nem lassítja és nem akaszthatja meg.

**Mi kell hozzá ezen a WordPressen** (ez egy külön oldal, nem a helloprovision.com):

1. A HelloProVision **beállító varázsló** (`helloprovision-setup` bővítmény) **„weboldal” (site) módban**. Ez
   telepíti a `wp-content/mu-plugins/helloprovision-leads.php` („Leads → CRM”) mu-plugint, és betölti a kulcsokat.
2. A két kulcs (a varázslóban, vagy kézzel a `wp-config.php`-ban), ugyanaz a titok, mint a CRM gépen:
   ```php
   define( 'HPV_CRM_URL', 'https://crm.helloprovision.com' );
   define( 'HPV_BRIDGE_SECRET', '…' ); // legalább 16 karakter
   ```

**Hogyan megy át:**

- **Ha a Leads → CRM mu-plugin aktív** (ez az ajánlott): a téma a mu-plugin `hpv_leads_map()` és
  `hpv_leads_send()` függvényét hívja, pontosan úgy, mint a többi űrlapnál (forrás: `contact`, űrlap:
  „Áron – stratégiai hívás” / „Áron – Growth Scorecard”, oldal URL, forrásmérés). A sorba állítás, az óránkénti
  újrapróbálás (3 napig), a 3 nap után küldött figyelmeztető e-mail, az aláírás és a `hpv_attr` forrás-süti a
  mu-pluginban van. Ugyanazt az IP-korlátot is alkalmazza (óránként 5 kitöltés).
- **Ha nincs mu-plugin**, de a két konstans meg van adva: a téma maga küldi, ugyanazzal az aláírással
  (`POST {HPV_CRM_URL}/wp-json/hpv/v1/bridge/lead`, `X-HPV-Timestamp` + `X-HPV-Signature` = HMAC-SHA256),
  hiba esetén óránként újrapróbálja (wp-cron, 3 napig; a CRM 400-as elutasítását nem).
  Ha a CRM ugyanebben a WordPressben fut, közvetlenül hívja (`hpv_leads_ingest()`).
- **Ha egyik sincs**: nem történik semmi, a leadek csak itt, a Leads menüben vannak.

**Mezők a CRM-ben:** név, e-mail, telefon, weboldal; az üzenet egy rövid összefoglaló; a „kérdés: válasz”
jegyzetben Iparág, Város, Fő kihívás, Büdzsé, Időzítés (hívás), illetve Scorecard eredmény, Leggyengébb terület,
Területek (scorecard), és a Kampány (UTM). Forrásmérés: a mu-plugin sütije (első + utolsó látogatás), ha nincs,
az űrlap UTM-adataiból. Az iparág nem cégnév, ezért nem kerül a cég mezőbe; a CRM-ben az ügyfél neve a lead neve lesz.

**Hol látszik:** *Megjelenés → HelloProVision setup → Launch checklist* „CRM-kapcsolat” sora (bekötés módja,
utolsó küldés), és a lead adatlapján a *HelloProVision CRM* doboz (mikor ment át, hiba). Lead meta:
`_hpvf_crm_sent` (az utolsó sikeres küldés ideje), `_hpvf_crm_status` (`sent` / `queued` / `retry` / `failed` /
`skipped`), `_hpvf_crm_error`. Az idővonalon is megjelenik.

## SEO, séma, mérés

- Cím, meta leírás, canonical, Open Graph, `noindex` a köszönőoldalon, keresésen és minden nem-éles
  környezetben (`WP_ENVIRONMENT_TYPE`). Ha Yoast / Rank Math / SEOPress / AIOSEO aktív, a téma félreáll.
- JSON-LD gráf: Person (középen), ProfessionalService, WebSite, WebPage/ProfilePage/CollectionPage,
  BreadcrumbList, Service (szolgáltatásoldalakon), BlogPosting, esettanulmány Article. Csak valós adatok.
- Sitemap: noindex oldalak és a felhasználók kihagyva.
- GA4 (gtag) vagy GTM, Consent Mode v2, opcionális saját cookie-sáv. Bejelentkezett szerkesztők nincsenek mérve.
- Események: `cta_click`, `form_start`, `form_submit`, `generate_lead`, `scorecard_start`,
  `scorecard_complete`, `booking_open`, `click_to_call`, `click_to_email`.

## Fejlesztőknek

A dizájn és a szöveg egyetlen forrása a HTML-prototípus (`prototype/src`). A téma generált részei:

```
python3 tools/build_theme.py
```

- `assets/css/main.css` = `prototype/src/assets/css/main.css` (a `@font-face` nélkül, a betűket a
  `theme.json` tölti) + `assets/css/src/wordpress.css`
- `patterns/*.php` = a prototípus oldalai blokkjelölésként (`page-*`, `section-*`, esettanulmány, cikk)
- `patterns/demo.json` = az importáló manifestje (oldalfa, SEO-mezők, oldalszerepek)

Ezeket ne kézzel szerkeszd: a prototípust javítsd, és futtasd újra a buildet. Minden más PHP/JS kézzel írt.

| Mappa / fájl | Tartalom |
| --- | --- |
| `inc/blocks.php` | Dinamikus blokkok (photo, project, case-grid, insights, breadcrumbs, contact, ticker, űrlapok) |
| `inc/leads.php`, `inc/lead-admin.php` | REST végpontok (`hpvf/v1/lead`, `hpvf/v1/scorecard`), tárolás, e-mailek, admin |
| `inc/crm-bridge.php` | Leadek továbbítása a HelloProVision CRM-be (lásd lent) |
| `inc/seo.php`, `inc/schema.php`, `inc/tracking.php` | SEO, JSON-LD, GA4/GTM + consent |
| `inc/demo-import.php` | Setup oldal, importáló, launch checklist, WP-CLI parancsok |
| `assets/js/editor/blocks.js` | Szerkesztő: blokkok, formátumok, oldalsó panelek (build nélküli JS) |
| `template-parts/` | Stratégiai hívás űrlap, scorecard, wordmark |

Hookok: `hpvf_lead_created( $lead_id, $type, $data )`, `hpvf_lead_stage_changed( $lead_id, $new, $old )`,
szűrők: `hpvf_schema_graph`, `hpvf_should_track`, `hpvf_launch_checks`, `hpvf_crm_values`, `hpvf_crm_form_label`.
Éles indexelés nem-production környezetben: `define( 'HPVF_ALLOW_INDEXING', true );`.

Teszt (WordPress nélkül, a CRM-híd): `php tests/crm-bridge.php` a repó gyökeréből. Ha a HelloProVision repó
mellette van (`../hlprv`) vagy `HPV_LEADS_MU=<útvonal>` meg van adva, a valódi leads mu-pluginnal is lefut.

### Előtag: `hpvf_` (1.1.0 óta)

A téma a helloprovision.com rendszere (hlprv: CRM, beállító varázsló, leads mu-plugin) mellett is futhat
ugyanabban a WordPressben, ezért 1.1.0-tól minden saját neve külön előtagot kapott:

| Mi | Régi (1.0.0) | Új (1.1.0) |
| --- | --- | --- |
| Téma mappa / zip | `helloprovision/`, `helloprovision-1.0.0.zip` | `helloprovision-founder/`, `helloprovision-founder-1.1.0.zip` |
| PHP függvények, hookok, szűrők | `hpv_…` (pl. `hpv_setup_handle()`, ütközött a varázslóval) | `hpvf_…` (`hpvf_setup_handle()`) |
| Konstansok | `HPV_VERSION`, `HPV_DIR`, `HPV_URI`, `HPV_ALLOW_INDEXING` | `HPVF_…` |
| REST | `hpv/v1/lead`, `hpv/v1/scorecard` | `hpvf/v1/lead`, `hpvf/v1/scorecard` |
| Lead bejegyzéstípus, meta | `hpv_lead`, `_hpv_*` | `hpvf_lead`, `_hpvf_*` |
| Esettanulmány meta, Testreszabó, opciók | `hpv_client`…, `hpv_<kulcs>` theme_mod, `hpv_demo_imported` | `hpvf_…` |
| Admin oldal, űrlap-műveletek | `themes.php?page=hpv-setup`, `admin-post … hpv_strategy_call` | `hpvf-setup`, `hpvf_strategy_call` |
| WP-CLI | `wp hpv import-demo`, `wp hpv checklist` | `wp hpvf import-demo`, `wp hpvf checklist` |

Változatlan: a szövegtartomány (`hpv`, a hlprv-ben nincs ilyen), a blokkok (`hpv/photo`, `hpv/case-grid`…) és a
blokkminták (`hpv/page-home`…) neve, a JS globálisok (`hpvConfig`, `hpvTrack`). Ha az 1.0.0 már élesben futott,
a régi leadek (`hpv_lead`), a Testreszabó beállításai és az importált oldalak meta-adatai nem jönnek át maguktól
(új telepítésnél nincs teendő).

A HelloProVision rendszer nevei (`hpv_leads_send()`, `hpv_leads_map()`, `HPV_CRM_URL`, `HPV_BRIDGE_SECRET`,
`hpv/v1/bridge/lead`) szándékosan maradtak `hpv_`-sek: azokat a téma csak hívja.
