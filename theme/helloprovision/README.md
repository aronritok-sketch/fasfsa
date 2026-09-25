# HelloProVision Founder – WordPress téma

Áron Ritók-Filip személyes márkaoldalának témája, a jóváhagyott „Wheatpaste” (utcai plakát) dizájnban.
Önálló klasszikus téma blokkszerkesztővel. **Nem kell hozzá plugin**: a leadkezelő, az SEO, a séma,
az analitika és a Growth Scorecard is a témában van.

- WordPress 6.5+ (6.8-on tesztelve), PHP 8.0+
- Betűk (Anton, Archivo, Caveat Brush) a témában, OFL licenc – nincs külső betűtöltés
- Minden oldal blokkokból áll, szerkeszthető a Gutenbergben

## Telepítés (5 perc)

1. **Megjelenés → Témák → Új hozzáadása → Téma feltöltése**, válaszd a `helloprovision.zip`-et, majd *Aktiválás*.
2. **Megjelenés → HelloProVision setup → Import pages & menus.**
   Létrehozza az összes oldalt (főoldal, szolgáltatások, rólam, esettanulmányok, insights, stratégiai hívás,
   köszönőoldal, scorecard, jogi oldalak), a 4 menüt, egy esettanulmányt és egy cikket, és beállítja a
   kezdőlapot, a blogoldalt és a permalinkeket (`/insights/%postname%/`).
   WP-CLI-ből: `wp hpv import-demo` (felülírás: `--force`), ellenőrzőlista: `wp hpv checklist`.
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
| `inc/leads.php`, `inc/lead-admin.php` | REST végpontok (`hpv/v1/lead`, `hpv/v1/scorecard`), tárolás, e-mailek, admin |
| `inc/seo.php`, `inc/schema.php`, `inc/tracking.php` | SEO, JSON-LD, GA4/GTM + consent |
| `inc/demo-import.php` | Setup oldal, importáló, launch checklist, WP-CLI parancsok |
| `assets/js/editor/blocks.js` | Szerkesztő: blokkok, formátumok, oldalsó panelek (build nélküli JS) |
| `template-parts/` | Stratégiai hívás űrlap, scorecard, wordmark |

Hookok: `hpv_lead_created( $lead_id, $type, $data )`, `hpv_lead_stage_changed( $lead_id, $new, $old )`,
szűrők: `hpv_schema_graph`, `hpv_should_track`. Éles indexelés nem-production környezetben:
`define( 'HPV_ALLOW_INDEXING', true );`.
