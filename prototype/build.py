#!/usr/bin/env python3
"""Build the static prototype.

    python3 prototype/build.py

Outputs
  prototype/dist/             multi-page static site with the blueprint's URL structure
                              (relative links, so it also works when opened from disk)
  prototype/dist/preview.html one-file preview of every page (hash-routed), for sharing

Source
  prototype/src/layout.html   shared header, footer and <head>
  prototype/src/pages/*.html  page content, with a front-matter comment at the top
  prototype/src/assets/       css, js, fonts
"""
import html
import json
import re
import shutil
from pathlib import Path

# Domain is still an open input in the blueprint (helloprovision.com vs a personal domain).
SITE_URL = "https://example.com"

HERE = Path(__file__).resolve().parent
SRC = HERE / "src"
DIST = HERE / "dist"

GOOGLE_FONTS = ("https://fonts.googleapis.com/css2?family=Martian+Mono:wdth,wght@75..112.5,100..800"
                "&family=IBM+Plex+Mono:ital,wght@0,400;0,500;0,600;1,400&display=swap")


# ---------------------------------------------------------------- pages

def parse_page(path: Path) -> dict:
    text = path.read_text(encoding="utf-8")
    m = re.match(r"\s*<!--(.*?)-->\s*", text, re.S)
    meta = {}
    for line in m.group(1).strip().splitlines():
        key, _, value = line.partition(":")
        meta[key.strip()] = value.strip()
    meta["content"] = text[m.end():]
    meta["source"] = path.name
    return meta


def load_pages() -> list:
    pages = [parse_page(p) for p in sorted((SRC / "pages").glob("*.html"))]
    pages.sort(key=lambda p: (p["path"] != "/", p["path"]))
    return pages


def token(path: str) -> str:
    """'/services/local-seo/' -> 'services-local-seo', '/' -> 'home'."""
    return path.strip("/").replace(".html", "").replace("/", "-") or "home"


def out_file(path: str) -> Path:
    if path.endswith(".html"):
        return DIST / path.lstrip("/")
    return DIST / path.lstrip("/") / "index.html"


def root_prefix(path: str) -> str:
    depth = len([s for s in path.strip("/").split("/") if s]) if not path.endswith(".html") else 0
    return "../" * depth or "./"


def crumbs_of(page: dict) -> list:
    items = [("Home", "/")]
    for part in filter(None, (c.strip() for c in page.get("crumbs", "").split(";"))):
        name, _, url = part.partition("=")
        items.append((name.strip(), url.strip() or page["path"]))
    return items


# ---------------------------------------------------------------- images

# HelloProVision projects. Screenshots/photos go in src/assets/img/projects/<slug>-<n>.(jpg|png|webp)
# and replace the placeholder frames automatically on the next build.
PROJECTS = {
    "imperial-kitchens": "Imperial Kitchens",
    "factory-fm": "Factory FM",
    "raven-protect": "Raven Protect",
    "tank-empire-budapest": "Tank Empire Budapest",
    "mandala": "Mandala",
    "kaeri": "Kaeri",
}
IMG_EXT = (".avif", ".webp", ".jpg", ".jpeg", ".png")
RATIOS = {"portrait", "land", "wide", "square"}
XTAG = re.compile(r'<x-(photo|project)\b([^>]*)>\s*</x-\1>', re.S)
ATTR = re.compile(r'([\w-]+)="([^"]*)"')


def parse_attrs(raw: str) -> dict:
    """key="value" pairs plus bare boolean attributes (dark, eager)."""
    attrs = dict(ATTR.findall(raw))
    for flag in re.findall(r'(?:^|\s)([\w-]+)(?=\s|$)', ATTR.sub(" ", raw)):
        attrs[flag] = ""
    return attrs


def find_image(base: str):
    for ext in IMG_EXT:
        f = SRC / "assets/img" / (base + ext)
        if f.exists():
            return "assets/img/" + base + ext
    return None


def used_images(pages: list) -> list:
    found = set()
    for page in pages:
        for kind, attrs in XTAG.findall(page["content"]):
            a = parse_attrs(attrs)
            base = a.get("src") or f'projects/{a.get("slug")}-{a.get("n", "1")}'
            img = find_image(base)
            if img:
                found.add(img)
    return sorted(found)


