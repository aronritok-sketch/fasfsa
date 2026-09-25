# Áron Ritók-Filip – founder site (HelloProVision)

| Mappa | Mi van benne |
| --- | --- |
| `theme/helloprovision/` | **A kész WordPress téma** (Wheatpaste dizájn, blokkminták, leadkezelő, SEO, séma, GA4). Leírás: [`theme/helloprovision/README.md`](theme/helloprovision/README.md) |
| `theme/dist/helloprovision-1.0.0.zip` | Feltölthető téma-csomag (Megjelenés → Témák → Téma feltöltése) |
| `prototype/` | A jóváhagyott HTML-prototípus: a dizájn és a szövegek egyetlen forrása ([`prototype/README.md`](prototype/README.md)) |
| `tools/build_theme.py` | A prototípusból generálja a téma CSS-ét, blokkmintáit és a demo-manifestet, `--zip`-pel a csomagot is |

```bash
python3 prototype/build.py          # prototípus → prototype/dist
python3 tools/build_theme.py --zip  # prototípus → téma CSS + minták + ZIP
```
