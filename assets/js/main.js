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
      burger.addEventListener('click', function () {
        var open = document.body.classList.toggle('menu-open');
        burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        burger.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        menu.setAttribute('aria-hidden', open ? 'false' : 'true');
      });
      menu.addEventListener('click', function (e) {
        if (e.target.closest('a')) {
          document.body.classList.remove('menu-open');
          burger.setAttribute('aria-expanded', 'false');
          menu.setAttribute('aria-hidden', 'true');
        }
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.body.classList.contains('menu-open')) {
          document.body.classList.remove('menu-open');
          burger.setAttribute('aria-expanded', 'false');
          burger.focus();
        }
      });
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
      group.querySelectorAll('.acc-head').forEach(function (head) {
        head.setAttribute('aria-expanded', head.closest('.acc-item').classList.contains('open') ? 'true' : 'false');
        head.addEventListener('click', function () {
          var item = head.closest('.acc-item');
          var willOpen = !item.classList.contains('open');
          if (single) {
            group.querySelectorAll('.acc-item.open').forEach(function (other) {
              if (other !== item) {
                other.classList.remove('open');
                other.querySelector('.acc-head').setAttribute('aria-expanded', 'false');
              }
            });
          }
          item.classList.toggle('open', willOpen);
          head.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
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
    activate(0);
    if (!('IntersectionObserver' in window) || innerWidth <= 900) {
      panels.forEach(function (p) { p.classList.add('is-active'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          activate(parseInt(entry.target.getAttribute('data-index'), 10) || 0);
        }
      });
    }, { rootMargin: '-45% 0px -45% 0px', threshold: 0 });
    triggers.forEach(function (t) { io.observe(t); });
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
        autoplay: prefersReduced ? false : { delay: 6000, disableOnInteraction: true },
        grabCursor: true
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
    } else if (pag) {
      pag.style.display = 'none';
    }
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
  function ajaxForm(form, onSuccess) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var status = form.querySelector('.form-status');
      var submitBtn = form.querySelector('[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;
      if (status) { status.className = 'form-status'; status.textContent = ''; }
      var data = new FormData(form);
      fetch(form.getAttribute('action') || window.location.href, {
        method: 'POST',
        body: data,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          if (json && json.success) {
            onSuccess(json, form);
          } else {
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
  }

  function initContactForm() {
    var form = document.getElementById('contactForm');
    if (!form) return;
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

  /* ---------------------------------------------------------------------
     Boot
     --------------------------------------------------------------------- */
  document.addEventListener('DOMContentLoaded', function () {
    initPreloader();
    initCursor();
    initMagnetic();
    initNav();
    initAOS();
    initTyped();
    initParticles();
    initAccordions();
    initCounters();
    initSystem();
    initTestimonials();
    initPortfolioFilter();
    initLibraryFilter();
    initCharts();
    initContactForm();
    initNewsletterForms();
    initReadingProgress();
    initVideoLoads();
  });
})();
