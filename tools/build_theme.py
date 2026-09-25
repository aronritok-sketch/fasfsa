#!/usr/bin/env python3
"""Build the WordPress theme's generated parts from the approved HTML prototype.

    python3 tools/build_theme.py          # CSS + patterns
    python3 tools/build_theme.py --zip    # … and theme/dist/helloprovision-<version>.zip for upload

The prototype (prototype/src) is the single source of truth for design and copy.
This script:

  1. builds theme/helloprovision/assets/css/main.css
       = prototype main.css (without @font-face; fonts come from theme.json)
       + theme/helloprovision/assets/css/src/wordpress.css
  2. converts every prototype page into Gutenberg block markup and writes
       patterns/page-*.php      full-page patterns (also used by the demo importer)
       patterns/section-*.php   one pattern per homepage section
       patterns/*.php           a few special patterns used by templates
  3. writes patterns/demo.json, the manifest the demo importer reads
       (page tree, titles, SEO fields, page roles, case study facts).

Conversion rules: sections/divs become core/group (tag and classes kept), headings,
paragraphs, lists, quotes, details and separators become their core blocks, and
interactive or data-driven parts become the theme's dynamic hpv/* blocks.
Internal links become home_url() calls so patterns work on any domain.
"""
import json
import re
import sys
import zipfile
from pathlib import Path

from bs4 import BeautifulSoup, NavigableString, Comment, Tag

ROOT = Path(__file__).resolve().parent.parent
PROTO = ROOT / "prototype" / "src"
THEME = ROOT / "theme" / "helloprovision"
PATTERNS = THEME / "patterns"

PROJECTS = {
    "imperial-kitchens": "Imperial Kitchens",
    "factory-fm": "Factory FM",
    "raven-protect": "Raven Protect",
    "tank-empire-budapest": "Tank Empire Budapest",
    "mandala": "Mandala",
    "kaeri": "Kaeri",
}

INLINE = {"a", "span", "mark", "em", "strong", "small", "time", "b", "i", "br", "abbr", "code", "sup", "sub"}
GROUP_TAGS = {"section", "article", "aside", "header", "footer", "div", "main"}

# --------------------------------------------------------------------------- helpers


def attrs_json(d):
    d = {k: v for k, v in d.items() if v not in (None, "", False)}
    return (" " + json.dumps(d, ensure_ascii=False, separators=(",", ":"))) if d else ""


def cls(el):
    return " ".join(el.get("class", []))


def php_links(html):
    """href="/path/" -> href="<?php echo esc_url( home_url( '/path/' ) ); ?>"."""
    return re.sub(r'href="(/(?!/)[^"]*)"', lambda m: "href=\"<?php echo esc_url( home_url( '%s' ) ); ?>\"" % m.group(1), html)


def inner_html(el):
    return "".join(str(c) for c in el.contents).strip()


def is_inline_only(el):
    for c in el.children:
        if isinstance(c, Comment):
            continue
        if isinstance(c, NavigableString):
            continue
        if c.name not in INLINE:
            return False
        if c.name in INLINE and not is_inline_only(c):
            return False
    return True


def clean_inline(html):
    # Inline SVGs are drawn by CSS now; collapse whitespace.
    html = re.sub(r"<svg.*?</svg>", "", html, flags=re.S)
    return re.sub(r"\s+", " ", html).strip()


def block(name, attrs=None, inner=None):
    a = attrs_json(attrs or {})
    if inner is None:
        return f"<!-- wp:{name}{a} /-->"
    return f"<!-- wp:{name}{a} -->\n{inner}\n<!-- /wp:{name} -->"


def paragraph(html, class_name=""):
    c = f' class="{class_name}"' if class_name else ""
    return block("paragraph", {"className": class_name}, f"<p{c}>{clean_inline(html)}</p>")


def heading(el):
    level = int(el.name[1])
    c = cls(el)
    classes = " ".join(["wp-block-heading"] + ([c] if c else []))
    attrs = {"className": c}
    if level != 2:
        attrs = {"level": level, **attrs}
    return block("heading", attrs, f'<h{level} class="{classes}">{clean_inline(inner_html(el))}</h{level}>')