def render_photos(markup: str, root: str) -> str:
    def repl(m):
        kind, a = m.group(1), parse_attrs(m.group(2))
        ratio = a.get("ratio", "land" if kind == "project" else "portrait")
        ratio = ratio if ratio in RATIOS else "land"
        load = 'loading="eager" fetchpriority="high"' if "eager" in a else 'loading="lazy"'
        if kind == "project":
            slug = a["slug"]
            name = PROJECTS[slug]
            n = a.get("n", "1")
            alt = html.escape(a.get("alt") or f"{name} — project by HelloProVision", quote=True)
            img = find_image(f"projects/{slug}-{n}")
            tone = (list(PROJECTS).index(slug) + int(n)) % 6 + 1
            if img:
                view = f'<img src="{root}{img}" alt="{alt}" {load} decoding="async">'
            else:
                view = (f'<div class="shot__ph" aria-hidden="true"><span class="shot__nav"><i></i><i></i><i></i></span>'
                        f'<span class="shot__title">{html.escape(name)}</span>'
                        f'<span class="shot__lines"><i></i><i></i><i></i></span><span class="shot__btn"></span></div>')
            role = '' if img else f' role="img" aria-label="{alt}"'
            return (f'<figure class="shot shot--{ratio} tone-{tone}"{role}>'
                    f'<div class="shot__bar" aria-hidden="true"><i></i><i></i><i></i><span>{html.escape(name)}</span></div>'
                    f'<div class="shot__view">{view}</div></figure>')
        # photo of Áron / on-location photography
        alt = html.escape(a.get("alt", ""), quote=True)
        img = find_image(a["src"])
        dark = " photo--dark" if "dark" in a else ""
        if img:
            return f'<img class="photo-img photo-img--{ratio}" src="{root}{img}" alt="{alt}" {load} decoding="async">'
        left, _, right = a.get("note", "Photo|Shoot pending").partition("|")
        return (f'<div class="photo photo--{ratio}{dark}" role="img" aria-label="{alt}">'
                f'<span class="photo__note"><span>{html.escape(left)}</span><span>{html.escape(right)}</span></span></div>')
    return XTAG.sub(repl, markup)


# ---------------------------------------------------------------- schema

def schema(page: dict) -> str:
    url = SITE_URL + page["path"]
    person = {
        "@type": "Person",
        "@id": SITE_URL + "/#person",
        "name": "Áron Ritók-Filip",
        "jobTitle": "Digital Growth Strategist",
        "url": SITE_URL + "/about/",
        "worksFor": {"@id": SITE_URL + "/#org"},
        "knowsAbout": ["Digital growth strategy", "Website design", "Local SEO", "Conversion optimization"],
        "sameAs": ["https://www.linkedin.com/in/[profile]"],
    }
    org = {
        "@type": "ProfessionalService",
        "@id": SITE_URL + "/#org",
        "name": "HelloProVision",
        "founder": {"@id": SITE_URL + "/#person"},
        "url": SITE_URL + "/",
        "areaServed": [
            {"@type": "City", "name": "Fort Myers"},
            {"@type": "City", "name": "Naples"},
            {"@type": "City", "name": "Cape Coral"},
            {"@type": "State", "name": "Florida"},
        ],
    }
    website = {"@type": "WebSite", "@id": SITE_URL + "/#website", "url": SITE_URL + "/",
               "name": "Áron Ritók-Filip", "publisher": {"@id": SITE_URL + "/#person"}}
    webpage = {"@type": "ProfilePage" if page.get("type") == "about" else "WebPage",
               "@id": url + "#webpage", "url": url, "name": page["title"],
               "description": page["description"], "isPartOf": {"@id": SITE_URL + "/#website"}}
    if page.get("type") == "about":
        webpage["mainEntity"] = {"@id": SITE_URL + "/#person"}
    graph = [person, org, website, webpage]

    if page["path"] != "/":
        graph.append({
            "@type": "BreadcrumbList",
            "@id": url + "#breadcrumbs",
            "itemListElement": [
                {"@type": "ListItem", "position": i + 1, "name": name, "item": SITE_URL + link}
                for i, (name, link) in enumerate(crumbs_of(page))
            ],
        })
    if page.get("type") == "service":
        graph.append({"@type": "Service", "@id": url + "#service", "name": page["service"],
                      "serviceType": page["service"], "url": url,
                      "provider": {"@id": SITE_URL + "/#person"}, "areaServed": org["areaServed"]})
    if page.get("type") == "article":
        graph.append({"@type": "BlogPosting", "@id": url + "#article", "headline": page["title"].split(" — ")[0],
                      "description": page["description"], "datePublished": page.get("article_date"),
                      "author": {"@id": SITE_URL + "/#person"}, "publisher": {"@id": SITE_URL + "/#person"},
                      "mainEntityOfPage": {"@id": url + "#webpage"}})
    data = {"@context": "https://schema.org", "@graph": graph}
    return json.dumps(data, ensure_ascii=False, indent=1).replace("</", "<\\/")


# ---------------------------------------------------------------- link rewriting

HREF = re.compile(r'href="(/(?!/)[^"#]*)"')


def rel_links(markup: str, root: str) -> str:
    def repl(m):
        p = m.group(1)
        target = p.lstrip("/")
        if not p.endswith(".html"):
            target += "index.html"
        return f'href="{root}{target}"'
    return HREF.sub(repl, markup)


def hash_links(markup: str) -> str:
    return HREF.sub(lambda m: f'href="#{token(m.group(1))}"', markup)


def mark_nav(markup: str, nav: str) -> str:
    if not nav:
        return markup
    return markup.replace(f'data-nav="{nav}"', f'data-nav="{nav}" aria-current="page"')


# ---------------------------------------------------------------- build: static site

