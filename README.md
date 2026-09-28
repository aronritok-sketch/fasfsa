# Áron Ritók-Filip – founder site (HelloProVision)

| Mappa | Mi van benne |
| --- | --- |
| `theme/helloprovision-founder/` | **A kész WordPress téma** (Wheatpaste dizájn, blokkminták, leadkezelő + HelloProVision CRM-bekötés, SEO, séma, GA4). Leírás: [`theme/helloprovision-founder/README.md`](theme/helloprovision-founder/README.md) |
| `theme/dist/helloprovision-founder-1.1.0.zip` | Feltölthető téma-csomag (Megjelenés → Témák → Téma feltöltése) |
| `tests/crm-bridge.php` | A CRM-híd tesztje WordPress nélkül: `php tests/crm-bridge.php` |
| `prototype/` | A jóváhagyott HTML-prototípus: a dizájn és a szövegek egyetlen forrása ([`prototype/README.md`](prototype/README.md)) |
| `tools/build_theme.py` | A prototípusból generálja a téma CSS-ét, blokkmintáit és a demo-manifestet, `--zip`-pel a csomagot is |

```bash
python3 prototype/build.py          # prototípus → prototype/dist
python3 tools/build_theme.py --zip  # prototípus → téma CSS + minták + ZIP
```

Ez egy **további** oldal (Áron személyes márkája), nem váltja ki a helloprovision.com-ot. Ezért a téma mappája
`helloprovision-founder` (a `helloprovision` az élő helloprovision.com témájának neve, a feltöltés felülírná), és
1.1.0 óta minden PHP-neve `hpvf_` / `HPVF_` előtagú, a REST névtere `hpvf/v1`, hogy a HelloProVision rendszer
bővítményeivel (beállító varázsló, leads mu-plugin, CRM) egy WordPressben se ütközzön. A leadek a HelloProVision
CRM-be is átmennek: lásd a téma README „CRM-bekötés” fejezetét.