def group(tag, class_name, anchor, inner):
    tag = tag if tag in {"section", "article", "aside", "header", "footer", "div", "main"} else "div"
    attrs = {}
    if tag != "div":
        attrs["tagName"] = tag
    attrs["className"] = class_name
    if anchor:
        attrs["anchor"] = anchor
    classes = " ".join(["wp-block-group"] + ([class_name] if class_name else []))
    idattr = f' id="{anchor}"' if anchor else ""
    return block("group", attrs, f'<{tag} class="{classes}"{idattr}>{inner}</{tag}>')


def list_block(el):
    ordered = el.name == "ol"
    c = cls(el)
    items = []
    for li in el.find_all("li", recursive=False):
        items.append(block("list-item", {}, f"<li>{clean_inline(inner_html(li))}</li>"))
    classes = " ".join(["wp-block-list"] + ([c] if c else []))
    tag = "ol" if ordered else "ul"
    return block("list", {"ordered": ordered, "className": c}, f'<{tag} class="{classes}">' + "\n".join(items) + f"</{tag}>")


# --------------------------------------------------------------------------- converter


class Converter:
    def __init__(self, page_key):
        self.page = page_key
        self.scorecard_done = False

    def children(self, el):
        out = []
        for c in el.children:
            b = self.node(c)
            if b:
                out.append(b)
        return "\n".join(out)

    def node(self, el):
        if isinstance(el, Comment):
            return ""
        if isinstance(el, NavigableString):
            text = str(el).strip()
            return paragraph(text) if text else ""
        if not isinstance(el, Tag):
            return ""

        name, c, ident = el.name, el.get("class", []), el.get("id", "")

        # ---- custom placeholders -> dynamic blocks
        if name == "x-photo":
            return block("hpv/photo", {
                "src": el.get("src", ""), "ratio": el.get("ratio", "portrait"), "alt": el.get("alt", ""),
                "note": el.get("note", ""), "dark": el.has_attr("dark"), "eager": el.has_attr("eager"),
            })
        if name == "x-project":
            slug, n = el.get("slug"), el.get("n", "1")
            return block("hpv/project", {
                "name": PROJECTS[slug], "src": f"projects/{slug}-{n}", "ratio": el.get("ratio", "land"),
                "tone": (list(PROJECTS).index(slug) + int(n)) % 6 + 1,  # same placeholder colour as the prototype
                "alt": el.get("alt", ""), "eager": el.has_attr("eager"),
            })

        # ---- things rendered elsewhere or replaced by dynamic blocks
        if name in ("noscript", "script", "style") or "tape" in c:
            return ""
        if name == "nav" and "breadcrumbs" in c:
            return block("hpv/breadcrumbs")
        if "ticker" in c:
            items = [li.get_text(strip=True) for li in el.select("ul.ticker__group")[0].find_all("li")]
            return block("hpv/ticker", {"items": "\n".join(items)})
        if "contact-lines" in c:
            return block("hpv/contact")
        if "form-panel" in c and el.find("form", id="call-form"):
            return block("hpv/strategy-call-form")
        if ident in ("scorecard-start", "scorecard-quiz", "scorecard-results"):
            if self.scorecard_done:
                return ""
            self.scorecard_done = True
            return block("hpv/scorecard")
        if name == "aside" and "snapshot" in c:
            return block("hpv/case-snapshot", {"className": " ".join(x for x in c if x != "snapshot")})
        if "stat" in c and el.find(string=re.compile("Headline result")):
            return block("hpv/case-result")
        if ident == "case-empty" or ("chips" in c and self.page == "case-studies"):
            return ""
        if "cases" in c:
            if self.page == "home":
                return block("hpv/case-grid", {"layout": "home", "count": 3, "upcoming": "Factory FM, Tank Empire Budapest"})
            return block("hpv/case-grid", {"layout": "hub", "count": 12, "filter": True,
                                           "upcoming": "Factory FM, Raven Protect, Tank Empire Budapest, Mandala, Kaeri"})
        if "insights" in c and self.page in ("home", "insights", "service-local-seo"):
            topic = ""
            sec = el.find_parent("section")
            if self.page == "insights" and sec is not None:
                topic = sec.get("id", "")
            if self.page == "service-local-seo":
                topic = "local-seo"
            return block("hpv/insights", {"topic": topic, "count": 3, "fill": True})

        # ---- text blocks
        if name in ("h1", "h2", "h3", "h4", "h5", "h6"):
            return heading(el)
        if name == "p":
            return paragraph(inner_html(el), cls(el))
        if name == "blockquote":
            return block("quote", {"className": cls(el)},
                         f'<blockquote class="wp-block-quote {cls(el)}">' + paragraph(inner_html(el)) + "</blockquote>")
        if name == "hr":
            return block("separator", {"className": cls(el)}, f'<hr class="wp-block-separator has-alpha-channel-opacity {cls(el)}"/>')
        if name == "details":
            summary = el.find("summary")
            rest = []
            for ch in el.children:
                if ch is summary or isinstance(ch, NavigableString) and not str(ch).strip():
                    continue
                if isinstance(ch, Tag) and ch.name == "div" and is_inline_only(ch):
                    rest.append(paragraph(inner_html(ch), cls(ch)))
                else:
                    rest.append(self.node(ch))
            return block("details", {}, f'<details class="wp-block-details"><summary>{clean_inline(inner_html(summary))}</summary>' + "\n".join(rest) + "</details>")
        if name in ("ul", "ol"):
            lis = el.find_all("li", recursive=False)
            if all(is_inline_only(li) for li in lis) and not ("process" in c or "steps" in c or "timeline" in c or "principles" in c):
                return list_block(el)
            parts = [group("div", cls(li), "", self.children(li)) if not is_inline_only(li)
                     else group("div", cls(li), "", paragraph(inner_html(li))) for li in lis]
            return group("div", cls(el), el.get("id", ""), "\n".join(parts))
        if name == "figcaption":
            return paragraph(inner_html(el), "testimonial__by")
        if name == "figure":
            return group("div", cls(el), ident, self.children(el))

        # ---- inline element standing alone at block level
        if name in INLINE:
            if name == "a":
                return paragraph(str(el))
            if name == "br":
                return ""
            return paragraph(inner_html(el), cls(el))

        # ---- containers
        if name in GROUP_TAGS or name == "nav":
            if "sticker" in c:
                return paragraph(inner_html(el), cls(el))
            if name == "div" and is_inline_only(el) and el.get_text(strip=True):
                return paragraph(inner_html(el), cls(el))
            return group(name, cls(el), ident, self.children(el))

        # ---- anything else: keep as raw HTML
        return block("html", {}, str(el))