def build_site(pages: list, layout: str) -> None:
    if DIST.exists():
        shutil.rmtree(DIST)
    shutil.copytree(SRC / "assets", DIST / "assets")
    for page in pages:
        root = root_prefix(page["path"])
        scripts = "".join(
            f'<script src="{root}assets/js/{s.strip()}.js" defer></script>\n'
            for s in page.get("scripts", "").split(",") if s.strip())
        doc = layout
        values = {
            "title": html.escape(page["title"], quote=True),
            "description": html.escape(page["description"], quote=True),
            "canonical": SITE_URL + page["path"],
            "robots": '<meta name="robots" content="noindex, follow">\n' if page.get("noindex") else "",
            "og_type": page.get("og_type", "website"),
            "schema": schema(page),
            "slug": token(page["path"]),
            "page_type": page.get("type", "page"),
            "scripts": scripts,
            "content": page["content"],
            "root": root,
        }
        # content last-but-one so its text is never re-scanned for placeholders
        for key in ["title", "description", "canonical", "robots", "og_type", "schema", "slug",
                    "page_type", "scripts", "root"]:
            doc = doc.replace("{{" + key + "}}", values[key])
        doc = doc.replace("{{content}}", render_photos(values["content"], root))
        doc = mark_nav(rel_links(doc, root), page.get("nav", ""))
        target = out_file(page["path"])
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(doc, encoding="utf-8")


# ---------------------------------------------------------------- build: one-file preview

ROUTER = r"""
document.body.setAttribute('data-mode', 'bundle');
(function () {
  var pages = Array.prototype.slice.call(document.querySelectorAll('[data-route]'));
  var byId = {}; pages.forEach(function (p) { byId[p.getAttribute('data-route')] = p; });
  function go(scroll) {
    var id = location.hash.replace('#', '');
    if (!byId[id]) { if (id && document.getElementById(id)) return; id = 'home'; }
    var page = byId[id];
    pages.forEach(function (p) { p.hidden = p !== page; });
    document.title = page.getAttribute('data-title');
    document.body.setAttribute('data-page-type', page.getAttribute('data-type'));
    document.querySelectorAll('.nav__list a').forEach(function (a) {
      if (a.getAttribute('data-nav') === page.getAttribute('data-nav')) a.setAttribute('aria-current', 'page');
      else a.removeAttribute('aria-current');
    });
    if (window.hpvCountTbd) window.hpvCountTbd(page);
    if (scroll) window.scrollTo(0, 0);
  }
  window.addEventListener('hashchange', function () { go(true); });
  go(false);
  window.addEventListener('load', function () { if (window.hpvCountTbd) window.hpvCountTbd(byId[location.hash.slice(1)] || byId.home); });
})();
"""


def build_preview(pages: list, layout: str) -> None:
    css = (SRC / "assets/css/main.css").read_text(encoding="utf-8")
    css = re.sub(r"@font-face\s*{[^}]*}\s*", "", css)
    js = "\n".join((SRC / f"assets/js/{n}.js").read_text(encoding="utf-8") for n in ("site", "forms", "scorecard"))

    body = re.search(r"<body[^>]*>(.*)</body>", layout, re.S).group(1)
    body = re.sub(r"<script.*?</script>\s*", "", body, flags=re.S).replace("{{scripts}}", "")
    # Section ids like "hero" or "faq" repeat across pages; prefix only those so ids stay unique.
    seen = {}
    for page in pages:
        for sid in set(re.findall(r'<section[^>]*? id="([^"]+)"', page["content"])):
            seen[sid] = seen.get(sid, 0) + 1
    routes = []
    for page in pages:
        tok = token(page["path"])
        content = re.sub(r'(<section[^>]*?) id="([^"]+)"',
                         lambda m: f'{m.group(1)} id="{tok}-{m.group(2)}"' if seen[m.group(2)] > 1 else m.group(0),
                         page["content"])
        content = render_photos(content, "")
        routes.append(
            f'<div data-route="{tok}" data-title="{html.escape(page["title"], quote=True)}" '
            f'data-type="{page.get("type", "page")}" data-nav="{page.get("nav", "")}"'
            f'{"" if tok == "home" else " hidden"}>\n{content}\n</div>')
    body = body.replace("{{content}}", "\n".join(routes))
    body = hash_links(body)

    out = (
        "<meta charset=\"utf-8\">\n<title>Áron Ritók-Filip Site</title>\n"
        '<meta name="description" content="Clickable prototype of the Áron Ritók-Filip founder website.">\n'
        '<link rel="preconnect" href="https://fonts.googleapis.com">\n'
        '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>\n'
        f'<link rel="stylesheet" href="{GOOGLE_FONTS}">\n'
        f"<style>\n{css}\n</style>\n"
        f"{body}\n"
        f"<script>\n{ROUTER}\n{js}\n</script>\n"
    )
    (DIST / "preview.html").write_text(out, encoding="utf-8")


def main() -> None:
    layout = (SRC / "layout.html").read_text(encoding="utf-8")
    pages = load_pages()
    build_site(pages, layout)
    build_preview(pages, layout)
    print(f"Built {len(pages)} pages -> {DIST.relative_to(HERE.parent)}/ (+ preview.html)")
    for p in pages:
        print(f"  {p['path']:<42} {p['source']}")


if __name__ == "__main__":
    main()
