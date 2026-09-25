/**
 * HelloProVision editor: dynamic blocks (server-rendered previews), text formats and
 * document sidebar panels. Plain ES5 on WordPress globals — no build step.
 */
(function (wp) {
  'use strict';
  if (!wp || !wp.blocks) return;

  var el = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var __ = wp.i18n.__;
  var be = wp.blockEditor;
  var c = wp.components;
  var SSR = wp.serverSideRender;
  var cfg = window.hpvEditor || { industries: [], topics: [] };

  var RATIOS = [
    { label: 'Portrait 4:5', value: 'portrait' },
    { label: 'Landscape 3:2', value: 'land' },
    { label: 'Wide 16:9', value: 'wide' },
    { label: 'Square 1:1', value: 'square' }
  ];

  /* ------------------------------------------------------------------ helpers */

  function preview(name, props) {
    var postId = wp.data.useSelect(function (s) { var e = s('core/editor'); return e ? e.getCurrentPostId() : 0; }, []);
    return el('div', be.useBlockProps({ className: 'hpv-ssr' }),
      el(SSR, { block: name, attributes: props.attributes, httpMethod: 'POST', urlQueryArgs: postId ? { post_id: postId } : {} }));
  }

  function imagePicker(props, label) {
    var a = props.attributes;
    return el(be.MediaUploadCheck, {},
      el(be.MediaUpload, {
        onSelect: function (m) { props.setAttributes({ imageId: m.id, alt: a.alt || m.alt || '' }); },
        allowedTypes: ['image'],
        value: a.imageId,
        render: function (o) {
          return el('div', { className: 'hpv-media' },
            el(c.Button, { variant: 'secondary', onClick: o.open }, a.imageId ? __('Replace image', 'hpv') : (label || __('Choose image', 'hpv'))),
            a.imageId ? el(c.Button, { variant: 'link', isDestructive: true, onClick: function () { props.setAttributes({ imageId: 0 }); } }, __('Remove', 'hpv')) : null
          );
        }
      })
    );
  }

  function block(name, def) {
    wp.blocks.registerBlockType(name, Object.assign({
      apiVersion: 3,
      category: 'hpv',
      supports: { html: false },
      save: function () { return null; }
    }, def));
  }

  /* ------------------------------------------------------------------ blocks */

  block('hpv/photo', {
    title: __('Photo (polaroid)', 'hpv'),
    description: __('A real photo, or a halftone placeholder until the shoot is done.', 'hpv'),
    icon: 'format-image',
    attributes: {
      imageId: { type: 'integer', default: 0 }, src: { type: 'string', default: '' },
      ratio: { type: 'string', default: 'portrait' }, alt: { type: 'string', default: '' },
      note: { type: 'string', default: 'Photo|Shoot pending' }, dark: { type: 'boolean', default: false },
      eager: { type: 'boolean', default: false }
    },
    edit: function (props) {
      var a = props.attributes, set = props.setAttributes;
      return el(Fragment, {},
        el(be.InspectorControls, {},
          el(c.PanelBody, { title: __('Photo', 'hpv') },
            imagePicker(props),
            el(c.SelectControl, { label: __('Crop', 'hpv'), value: a.ratio, options: RATIOS, onChange: function (v) { set({ ratio: v }); } }),
            el(c.TextControl, { label: __('Alt text', 'hpv'), help: __('Describe the photo, e.g. “Áron reviewing a sitemap with a client in Fort Myers”.', 'hpv'), value: a.alt, onChange: function (v) { set({ alt: v }); } }),
            el(c.TextControl, { label: __('Placeholder caption (left|right)', 'hpv'), value: a.note, onChange: function (v) { set({ note: v }); } }),
            el(c.ToggleControl, { label: __('Orange placeholder', 'hpv'), checked: a.dark, onChange: function (v) { set({ dark: v }); } }),
            el(c.ToggleControl, { label: __('Above the fold (load first)', 'hpv'), checked: a.eager, onChange: function (v) { set({ eager: v }); } })
          )
        ),
        preview('hpv/photo', props)
      );
    }
  });

  block('hpv/project', {
    title: __('Project screenshot', 'hpv'),
    description: __('A project screenshot taped to the wall, in a browser frame.', 'hpv'),
    icon: 'desktop',
    attributes: {
      name: { type: 'string', default: 'Project' }, imageId: { type: 'integer', default: 0 },
      src: { type: 'string', default: '' }, ratio: { type: 'string', default: 'land' },
      tone: { type: 'integer', default: 0 }, alt: { type: 'string', default: '' },
      eager: { type: 'boolean', default: false }
    },
    edit: function (props) {
      var a = props.attributes, set = props.setAttributes;
      return el(Fragment, {},
        el(be.InspectorControls, {},
          el(c.PanelBody, { title: __('Project', 'hpv') },
            el(c.TextControl, { label: __('Project name', 'hpv'), value: a.name, onChange: function (v) { set({ name: v }); } }),
            imagePicker(props, __('Choose screenshot', 'hpv')),
            el(c.SelectControl, { label: __('Crop', 'hpv'), value: a.ratio, options: RATIOS, onChange: function (v) { set({ ratio: v }); } }),
            el(c.RangeControl, { label: __('Placeholder colour (0 = automatic)', 'hpv'), min: 0, max: 6, value: a.tone, onChange: function (v) { set({ tone: v || 0 }); } }),
            el(c.TextControl, { label: __('Alt text', 'hpv'), value: a.alt, onChange: function (v) { set({ alt: v }); } })
          )
        ),
        preview('hpv/project', props)
      );
    }
  });

  block('hpv/case-grid', {
    title: __('Case studies grid', 'hpv'),
    description: __('Published case studies (featured first), plus “coming soon” cards for projects without a write-up.', 'hpv'),
    icon: 'portfolio',
    attributes: {
      layout: { type: 'string', default: 'hub' }, count: { type: 'integer', default: 12 },
      upcoming: { type: 'string', default: '' }, filter: { type: 'boolean', default: false }
    },
    edit: function (props) {
      var a = props.attributes, set = props.setAttributes;
      return el(Fragment, {},
        el(be.InspectorControls, {},
          el(c.PanelBody, { title: __('Case studies', 'hpv') },
            el(c.SelectControl, { label: __('Layout', 'hpv'), value: a.layout, options: [{ label: 'Hub (grid)', value: 'hub' }, { label: 'Home (swipe on mobile)', value: 'home' }], onChange: function (v) { set({ layout: v }); } }),
            el(c.RangeControl, { label: __('How many', 'hpv'), min: 1, max: 24, value: a.count, onChange: function (v) { set({ count: v }); } }),
            el(c.TextareaControl, { label: __('Projects coming soon (comma separated)', 'hpv'), value: a.upcoming, onChange: function (v) { set({ upcoming: v }); } }),
            el(c.ToggleControl, { label: __('Industry filter', 'hpv'), checked: a.filter, onChange: function (v) { set({ filter: v }); } })
          )
        ),
        preview('hpv/case-grid', props)
      );
    }
  });

  block('hpv/insights', {
    title: __('Insights cards', 'hpv'),
    description: __('Latest articles, optionally from one topic. Planned titles fill empty slots as “In writing”.', 'hpv'),
    icon: 'welcome-write-blog',
    attributes: { topic: { type: 'string', default: '' }, count: { type: 'integer', default: 3 }, fill: { type: 'boolean', default: true } },
    edit: function (props) {
      var a = props.attributes, set = props.setAttributes;
      return el(Fragment, {},
        el(be.InspectorControls, {},
          el(c.PanelBody, { title: __('Articles', 'hpv') },
            el(c.SelectControl, { label: __('Topic', 'hpv'), value: a.topic, options: cfg.topics, onChange: function (v) { set({ topic: v }); } }),
            el(c.RangeControl, { label: __('How many', 'hpv'), min: 1, max: 12, value: a.count, onChange: function (v) { set({ count: v }); } }),
            el(c.ToggleControl, { label: __('Fill with planned titles', 'hpv'), checked: a.fill, onChange: function (v) { set({ fill: v }); } })
          )
        ),
        preview('hpv/insights', props)
      );
    }
  });

  block('hpv/ticker', {
    title: __('Ticker band', 'hpv'),
    description: __('The tilted fluoro band that scrolls sideways.', 'hpv'),
    icon: 'megaphone',
    attributes: { items: { type: 'string', default: '' } },
    edit: function (props) {
      return el(Fragment, {},
        el(be.InspectorControls, {},
          el(c.PanelBody, { title: __('Ticker', 'hpv') },
            el(c.TextareaControl, { label: __('One item per line', 'hpv'), rows: 8, value: props.attributes.items, onChange: function (v) { props.setAttributes({ items: v }); } })
          )
        ),
        preview('hpv/ticker', props)
      );
    }
  });

  [
    ['hpv/breadcrumbs', __('Breadcrumbs', 'hpv'), 'arrow-right-alt', __('Home → section → page, built from the page hierarchy.', 'hpv')],
    ['hpv/contact', __('Phone & email', 'hpv'), 'phone', __('Shows the phone and email set in Appearance → Customize.', 'hpv')],
    ['hpv/case-snapshot', __('Case study snapshot', 'hpv'), 'list-view', __('Client, industry, location, services, timeline and website from the case study fields.', 'hpv')],
    ['hpv/case-result', __('Case study headline result', 'hpv'), 'chart-line', __('The headline number from the case study fields.', 'hpv')],
    ['hpv/strategy-call-form', __('Strategy call form', 'hpv'), 'calendar-alt', __('Two-step qualifying form. Leads go to the Leads screen.', 'hpv')],
    ['hpv/scorecard', __('Growth Scorecard', 'hpv'), 'forms', __('The 15-question scorecard. Results are saved as leads.', 'hpv')]
  ].forEach(function (b) {
    block(b[0], {
      title: b[1], icon: b[2], description: b[3],
      edit: function (props) { return preview(b[0], props); }
    });
  });

  /* ------------------------------------------------------------------ text formats */

  var rt = wp.richText;
  function format(name, title, tagName, className, icon) {
    rt.registerFormatType(name, {
      title: title, tagName: tagName, className: className,
      edit: function (props) {
        return el(be.RichTextToolbarButton, {
          icon: icon, title: title, isActive: props.isActive,
          onClick: function () { props.onChange(rt.toggleFormat(props.value, { type: name })); }
        });
      }
    });
  }
  format('hpv/highlight', __('Pink highlight', 'hpv'), 'mark', 'hl', 'admin-customizer');
  format('hpv/circle', __('Marker circle', 'hpv'), 'span', 'circled', 'marker');
  format('hpv/scribble', __('Scribble underline', 'hpv'), 'span', 'scribble', 'editor-underline');
  format('hpv/hand', __('Hand-written note', 'hpv'), 'span', 'hand', 'edit');
  format('hpv/tbd', __('Fact to verify', 'hpv'), 'span', 'tbd', 'flag');

  /* ------------------------------------------------------------------ document panels */

  var Panel = (wp.editor && wp.editor.PluginDocumentSettingPanel) || (wp.editPost && wp.editPost.PluginDocumentSettingPanel);
  if (!Panel || !wp.plugins) return;
  var useSelect = wp.data.useSelect, useEntityProp = wp.coreData.useEntityProp;

  function useMeta() {
    var type = useSelect(function (s) { return s('core/editor').getCurrentPostType(); }, []);
    var pair = useEntityProp('postType', type, 'meta');
    var meta = pair[0] || {}, setMeta = pair[1];
    return {
      type: type, meta: meta,
      set: function (key) { return function (v) { var n = {}; n[key] = v; setMeta(Object.assign({}, meta, n)); }; }
    };
  }

  function counter(text, max) {
    var n = (text || '').length;
    return n + ' / ' + max + (n > max ? ' — ' + __('too long for Google', 'hpv') : '');
  }

  wp.plugins.registerPlugin('hpv-seo', {
    render: function () {
      var m = useMeta();
      if (['page', 'post', 'case_study'].indexOf(m.type) === -1) return null;
      return el(Panel, { name: 'hpv-seo', title: __('Search & sharing', 'hpv') },
        el(c.TextControl, { label: __('SEO title', 'hpv'), help: counter(m.meta.hpv_seo_title, 60), value: m.meta.hpv_seo_title || '', onChange: m.set('hpv_seo_title') }),
        el(c.TextareaControl, { label: __('Meta description', 'hpv'), help: counter(m.meta.hpv_seo_description, 160), value: m.meta.hpv_seo_description || '', onChange: m.set('hpv_seo_description') }),
        el(c.ToggleControl, { label: __('Hide from search engines (noindex)', 'hpv'), checked: !!m.meta.hpv_noindex, onChange: m.set('hpv_noindex') }),
        m.type === 'page' ? el(c.SelectControl, {
          label: __('Page role', 'hpv'), value: m.meta.hpv_page_type || '',
          options: [
            { label: __('Standard page', 'hpv'), value: '' }, { label: __('About (ProfilePage schema)', 'hpv'), value: 'about' },
            { label: __('Service page (Service schema)', 'hpv'), value: 'service' }, { label: __('Services hub', 'hpv'), value: 'services_hub' },
            { label: __('Strategy call', 'hpv'), value: 'strategy_call' }, { label: __('Scorecard', 'hpv'), value: 'scorecard' },
            { label: __('Thank-you page', 'hpv'), value: 'thank_you' }, { label: __('Legal', 'hpv'), value: 'legal' }
          ],
          onChange: m.set('hpv_page_type')
        }) : null,
        m.type === 'page' && m.meta.hpv_page_type === 'service' ? el(c.TextControl, { label: __('Service name (schema)', 'hpv'), value: m.meta.hpv_service_name || '', onChange: m.set('hpv_service_name') }) : null
      );
    }
  });

  wp.plugins.registerPlugin('hpv-case', {
    render: function () {
      var m = useMeta();
      if (m.type !== 'case_study') return null;
      function field(key, label, help) { return el(c.TextControl, { label: label, help: help, value: m.meta[key] || '', onChange: m.set(key) }); }
      return el(Panel, { name: 'hpv-case', title: __('Case study facts', 'hpv') },
        el('p', {}, __('Every number needs a timeframe and a source, and the client’s written permission.', 'hpv')),
        field('hpv_client', __('Client', 'hpv')),
        field('hpv_location', __('Location', 'hpv'), 'e.g. Fort Myers, Florida'),
        field('hpv_services', __('Services delivered', 'hpv')),
        field('hpv_timeline', __('Timeline', 'hpv'), 'e.g. Jan 2026 – Apr 2026'),
        field('hpv_website', __('Client website', 'hpv')),
        field('hpv_card_title', __('Card headline', 'hpv'), __('Lead with the result, not the client name.', 'hpv')),
        field('hpv_result_value', __('Headline result: number', 'hpv'), 'e.g. +64%'),
        field('hpv_result_context', __('Headline result: context', 'hpv'), 'e.g. qualified inquiries per month, 6 months after launch'),
        field('hpv_result_source', __('Headline result: source', 'hpv'), 'e.g. GA4 + call tracking'),
        el(c.ToggleControl, { label: __('Featured (shown first)', 'hpv'), checked: !!m.meta.hpv_featured, onChange: m.set('hpv_featured') })
      );
    }
  });
})(window.wp);
