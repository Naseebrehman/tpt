/* ===========================================================================
   THE PIE TECHNOLOGIES — frontend interactions
   Custom cursor · magnetic buttons · preloader · navbar · marquee-free CSS
   Typed hero · particles · accordions · pinned "system" · counters · charts
   Portfolio filter · AJAX forms · AOS init (all with graceful fallbacks)
   =========================================================================== */
(function () {
  'use strict';

  var PIE = window.PIE || { base: '' };
  var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

  /* ---------------------------------------------------------------------
     Preloader
     --------------------------------------------------------------------- */
  function initPreloader() {
    var pre = document.getElementById('preloader');
    if (!pre) return;
    var logo = pre.querySelector('.preloader-logo');
    if (logo) {
      var text = logo.getAttribute('data-text') || logo.textContent;
      logo.textContent = '';
      text.split('').forEach(function (ch, i) {
        var s = document.createElement('span');
        s.textContent = ch === ' ' ? ' ' : ch;
        s.style.animationDelay = (i * 38) + 'ms';
        logo.appendChild(s);
      });
    }
    var started = Date.now();
    function hide() {
      var waited = Date.now() - started;
      var min = 1200;
      var delay = waited < min ? min - waited : 0;
      setTimeout(function () {
        pre.classList.add('fade-out');
        setTimeout(function () { if (pre.parentNode) pre.parentNode.removeChild(pre); }, 650);
      }, delay);
    }
    if (document.readyState === 'complete') hide();
    else window.addEventListener('load', hide);
    /* hard safety: never trap the visitor */
    setTimeout(function () { if (document.getElementById('preloader')) hide(); }, 4000);
  }

  /* ---------------------------------------------------------------------
     Custom cursor
     --------------------------------------------------------------------- */
  function initCursor() {
    var dot = document.getElementById('cursorDot');
    var ring = document.getElementById('cursorRing');
    if (!dot || !ring || !finePointer || prefersReduced) return;
    var mx = innerWidth / 2, my = innerHeight / 2, rx = mx, ry = my;
    document.addEventListener('mousemove', function (e) { mx = e.clientX; my = e.clientY; }, { passive: true });
    (function loop() {
      rx += (mx - rx) * 0.16;
      ry += (my - ry) * 0.16;
      dot.style.transform = 'translate(' + (mx - 4) + 'px,' + (my - 4) + 'px)';
      ring.style.transform = 'translate(' + (rx - 22) + 'px,' + (ry - 22) + 'px)';
      requestAnimationFrame(loop);
    })();
    var hoverSel = 'a, button, input, select, textarea, .acc-head, .filter-tab, [data-cursor]';
    document.addEventListener('mouseover', function (e) {
      if (e.target.closest && e.target.closest(hoverSel)) document.body.classList.add('cursor-hover');
    });
    document.addEventListener('mouseout', function (e) {
      if (e.target.closest && e.target.closest(hoverSel)) document.body.classList.remove('cursor-hover');
    });
    document.addEventListener('mousedown', function () { document.body.classList.add('cursor-down'); });
    document.addEventListener('mouseup', function () { document.body.classList.remove('cursor-down'); });
  }

  /* ---------------------------------------------------------------------
     Magnetic buttons
     --------------------------------------------------------------------- */
  function initMagnetic() {
    if (!finePointer || prefersReduced) return;
    document.querySelectorAll('.btn-magnetic').forEach(function (btn) {
      btn.addEventListener('mousemove', function (e) {
        var r = btn.getBoundingClientRect();
        var offsetX = (e.clientX - (r.left + r.width / 2)) * 0.25;
        var offsetY = (e.clientY - (r.top + r.height / 2)) * 0.35;
        offsetX = Math.max(-12, Math.min(12, offsetX));
        offsetY = Math.max(-10, Math.min(10, offsetY));
        btn.style.transform = 'translate(' + offsetX + 'px,' + offsetY + 'px)';
      });
      btn.addEventListener('mouseleave', function () {
        btn.style.transition = 'transform .45s cubic-bezier(.22,1,.36,1)';
        btn.style.transform = '';
        setTimeout(function () { btn.style.transition = ''; }, 450);
      });
    });
  }

  /* ---------------------------------------------------------------------
     Navbar + mobile menu
     --------------------------------------------------------------------- */
  function initNav() {
    var nav = document.getElementById('siteNav');
    if (nav) {
      var onScroll = function () {
        nav.classList.toggle('scrolled', window.scrollY > 60);
      };
      onScroll();
      window.addEventListener('scroll', onScroll, { passive: true });
    }
    var burger = document.getElementById('navBurger');
    var menu = document.getElementById('mobileMenu');
    if (burger && menu) {
      var close = document.getElementById('mobileMenuClose');
      var main = document.getElementById('main');
      function setMenu(open) {
        document.body.classList.toggle('menu-open', open);
        burger.setAttribute('aria-expanded', String(open));
        burger.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        menu.setAttribute('aria-hidden', String(!open));
        menu.inert = !open;
        if (main) main.inert = open;
        if (nav) nav.inert = open;
        if (open) {
          menu.scrollTop = 0;
          requestAnimationFrame(function () {
            if (close && document.body.classList.contains('menu-open')) close.focus({preventScroll:true});
          });
        }
        else { burger.focus({preventScroll:true}); }
      }
      burger.addEventListener('click', function () { setMenu(!document.body.classList.contains('menu-open')); });
      if (close) close.addEventListener('click', function () { setMenu(false); });
      menu.addEventListener('click', function (e) { if (e.target.closest('a')) setMenu(false); });
      document.addEventListener('keydown', function (e) {
        if (!document.body.classList.contains('menu-open')) return;
        if (e.key === 'Escape') { e.preventDefault(); setMenu(false); }
        if (e.key === 'Tab') {
          var focusables = Array.from(menu.querySelectorAll('a[href],button,summary,[tabindex="0"]')).filter(function (el) { return el.getClientRects().length > 0; });
          var first = focusables[0], last = focusables[focusables.length - 1];
          if (!menu.contains(document.activeElement)) { e.preventDefault(); first.focus(); }
          else if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
          else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
      });
      window.addEventListener('resize', function () { if (innerWidth >= 1240 && document.body.classList.contains('menu-open')) setMenu(false); });
    }
  }

  /* ---------------------------------------------------------------------
     AOS (scroll reveal) with safety fallback
     --------------------------------------------------------------------- */
  function initAOS() {
    if (window.AOS && typeof window.AOS.init === 'function') {
      window.AOS.init({
        duration: 800,
        easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
        once: true,
        offset: 80,
        disable: prefersReduced
      });
    } else {
      document.body.classList.add('no-aos');
    }
    /* if AOS CSS loaded but JS died silently, reveal after 2.5s */
    setTimeout(function () {
      if (!window.AOS) document.body.classList.add('no-aos');
    }, 2500);
  }

  /* ---------------------------------------------------------------------
     Typed hero (Typed.js or fallback typewriter)
     --------------------------------------------------------------------- */
  function initTyped() {
    var el = document.getElementById('typed-text');
    if (!el) return;
    var strings = (el.getAttribute('data-strings') || '').split('|').filter(Boolean);
    if (!strings.length) return;
    if (window.Typed) {
      new window.Typed('#typed-text', {
        strings: strings,
        typeSpeed: 60,
        backSpeed: 30,
        loop: true,
        backDelay: 2500,
        showCursor: false
      });
      return;
    }
    if (prefersReduced) { el.textContent = strings[0]; return; }
    var si = 0, ci = 0, deleting = false;
    (function tick() {
      var full = strings[si];
      ci += deleting ? -1 : 1;
      el.textContent = full.slice(0, ci);
      var wait = deleting ? 28 : 62;
      if (!deleting && ci === full.length) { wait = 2500; deleting = true; }
      else if (deleting && ci === 0) { deleting = false; si = (si + 1) % strings.length; wait = 420; }
      setTimeout(tick, wait);
    })();
  }

  /* ---------------------------------------------------------------------
     Particles hero (particles.js or canvas fallback)
     --------------------------------------------------------------------- */
  function initParticles() {
    var host = document.getElementById('particles-js');
    if (!host) return;
    if (window.particlesJS) {
      window.particlesJS('particles-js', {
        particles: {
          number: { value: 60, density: { enable: true, value_area: 900 } },
          color: { value: ['#7c3aed', '#22d3ee'] },
          shape: { type: 'circle' },
          opacity: { value: 0.3, random: true, anim: { enable: true, speed: .6, opacity_min: .08, sync: false } },
          size: { value: 2.6, random: true },
          line_linked: { enable: true, distance: 150, color: '#22d3ee', opacity: 0.1, width: 1 },
          move: { enable: true, speed: 1.1, direction: 'none', random: true, out_mode: 'out' }
        },
        interactivity: {
          detect_on: 'canvas',
          events: { onhover: { enable: true, mode: 'repulse' }, onclick: { enable: false }, resize: true },
          modes: { repulse: { distance: 90, duration: .4 } }
        },
        retina_detect: true
      });
      return;
    }
    if (prefersReduced) return;
    var canvas = document.createElement('canvas');
    canvas.setAttribute('aria-hidden', 'true');
    canvas.style.cssText = 'position:absolute;inset:0;width:100%;height:100%';
    host.appendChild(canvas);
    var ctx = canvas.getContext('2d');
    var dots = [], W = 0, H = 0, mouse = { x: -9999, y: -9999 };
    function resize() {
      W = canvas.width = host.offsetWidth;
      H = canvas.height = host.offsetHeight;
    }
    resize();
    window.addEventListener('resize', resize);
    host.addEventListener('mousemove', function (e) {
      var r = host.getBoundingClientRect();
      mouse.x = e.clientX - r.left; mouse.y = e.clientY - r.top;
    }, { passive: true });
    host.addEventListener('mouseleave', function () { mouse.x = -9999; mouse.y = -9999; });
    for (var i = 0; i < 60; i++) {
      dots.push({
        x: Math.random() * 2000, y: Math.random() * 1200,
        vx: (Math.random() - .5) * .5, vy: (Math.random() - .5) * .5,
        r: Math.random() * 2 + 1,
        c: Math.random() > .75 ? '34,211,238' : '124,58,237',
        o: Math.random() * .25 + .1
      });
    }
    (function draw() {
      ctx.clearRect(0, 0, W, H);
      for (var i = 0; i < dots.length; i++) {
        var d = dots[i];
        d.x += d.vx; d.y += d.vy;
        var dx = d.x - mouse.x, dy = d.y - mouse.y, dist = Math.sqrt(dx * dx + dy * dy);
        if (dist < 90 && dist > 0) { d.x += (dx / dist) * 1.4; d.y += (dy / dist) * 1.4; }
        if (d.x < -20) d.x = W + 20; if (d.x > W + 20) d.x = -20;
        if (d.y < -20) d.y = H + 20; if (d.y > H + 20) d.y = -20;
        ctx.beginPath();
        ctx.arc(d.x, d.y, d.r, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(' + d.c + ',' + d.o + ')';
        ctx.fill();
        for (var j = i + 1; j < dots.length; j++) {
          var e2 = dots[j], lx = d.x - e2.x, ly = d.y - e2.y, ld = Math.sqrt(lx * lx + ly * ly);
          if (ld < 140) {
            ctx.beginPath();
            ctx.moveTo(d.x, d.y); ctx.lineTo(e2.x, e2.y);
            ctx.strokeStyle = 'rgba(34,211,238,' + (0.1 * (1 - ld / 140)).toFixed(3) + ')';
            ctx.lineWidth = 1;
            ctx.stroke();
          }
        }
      }
      requestAnimationFrame(draw);
    })();
  }

  /* ---------------------------------------------------------------------
     Accordions (services + FAQ)
     --------------------------------------------------------------------- */
  function initAccordions() {
    document.querySelectorAll('[data-accordion]').forEach(function (group) {
      var single = group.getAttribute('data-accordion') !== 'multi';
      group.querySelectorAll('.acc-head').forEach(function (head, index) {
        var item = head.closest('.acc-item');
        var body = item.querySelector('.acc-body');
        item.classList.remove('open');
        if (body) {
          body.id = body.id || 'accordion-panel-' + Array.from(document.querySelectorAll('.acc-body')).indexOf(body);
          head.setAttribute('aria-controls', body.id);
          body.inert = true;
        }
        head.setAttribute('aria-expanded', head.closest('.acc-item').classList.contains('open') ? 'true' : 'false');
        head.addEventListener('click', function () {
          var item = head.closest('.acc-item');
          var willOpen = !item.classList.contains('open');
          if (single) {
            group.querySelectorAll('.acc-item.open').forEach(function (other) {
              if (other !== item) {
                other.classList.remove('open');
                other.querySelector('.acc-head').setAttribute('aria-expanded', 'false');
                var otherBody = other.querySelector('.acc-body');
                if (otherBody) otherBody.inert = true;
              }
            });
          }
          item.classList.toggle('open', willOpen);
          head.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
          if (body) body.inert = !willOpen;
        });
      });
    });
  }

  /* ---------------------------------------------------------------------
     Count-up numbers
     --------------------------------------------------------------------- */
  function initCounters() {
    var els = document.querySelectorAll('[data-countup]');
    if (!els.length) return;
    function animate(el) {
      var target = parseFloat(el.getAttribute('data-countup'));
      var decimals = parseInt(el.getAttribute('data-decimals') || '0', 10);
      var suffix = el.getAttribute('data-suffix') || '';
      if (prefersReduced) { el.textContent = target.toFixed(decimals) + suffix; return; }
      var start = null, dur = 1700;
      function step(ts) {
        if (!start) start = ts;
        var p = Math.min(1, (ts - start) / dur);
        var eased = 1 - Math.pow(1 - p, 3);
        el.textContent = (target * eased).toFixed(decimals) + suffix;
        if (p < 1) requestAnimationFrame(step);
      }
      requestAnimationFrame(step);
    }
    if (!('IntersectionObserver' in window)) {
      els.forEach(animate);
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          animate(entry.target);
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.4 });
    els.forEach(function (el) { io.observe(el); });
  }

  /* ---------------------------------------------------------------------
     THE SYSTEM — pinned scroll disciplines
     --------------------------------------------------------------------- */
  function initSystem() {
    var root = document.querySelector('.system');
    if (!root) return;
    var panels = root.querySelectorAll('.system-panel');
    var triggers = root.querySelectorAll('.system-trigger');
    var bars = root.querySelectorAll('.system-progress li');
    if (!panels.length) return;
    function activate(index) {
      panels.forEach(function (p, i) { p.classList.toggle('is-active', i === index); });
      bars.forEach(function (b, i) {
        b.classList.toggle('current', i === index);
        b.classList.toggle('done', i < index);
      });
    }
    var io = null;
    var mobile = window.matchMedia('(max-width: 900px), (prefers-reduced-motion: reduce)');
    function configure() {
      if (io) { io.disconnect(); io = null; }
      if (mobile.matches || !('IntersectionObserver' in window)) {
        panels.forEach(function (p) { p.classList.add('is-active'); });
        return;
      }
      activate(0);
      io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) activate(parseInt(entry.target.getAttribute('data-index'), 10) || 0);
        });
      }, { rootMargin: '-45% 0px -45% 0px', threshold: 0 });
      triggers.forEach(function (t) { io.observe(t); });
    }
    configure();
    if (mobile.addEventListener) mobile.addEventListener('change', configure);
    else mobile.addListener(configure);
  }

  /* ---------------------------------------------------------------------
     Testimonials carousel (Swiper or static fallback)
     --------------------------------------------------------------------- */
  function initTestimonials() {
    var host = document.querySelector('.testimonial-swiper');
    if (!host) return;
    var pag = document.querySelector('.swiper-pagination-custom');
    if (window.Swiper) {
      var swiper = new window.Swiper(host, {
        slidesPerView: 1,
        spaceBetween: 28,
        loop: true,
        speed: 700,
        watchOverflow: true,
        autoHeight: true,
        touchRatio: 1,
        autoplay: prefersReduced ? false : { delay: 6000, disableOnInteraction: true },
        grabCursor: true,
        breakpoints: {
          320: { spaceBetween: 14 },
          640: { spaceBetween: 20 },
          900: { spaceBetween: 28 }
        }
      });
      if (pag) {
        pag.querySelectorAll('button').forEach(function (btn, i) {
          btn.addEventListener('click', function () { swiper.slideToLoop(i); });
        });
        swiper.on('slideChange', function () {
          var real = swiper.realIndex;
          pag.querySelectorAll('button').forEach(function (b, i) { b.classList.toggle('active', i === real); });
        });
      }
    } else {
      /* CDN fallback: hide the pagination dots and stack cards — never cut off. */
      host.classList.add('swiper-fallback');
      if (pag) pag.style.display = 'none';
    }
  }

  /* ---------------------------------------------------------------------
     International phone input (contact + payment forms)
     --------------------------------------------------------------------- */
  function initIntlPhone(scope) {
    if (!window.intlTelInput) return;
    (scope || document).querySelectorAll('[data-intl-phone]').forEach(function (input) {
      if (input.dataset.itiInit === '1') return;
      input.dataset.itiInit = '1';
      var iti = window.intlTelInput(input, {
        initialCountry: 'us',
        preferredCountries: ['us', 'gb', 'pk', 'ca', 'au', 'in'],
        separateDialCode: true,
        nationalMode: false,
        autoPlaceholder: 'polite',
        utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js'
      });
      input._tptIntlPhone = iti;
      /* Submit the normalised E.164 number in a hidden companion field. */
      var form = input.closest('form');
      if (form) {
        form.addEventListener('submit', function () {
          try {
            var full = iti.getNumber();
            if (full && input.value.trim() !== '') {
              var hidden = form.querySelector('[name="' + input.name + '_e164"]');
              if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = input.name + '_e164';
                form.appendChild(hidden);
              }
              hidden.value = full;
              input.value = full;
            }
          } catch (e) { /* utils not loaded yet — keep the typed value */ }
        }, true);
      }
    });
  }

  /* ---------------------------------------------------------------------
     Portfolio filter
     --------------------------------------------------------------------- */
  function initPortfolioFilter() {
    var tabs = document.querySelectorAll('.filter-tab');
    var cards = document.querySelectorAll('.pf-card[data-category]');
    if (!tabs.length || !cards.length) return;
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabs.forEach(function (t) { t.classList.remove('active'); t.setAttribute('aria-pressed', 'false'); });
        tab.classList.add('active');
        tab.setAttribute('aria-pressed', 'true');
        var filter = tab.getAttribute('data-filter');
        var visible = 0;
        cards.forEach(function (card) {
          var show = filter === 'all' || card.getAttribute('data-category') === filter;
          if (show) visible++;
          if (show) {
            card.classList.remove('hidden');
            requestAnimationFrame(function () { card.classList.remove('is-hiding'); });
          } else {
            card.classList.add('is-hiding');
            setTimeout(function () {
              if (card.classList.contains('is-hiding')) card.classList.add('hidden');
            }, 320);
          }
        });
        var emptyState = document.getElementById('pfEmpty');
        if (emptyState) emptyState.classList.toggle('hidden', visible > 0);
      });
    });
  }

  /* ---------------------------------------------------------------------
     Growth Library filter (two dimensions: type + category)
     --------------------------------------------------------------------- */
  function initLibraryFilter() {
    var cards = document.querySelectorAll('.resource-card[data-type]');
    if (!cards.length) return;
    var state = { type: 'all', cat: 'all' };
    var empty = document.getElementById('libEmpty');
    function apply() {
      var visible = 0;
      cards.forEach(function (card) {
        var okT = state.type === 'all' || card.getAttribute('data-type') === state.type;
        var okC = state.cat === 'all' || card.getAttribute('data-category') === state.cat;
        var show = okT && okC;
        if (show) {
          visible++;
          card.classList.remove('hidden');
          requestAnimationFrame(function () { card.classList.remove('is-hiding'); });
        } else {
          card.classList.add('is-hiding');
          setTimeout(function () {
            if (card.classList.contains('is-hiding')) card.classList.add('hidden');
          }, 320);
        }
      });
      if (empty) empty.classList.toggle('hidden', visible > 0);
    }
    document.querySelectorAll('[data-libfilter]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var dim = btn.getAttribute('data-libfilter');
        var group = document.querySelectorAll('[data-libfilter="' + dim + '"]');
        group.forEach(function (b) { b.classList.remove('active'); b.setAttribute('aria-pressed', 'false'); });
        btn.classList.add('active');
        btn.setAttribute('aria-pressed', 'true');
        if (dim === 'type') state.type = btn.getAttribute('data-value');
        else state.cat = btn.getAttribute('data-value');
        apply();
      });
    });
  }

  /* ---------------------------------------------------------------------
     Charts (Chart.js) — canvas elements carrying data-chart JSON
     --------------------------------------------------------------------- */
  function initCharts() {
    var canvases = document.querySelectorAll('canvas[data-chart]');
    if (!canvases.length) return;
    if (!window.Chart) {
      canvases.forEach(function (c) {
        var wrap = c.parentNode;
        if (wrap) wrap.innerHTML = '<p class="text-muted" style="padding:40px 0;text-align:center">Chart unavailable offline.</p>';
      });
      return;
    }
    Chart.defaults.color = '#9a9aa7';
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.borderColor = 'rgba(255,255,255,.06)';
    canvases.forEach(function (canvas) {
      var cfg;
      try { cfg = JSON.parse(canvas.getAttribute('data-chart')); } catch (e) { return; }
      cfg.options = cfg.options || {};
      cfg.options.responsive = true;
      cfg.options.maintainAspectRatio = false;
      cfg.options.animation = cfg.options.animation || { duration: prefersReduced ? 0 : 1400, easing: 'easeOutQuart' };
      if (cfg.type === 'line') {
        (cfg.data.datasets || []).forEach(function (ds) {
          ds.borderColor = ds.borderColor || '#7c3aed';
          ds.backgroundColor = ds.backgroundColor || 'rgba(124,58,237,.16)';
          ds.fill = ds.fill === undefined ? true : ds.fill;
          ds.tension = ds.tension === undefined ? 0.42 : ds.tension;
          ds.pointRadius = ds.pointRadius === undefined ? 3 : ds.pointRadius;
          ds.pointBackgroundColor = ds.pointBackgroundColor || '#a78bfa';
        });
      }
      new Chart(canvas, cfg);
    });
  }

  /* ---------------------------------------------------------------------
     AJAX forms (contact + newsletter + comment)
     --------------------------------------------------------------------- */
  function clearFieldErrors(form) {
    form.querySelectorAll('.field-msg').forEach(function (el) { el.parentNode.removeChild(el); });
    form.querySelectorAll('[aria-invalid]').forEach(function (el) { el.removeAttribute('aria-invalid'); });
  }
  function showFieldErrors(form, errors) {
    clearFieldErrors(form);
    Object.keys(errors || {}).forEach(function (name) {
      var input = form.querySelector('[name="' + name + '"]');
      if (!input) return;
      input.setAttribute('aria-invalid', 'true');
      var holder = input.closest('.field') || input.parentNode;
      var msg = document.createElement('small');
      msg.className = 'field-msg';
      msg.textContent = errors[name];
      holder.appendChild(msg);
    });
  }
  function readFormCaptchaToken(form, data) {
    var existing = data.get('captcha_token') || data.get('cf-turnstile-response') || data.get('h-captcha-response') || data.get('g-recaptcha-response');
    if (existing) return existing;
    var hidden = form.querySelector('input[name="captcha_token"], input[name="cf-turnstile-response"], textarea[name="g-recaptcha-response"], textarea[name="h-captcha-response"]');
    if (hidden && hidden.value) {
      data.set('captcha_token', hidden.value);
      return hidden.value;
    }
    var tsWidget = form.querySelector('.cf-turnstile[data-tpt-widget-id]');
    if (tsWidget && window.turnstile && typeof window.turnstile.getResponse === 'function') {
      try {
        var tsToken = window.turnstile.getResponse(tsWidget.getAttribute('data-tpt-widget-id'));
        if (tsToken) {
          data.set('cf-turnstile-response', tsToken);
          data.set('captcha_token', tsToken);
          return tsToken;
        }
      } catch (err) { /* ignore */ }
    } else if (form.querySelector('.h-captcha') && window.hcaptcha && typeof window.hcaptcha.getResponse === 'function') {
      try {
        var hcToken = window.hcaptcha.getResponse();
        if (hcToken) {
          data.set('h-captcha-response', hcToken);
          data.set('captcha_token', hcToken);
          return hcToken;
        }
      } catch (err) { /* ignore */ }
    } else if (form.querySelector('.g-recaptcha') && window.grecaptcha && typeof window.grecaptcha.getResponse === 'function') {
      try {
        var rcToken = window.grecaptcha.getResponse();
        if (rcToken) {
          data.set('g-recaptcha-response', rcToken);
          data.set('captcha_token', rcToken);
          return rcToken;
        }
      } catch (err) { /* ignore */ }
    }
    return '';
  }

  function ensureFormCaptcha(form, data, done) {
    if (typeof window.tptRenderTurnstiles === 'function') {
      window.tptRenderTurnstiles(form);
    }
    if (readFormCaptchaToken(form, data) || !form.querySelector('.cf-turnstile') || !window.turnstile) {
      done();
      return;
    }
    var attempts = 0;
    var timer = window.setInterval(function () {
      attempts++;
      if (typeof window.tptRenderTurnstiles === 'function') {
        window.tptRenderTurnstiles(form);
      }
      if (readFormCaptchaToken(form, data) || attempts >= 20) {
        window.clearInterval(timer);
        done();
      }
    }, 150);
  }

  function ajaxForm(form, onSuccess) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var status = form.querySelector('.form-status');
      var submitBtn = form.querySelector('[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;
      if (status) { status.className = 'form-status'; status.textContent = ''; }
      clearFieldErrors(form);
      var data = new FormData(form);
      /* FormData(form) omits the clicked submit button. The PHP contact handler
         uses this marker to distinguish a real submission from a page request. */
      if (form.id === 'contactForm' || form.hasAttribute('data-quick-contact')) data.set('contact_submit', '1');
      ensureFormCaptcha(form, data, function () {
        fetch(form.getAttribute('action') || window.location.href, {
          method: 'POST',
          body: data,
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
          .then(function (res) {
            return res.text().then(function (body) {
              var json;
              try { json = JSON.parse(body); } catch (error) { throw new Error('unexpected response'); }
              if (!res.ok && json && json.success === undefined) throw new Error('request failed');
              return json;
            });
          })
          .then(function (json) {
            if (json && json.success) {
              onSuccess(json, form);
            } else {
              if (json && json.errors) {
                showFieldErrors(form, json.errors);
                if (json.errors.captcha) resetCaptcha(form);
              }
              if (status) {
                status.className = 'form-status err show';
                status.textContent = (json && json.message) ? json.message : 'Something went wrong. Please try again.';
              }
              if (submitBtn) submitBtn.disabled = false;
            }
          })
          .catch(function () {
            if (status) {
              status.className = 'form-status err show';
              status.textContent = 'Network error — please try again or email us directly.';
            }
            if (submitBtn) submitBtn.disabled = false;
          });
      });
    });
  }

  function initContactForm(form) {
    form = form || document.getElementById('contactForm');
    if (!form) return;
    if (form.getAttribute('data-ajax-bound') === '1') return;
    form.setAttribute('data-ajax-bound', '1');
    ajaxForm(form, function (json) {
      var success = document.getElementById('formSuccess');
      if (success) {
        if (json.message) {
          var msg = success.querySelector('p');
          if (msg) msg.textContent = json.message;
        }
        form.style.display = 'none';
        success.classList.add('show');
        success.scrollIntoView({ behavior: prefersReduced ? 'auto' : 'smooth', block: 'center' });
      } else {
        form.reset();
      }
    });
  }

  /* ---------------------------------------------------------------------
     Start-a-project popup — the compact Contact Us form.

     The popup moves the real form into a dialog and posts it to the same
     ContactController endpoint as Contact Us. CSRF, CAPTCHA, server-side
     validation, submissions and mail notifications are shared; this is only
     a compact presentation of the existing contact workflow.
     --------------------------------------------------------------------- */
  function resetCaptcha(form) {
    if (!form) return;
    var tokenInput = form.querySelector('input[name="captcha_token"]');
    if (tokenInput) tokenInput.value = '';
    var turnstile = form.querySelectorAll('.cf-turnstile[data-tpt-widget-id]');
    if (window.turnstile && typeof window.turnstile.reset === 'function' && turnstile.length) {
      Array.prototype.forEach.call(turnstile, function (widget) {
        try { window.turnstile.reset(widget.getAttribute('data-tpt-widget-id')); } catch (error) { /* ignore provider reset errors */ }
      });
    } else if (window.hcaptcha && typeof window.hcaptcha.reset === 'function') {
      try { window.hcaptcha.reset(); } catch (error) { /* ignore provider reset errors */ }
    } else if (window.grecaptcha && typeof window.grecaptcha.reset === 'function') {
      try { window.grecaptcha.reset(); } catch (error) { /* ignore provider reset errors */ }
    }
  }

  function initContactModal() {
    var triggers = document.querySelectorAll('[data-contact-modal]');
    if (!triggers.length || document.getElementById('contactModal')) return;

    var quickHost = document.getElementById('tpt-quick-contact');
    var quickForm = document.getElementById('quickContactForm');
    var quickSuccess = document.getElementById('quickFormSuccess');
    if (!quickHost || !quickForm) return;

    var panel = document.createElement('div');
    panel.className = 'contact-modal';
    panel.id = 'contactModal';
    panel.setAttribute('aria-hidden', 'true');
    panel.innerHTML =
      '<div class="contact-modal__overlay" data-modal-close></div>' +
      '<div class="contact-modal__panel" role="dialog" aria-modal="true" aria-labelledby="contactModalTitle">' +
        '<div class="contact-modal__top">' +
          '<div class="contact-modal__head">' +
            '<p class="eyebrow">Start a project</p>' +
            '<h2 id="contactModalTitle">Tell us what you need.</h2>' +
            '<p class="contact-modal__lead">Four quick details. A senior strategist will reply within one business day.</p>' +
          '</div>' +
          '<button type="button" class="contact-modal__close" data-modal-close aria-label="Close project form">' +
            '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>' +
          '</button>' +
        '</div>' +
        '<div class="contact-modal__body"></div>' +
      '</div>';
    document.body.appendChild(panel);

    var body = panel.querySelector('.contact-modal__body');
    var lastFocus = null;
    var isOpen = false;
    var mounted = false;

    function focusable() {
      return Array.prototype.slice.call(
        panel.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')
      ).filter(function (el) { return el.getClientRects().length > 0; });
    }

    function mount() {
      if (mounted || !quickForm || !quickHost) return false;
      body.appendChild(quickForm);
      if (quickSuccess) body.appendChild(quickSuccess);
      if (quickHost.parentNode) quickHost.parentNode.removeChild(quickHost);
      mounted = true;
      initQuickContactForm();
      return true;
    }

    function clearQuickStatus() {
      var status = quickForm && quickForm.querySelector('.form-status');
      if (status) { status.className = 'form-status'; status.textContent = ''; }
    }

    function resetQuickForm() {
      if (!quickForm) return;
      quickForm.reset();
      var phone = quickForm.querySelector('[data-intl-phone]');
      if (phone && phone._tptIntlPhone && typeof phone._tptIntlPhone.setNumber === 'function') {
        try { phone._tptIntlPhone.setNumber(''); } catch (error) { /* keep the native reset */ }
      }
      var normalizedPhone = quickForm.querySelector('input[name="phone_e164"]');
      if (normalizedPhone && normalizedPhone.parentNode) normalizedPhone.parentNode.removeChild(normalizedPhone);
      clearFieldErrors(quickForm);
      clearQuickStatus();
      resetCaptcha(quickForm);
    }

    function initQuickContactForm() {
      if (!quickForm || quickForm.getAttribute('data-ajax-bound') === '1') return;
      quickForm.setAttribute('data-ajax-bound', '1');
      ajaxForm(quickForm, function (json) {
        /* The server has accepted and recorded this contact submission. Clear
           customer data before showing the confirmation state. */
        resetQuickForm();
        if (quickSuccess) {
          if (json.message) {
            var message = quickSuccess.querySelector('p');
            if (message) message.textContent = json.message;
          }
          quickForm.style.display = 'none';
          quickSuccess.setAttribute('aria-hidden', 'false');
          quickSuccess.classList.add('show');
          var closeButton = panel.querySelector('.contact-modal__close');
          if (closeButton) closeButton.focus({ preventScroll: true });
        }
      });
    }

    function open() {
      lastFocus = document.activeElement;
      if (!mounted && !mount()) return;
      if (!isOpen) {
        isOpen = true;
        panel.classList.add('open');
        panel.inert = false;
        document.body.classList.add('modal-open');
        panel.setAttribute('aria-hidden', 'false');
      }
      initIntlPhone(quickForm);
      if (typeof window.tptRenderTurnstiles === 'function') {
        window.tptRenderTurnstiles(panel);
        window.requestAnimationFrame(function () { window.tptRenderTurnstiles(panel); });
        window.setTimeout(function () { window.tptRenderTurnstiles(panel); }, 250);
      }
      focusFirstField();
      window.requestAnimationFrame(focusFirstField);
      window.setTimeout(focusFirstField, 250);
    }

    function focusFirstField() {
      var target = (!quickForm || quickForm.style.display === 'none' || !mounted)
        ? panel.querySelector('.contact-modal__close')
        : (quickForm.querySelector('input[name="name"]') || panel.querySelector('.contact-modal__close'));
      if (target && typeof target.focus === 'function') {
        try { target.focus({ preventScroll: true }); } catch (error) { /* ignore */ }
      }
    }

    function close() {
      if (!isOpen) return;
      isOpen = false;
      panel.classList.remove('open');
      panel.inert = true;
      document.body.classList.remove('modal-open');
      panel.setAttribute('aria-hidden', 'true');
      if (quickSuccess && quickSuccess.classList.contains('show')) {
        quickSuccess.classList.remove('show');
        quickSuccess.setAttribute('aria-hidden', 'true');
        quickForm.style.display = '';
        clearQuickStatus();
      }
      var restoreFocus = lastFocus;
      if (restoreFocus && restoreFocus.closest && restoreFocus.closest('.mobile-menu')) {
        restoreFocus = document.getElementById('navBurger') || document.getElementById('siteNav');
      }
      if (restoreFocus && restoreFocus.focus && restoreFocus.isConnected) {
        try { restoreFocus.focus({ preventScroll: true }); } catch (error) { /* ignore */ }
      }
    }

    mount();
    panel.inert = true;
    window.TPT_CONTACT_MODAL = { open: open, close: close };

    /* Intercept only explicit popup triggers; ordinary Contact Us links still navigate. */
    triggers.forEach(function (trigger) {
      if (trigger.getAttribute('data-modal-bound') === '1') return;
      trigger.setAttribute('data-modal-bound', '1');
      trigger.addEventListener('click', function (event) {
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.button) return;
        event.preventDefault();
        open();
      });
    });

    document.addEventListener('click', function (event) {
      if (event.target.closest && event.target.closest('[data-modal-close]')) {
        event.preventDefault();
        close();
      }
    });

    document.addEventListener('keydown', function (event) {
      if (!isOpen) return;
      if (event.key === 'Escape' || event.key === 'Esc') { event.preventDefault(); close(); }
    });

    panel.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' || event.key === 'Esc') { event.preventDefault(); close(); return; }
      if (event.key !== 'Tab') return;
      var items = focusable();
      if (!items.length) return;
      var first = items[0], last = items[items.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
  }

  function initNewsletterForms() {
    document.querySelectorAll('form[data-ajax="newsletter"]').forEach(function (form) {
      ajaxForm(form, function (json, f) {
        var status = f.querySelector('.form-status');
        if (status) {
          status.className = 'form-status ok show';
          status.textContent = json.message || 'You are on the list! Check your inbox for a welcome email.';
        }
        f.reset();
      });
    });
  }

  /* ---------------------------------------------------------------------
     Click-to-load video embeds (privacy + performance)
     --------------------------------------------------------------------- */
  function initVideoLoads() {
    document.querySelectorAll('.video-load[data-video]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var frame = document.createElement('iframe');
        frame.className = 'video-embed';
        frame.src = btn.getAttribute('data-video') + '?autoplay=1';
        frame.title = btn.getAttribute('aria-label') || 'Video';
        frame.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
        frame.allowFullscreen = true;
        frame.style.cssText = 'position:absolute;inset:0;width:100%;height:100%';
        btn.parentNode.replaceChild(frame, btn);
      });
    });
  }

  /* ---------------------------------------------------------------------
     Reading progress (blog single)
     --------------------------------------------------------------------- */
  function initReadingProgress() {
    var bar = document.querySelector('.reading-progress i');
    var article = document.querySelector('.post-body');
    if (!bar || !article) return;
    function update() {
      var rect = article.getBoundingClientRect();
      var total = rect.height - innerHeight * 0.6;
      var passed = Math.min(Math.max(-rect.top + innerHeight * 0.2, 0), total);
      bar.style.transform = 'scaleX(' + (total > 0 ? passed / total : 1) + ')';
    }
    update();
    window.addEventListener('scroll', update, { passive: true });
  }

  function initIndustryExplorer() {
    document.querySelectorAll('[data-industry-explorer]').forEach(function (root) {
      var list = root.querySelector('.industry-tabs');
      var tabs = Array.from(root.querySelectorAll('.industry-tab'));
      var panels = Array.from(root.querySelectorAll('.industry-detail'));
      if (!list || !tabs.length || tabs.length !== panels.length) return;
      list.hidden = false;
      function select(index, focus) {
        tabs.forEach(function (tab, i) {
          tab.setAttribute('aria-selected', String(i === index));
          tab.tabIndex = i === index ? 0 : -1;
          panels[i].hidden = i !== index;
          panels[i].setAttribute('role', 'tabpanel');
          panels[i].setAttribute('aria-labelledby', tab.id);
          panels[i].tabIndex = 0;
        });
        if (focus) tabs[index].focus({preventScroll:true});
      }
      tabs.forEach(function (tab, index) {
        tab.addEventListener('click', function () { select(index, false); });
        tab.addEventListener('keydown', function (event) {
          var next = index;
          if (event.key === 'ArrowRight' || event.key === 'ArrowDown') next = (index + 1) % tabs.length;
          else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') next = (index - 1 + tabs.length) % tabs.length;
          else if (event.key === 'Home') next = 0;
          else if (event.key === 'End') next = tabs.length - 1;
          else return;
          event.preventDefault(); select(next, true);
        });
      });
      select(0, false);
    });
  }

  function initBackToTop() {
    var button = document.getElementById('backToTop');
    if (!button) return;
    function update() { button.hidden = window.scrollY < 400; }
    button.addEventListener('click', function () {
      window.scrollTo({top:0, behavior:prefersReduced ? 'auto' : 'smooth'});
      var brand = document.querySelector('#siteNav .brand');
      if (brand) brand.focus({preventScroll:true});
    });
    window.addEventListener('scroll', update, {passive:true});
    update();
  }

  /* ---------------------------------------------------------------------
     Boot
     --------------------------------------------------------------------- */
  /* Each initializer runs in isolation: if one widget fails on a device
     (unavailable canvas/observer/library), navigation, forms and the
     Start-a-project popup still initialize. */
  function safeInit(init) {
    try {
      init();
    } catch (error) {
      if (window.console && typeof console.warn === 'function') {
        console.warn('[TPT] ' + (init.name || 'init') + ' failed:', error);
      }
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    [
      initPreloader, initCursor, initMagnetic, initNav, initBackToTop,
      initIndustryExplorer, initAOS, initTyped, initParticles, initAccordions,
      initCounters, initSystem, initTestimonials, initPortfolioFilter, initLibraryFilter,
      initCharts, initContactForm, initContactModal, initNewsletterForms, initIntlPhone,
      initReadingProgress, initVideoLoads,
    ].forEach(safeInit);
  });
})();