# --------------------------------------------------------------------------- pages


def parse_page(path):
    text = path.read_text(encoding="utf-8")
    m = re.match(r"\s*<!--(.*?)-->\s*", text, re.S)
    meta = {}
    for line in m.group(1).strip().splitlines():
        k, _, v = line.partition(":")
        meta[k.strip()] = v.strip()
    return meta, text[m.end():]


def convert(html, page_key, skip_first_section=False):
    soup = BeautifulSoup(html, "html.parser")
    conv = Converter(page_key)
    tops = [n for n in soup.contents if isinstance(n, Tag) or (isinstance(n, NavigableString) and str(n).strip() and not isinstance(n, Comment))]
    if skip_first_section:
        tops = tops[1:]
    return "\n\n".join(filter(None, (conv.node(n) for n in tops)))


def php_header(title, slug, categories, description="", inserter=True, post_types=None):
    lines = [
        "<?php",
        "/**",
        f" * Title: {title}",
        f" * Slug: {slug}",
        f" * Categories: {categories}",
    ]
    if description:
        lines.append(f" * Description: {description}")
    if post_types:
        lines.append(f" * Post Types: {post_types}")
    if not inserter:
        lines.append(" * Inserter: no")
    lines += [
        " *",
        " * Generated by tools/build_theme.py from the HTML prototype. Edit the prototype and rebuild.",
        " *",
        " * @package HelloProVision",
        " */",
        "",
        "defined( 'ABSPATH' ) || exit;",
        "?>",
    ]
    return "\n".join(lines) + "\n"


def write_pattern(filename, title, slug, categories, content, **kw):
    PATTERNS.mkdir(parents=True, exist_ok=True)
    (PATTERNS / filename).write_text(php_header(title, slug, categories, **kw) + php_links(content) + "\n", encoding="utf-8")


