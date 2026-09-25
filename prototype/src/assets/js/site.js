/* Nav, header state, reveal, analytics hooks, prototype fact marks. No dependencies. */
(function () {
  'use strict';
  var doc = document, root = doc.documentElement;
  window.dataLayer = window.dataLayer || [];
  function track(event, params) { window.dataLayer.push(Object.assign({ event: event }, params || {})); }
  window.hpvTrack = track;

  /* Resolve a site path ("/insights/") for the static build or the one-file preview */
  window.hpvUrl = function (path) {
    var body = doc.body;
    if (body.getAttribute('data-mode') === 'bundle') return '#' + (path.replace(/^\/|\/$/g, '').replace(/\//g, '-') || 'home');
    return (body.getAttribute('data-root') || '/') + path.replace(/^\//, '') + (body.getAttribute('data-root') ? 'index.html' : '');
  };

  /* Case study filter by industry */
  var caseList = doc.getElementById('case-list');
  if (caseList) {
    doc.querySelectorAll('input[name="industry"]').forEach(function (r) {
      r.addEventListener('change', function () {
        var shown = 0;
        caseList.querySelectorAll('[data-industry]').forEach(function (c) {
          var on = r.value === 'all' || c.dataset.industry === r.value;
          c.hidden = !on; if (on) shown++;
        });
        doc.getElementById('case-empty').hidden = shown > 0;
      });
    });
  }

  /* Header gains a 1px stone line on scroll */
  var header = doc.getElementById('site-header');
  function onScroll() { if (header) header.classList.toggle('is-scrolled', window.scrollY > 8); }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* Full-screen mobile menu */
  var menu = doc.getElementById('mobile-menu');
  var openBtn = doc.getElementById('nav-toggle');
  var closeBtn = doc.getElementById('nav-close');
  function setMenu(open) {
    if (!menu) return;
    menu.hidden = !open;
    openBtn.setAttribute('aria-expanded', String(open));
    root.style.overflow = open ? 'hidden' : '';
    (open ? closeBtn : openBtn).focus();
  }
  if (openBtn) openBtn.addEventListener('click', function () { setMenu(true); });
  if (closeBtn) closeBtn.addEventListener('click', function () { setMenu(false); });
  if (menu) menu.addEventListener('click', function (e) { if (e.target.closest('a')) setMenu(false); });
  doc.addEventListener('keydown', function (e) { if (e.key === 'Escape' && menu && !menu.hidden) setMenu(false); });

  /* Reveal: content is visible at rest; the fade-up plays just before it scrolls into view. */
  var reveal = doc.querySelectorAll('.section-head, .block, .service-card, .case-card, .work-item, .insight-card, .stat, .process__step, .steps li, .photo, .photo-img, .fit__col, .principles li');
  if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var fold = window.innerHeight;
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('reveal-run'); io.unobserve(en.target); }
      });
    }, { rootMargin: '0px 0px 12% 0px' });
    reveal.forEach(function (el) { if (el.getBoundingClientRect().top > fold) io.observe(el); });
  }

  /* GA4-ready events (blueprint §10) */
  doc.addEventListener('click', function (e) {
    var a = e.target.closest('a, button');
    if (!a) return;
    var type = doc.body.getAttribute('data-page-type') || '';
    if (a.matches('.btn, .arrow-link')) {
      var sec = a.closest('section, header, footer, .mobile-menu');
      track('cta_click', { cta_text: a.textContent.trim().replace(/\s*→$/, ''), cta_location: (sec && (sec.id || sec.className.split(' ')[0])) || 'page', page_type: type });
    }
    if (a.dataset.track) track(a.dataset.track, { page_type: type });
  });

  /* Prototype only: count and toggle the "fact to verify" marks */
  var marks = doc.querySelectorAll('main .tbd, footer .tbd');
  var count = doc.getElementById('tbd-count');
  var toggle = doc.getElementById('tbd-toggle');
  window.hpvCountTbd = function (scope) {
    var n = (scope || doc).querySelectorAll('.tbd').length;
    if (count) count.textContent = n;
  };
  if (count) count.textContent = marks.length;
  /* Local time in Fort Myers (header) */
  var clock = doc.getElementById('local-time');
  if (clock && window.Intl) {
    var fmt = new Intl.DateTimeFormat('en-US', { timeZone: 'America/New_York', hour: '2-digit', minute: '2-digit' });
    var tick = function () { clock.textContent = fmt.format(new Date()); };
    tick(); setInterval(tick, 30000);
  }

  if (toggle) toggle.addEventListener('click', function () {
    var hidden = root.classList.toggle('hide-tbd');
    toggle.textContent = hidden ? 'Show marks' : 'Hide marks';
    toggle.setAttribute('aria-pressed', String(!hidden));
  });
})();