# Page manifest: prototype source -> WordPress object.
PAGES = [
    # key, source, kind, path, title
    ("home", "home.html", "page", "home", "Home"),
    ("about", "about.html", "page", "about", "About"),
    ("services", "services.html", "page", "services", "Services"),
    ("service-website", "service-website.html", "page", "services/website-design-development", "Website Design & Development"),
    ("service-local-seo", "service-local-seo.html", "page", "services/local-seo", "Local SEO"),
    ("service-strategy", "service-strategy.html", "page", "services/digital-growth-strategy", "Digital Growth Strategy"),
    ("strategy-call", "strategy-call.html", "page", "strategy-call", "Book a Strategy Call"),
    ("strategy-call-thank-you", "strategy-call-thank-you.html", "page", "strategy-call/thank-you", "Thank You"),
    ("growth-scorecard", "growth-scorecard.html", "page", "growth-scorecard", "Growth Scorecard"),
    ("insights", "insights.html", "page", "insights", "Insights"),
    ("privacy-policy", "privacy-policy.html", "page", "privacy-policy", "Privacy Policy"),
    ("terms", "terms.html", "page", "terms", "Terms of Use"),
    ("accessibility", "accessibility.html", "page", "accessibility", "Accessibility Statement"),
    ("case-studies", "case-studies.html", "archive", "case-studies", "Case Studies"),
    ("404", "404.html", "template", "", "Page not found"),
]

PAGE_ROLES = {
    "about": "about", "services": "services_hub", "service-website": "service", "service-local-seo": "service",
    "service-strategy": "service", "strategy-call": "strategy_call", "strategy-call-thank-you": "thank_you",
    "growth-scorecard": "scorecard", "privacy-policy": "legal", "terms": "legal", "accessibility": "legal",
}


def main():
    # 1. CSS
    css = (PROTO / "assets/css/main.css").read_text(encoding="utf-8")
    css = re.sub(r"@font-face \{[^}]*\}\n", "", css)
    css = css.replace("Components read semantic tokens (--c-*); section scopes redefine them.",
                      "Components read semantic tokens (--c-*); section scopes redefine them.\n"
                      "   BUILT FILE: generated by tools/build_theme.py from prototype/src/assets/css/main.css\n"
                      "   + assets/css/src/wordpress.css. Fonts are declared in theme.json.")
    css += "\n" + (THEME / "assets/css/src/wordpress.css").read_text(encoding="utf-8")
    (THEME / "assets/css/main.css").write_text(css, encoding="utf-8")

    # 2. Patterns
    for old in PATTERNS.glob("*.php"):
        old.unlink()
    manifest = {"pages": [], "case_studies": [], "posts": []}

    for key, source, kind, path, title in PAGES:
        meta, html = parse_page(PROTO / "pages" / source)
        content = convert(html, key)
        slug = f"hpv/page-{key}"
        write_pattern(f"page-{key}.php", f"Page: {title}", slug, "hpv-pages", content,
                      description=meta.get("description", ""), inserter=kind == "page", post_types="page")
        if kind == "page":
            manifest["pages"].append({
                "path": path, "title": title, "pattern": slug,
                "seo_title": meta.get("title", ""), "seo_description": meta.get("description", ""),
                "page_type": PAGE_ROLES.get(key, ""), "service_name": meta.get("service", ""),
                "noindex": meta.get("noindex") == "true",
            })

    # Homepage sections as individual patterns.
    _, home = parse_page(PROTO / "pages" / "home.html")
    soup = BeautifulSoup(home, "html.parser")
    names = {
        "hero": "Hero: Nice website. Where are the calls?", "proof": "Proof strip", "problem": "Problem + symptoms",
        "approach": "Approach (transit line)", "services": "Services cards", "work": "Work wall",
        "case-studies": "Case studies", "founder": "Founder quote (dark)", "fit": "Who I work with",
        "process": "How working together starts", "scorecard": "Scorecard band (orange)", "insights": "Latest insights",
        "faq": "FAQ", "final-cta": "Final call to action (pink)",
    }
    conv = Converter("home")
    for sec in soup.find_all(["section", "div"], recursive=False):
        ident = sec.get("id") or ("ticker" if "ticker" in sec.get("class", []) else "")
        if not ident:
            continue
        content = conv.node(sec)
        conv.scorecard_done = False
        label = names.get(ident, "Ticker band" if ident == "ticker" else ident)
        write_pattern(f"section-{ident}.php", f"Section: {label}", f"hpv/section-{ident}", "hpv-sections", content)
        if ident == "scorecard":
            write_pattern("scorecard-band.php", "Scorecard band", "hpv/scorecard-band", "hpv-sections", content, inserter=False)

    # Case study body (the template renders the hero).
    meta, html = parse_page(PROTO / "pages" / "case-study-imperial-kitchens.html")
    body = convert(html, "case-study", skip_first_section=True)
    write_pattern("case-study-body.php", "Case study: full story layout", "hpv/case-study-body", "hpv-pages", body,
                  description="Hero image, snapshot, story, results, lessons, testimonial and call to action.", post_types="case_study")
    manifest["case_studies"].append({
        "slug": "imperial-kitchens",
        "title": "Imperial Kitchens: from referrals-only to a steady flow of qualified inquiries",
        "pattern": "hpv/case-study-body", "industry": "Home services",
        "excerpt": "Website strategy, local SEO and lead tracking for a premium remodeler.",
        "seo_title": meta.get("title", ""), "seo_description": meta.get("description", ""),
        "meta": {"hpv_client": "Imperial Kitchens", "hpv_location": "Fort Myers, Florida",
                 "hpv_services": "Website strategy & development, Local SEO, tracking",
                 "hpv_card_title": "From referrals-only to <span class=\"tbd\">[X]</span> qualified inquiries a month",
                 "hpv_featured": True},
    })

    # Sample article: only the body; single.php renders hero and sidebar.
    meta, html = parse_page(PROTO / "pages" / "article-traffic-no-calls.html")
    soup = BeautifulSoup(html, "html.parser")
    art = soup.select_one(".article-body")
    art_blocks = "\n\n".join(filter(None, (Converter("article").node(n) for n in art.children)))
    lead = soup.select_one("p.lead").get_text(" ", strip=True)
    write_pattern("article-traffic-no-calls.php", "Article: Why your website gets traffic but no calls",
                  "hpv/article-traffic-no-calls", "hpv-pages", art_blocks, inserter=False)
    manifest["posts"].append({
        "slug": "website-traffic-no-calls", "title": "Why your website gets traffic but no calls",
        "pattern": "hpv/article-traffic-no-calls", "excerpt": lead, "category": "websites",
        "seo_title": meta.get("title", ""), "seo_description": meta.get("description", ""), "date": meta.get("article_date", ""),
    })

    manifest["topics"] = [
        {"slug": "websites", "name": "Websites", "description": "Websites that sell: what makes a Florida service business website turn visits into calls."},
        {"slug": "local-seo", "name": "Local SEO", "description": "Getting found locally: map pack, Google Business Profile, reviews and local pages."},
        {"slug": "growth", "name": "Growth", "description": "Growth and conversion: follow-up, measurement and where marketing money actually pays back."},
    ]
    manifest["industries"] = ["Home services", "Hospitality & experiences", "Local businesses"]
    (PATTERNS / "demo.json").write_text(json.dumps(manifest, indent=1, ensure_ascii=False), encoding="utf-8")

    count = len(list(PATTERNS.glob("*.php")))
    print(f"CSS built ({len(css)//1024} KB). {count} patterns + demo.json written to {PATTERNS.relative_to(ROOT)}")

    if "--zip" in sys.argv:
        version = re.search(r"Version:\s*(\S+)", (THEME / "style.css").read_text(encoding="utf-8")).group(1)
        out = ROOT / "theme" / "dist" / f"helloprovision-{version}.zip"
        out.parent.mkdir(exist_ok=True)
        with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as z:
            for f in sorted(THEME.rglob("*")):
                if f.is_file() and not f.name.startswith("."):
                    z.write(f, Path("helloprovision") / f.relative_to(THEME))
        print(f"Packaged {out.relative_to(ROOT)} ({out.stat().st_size // 1024} KB)")


if __name__ == "__main__":
    main()
