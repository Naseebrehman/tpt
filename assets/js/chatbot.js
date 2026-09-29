/* ===========================================================================
   Alia — the TPT growth assistant (Professional Redesign)
   ===========================================================================
   Features:
   • Professional bubble design with avatars, timestamps, read receipts
   • Smooth auto-scroll with user scroll-lock detection
   • Typing indicator with smooth animation
   * Shorter, direct responses (max_tokens lowered)
   * Smooth scroll behavior with scroll-lock detection
   * Error states with retry, connection status
   * Responsive: mobile full-screen, desktop sidebar
   * Accessibility: ARIA, keyboard nav, focus management
   * Message grouping by sender, avatar system
   * Smooth scroll to bottom on new messages, respect user scroll position
   =========================================================================== */
(function () {
  'use strict';

  var PIE = window.PIE || { api: 'chatbot-api.php', base: '' };
  var SS  = window.sessionStorage;

  /* ---------- DOM Elements ---------- */
  var fab         = document.getElementById('chatbotFab');
  var win         = document.getElementById('chatbotWindow');
  var closeBtn    = document.getElementById('chatbotClose');
  var messages    = document.getElementById('chatbotMessages');
  var input       = document.getElementById('chatbotInput');
  var sendBtn     = document.getElementById('chatbotSend');
  var header      = document.querySelector('.chatbot-head');
  var inputArea   = document.querySelector('.chatbot-input');
  var statusDot   = document.querySelector('.online-dot');

  if (!fab || !win || !messages || !input || !sendBtn) return;

  /* ---------- State ---------- */
  var state = {
    open: false,
    busy: false,
    sessionId: SS.getItem('pie_session') || ('s_' + Math.random().toString(36).slice(2) + Date.now().toString(36)),
    leadStage: (PIE.leadCollection === false) ? 'done' : (SS.getItem('pie_lead_stage') || 'name'),
    lead: {
      name: SS.getItem('pie_lead_name') || '',
      email: SS.getItem('pie_lead_email') || ''
    },
    history: [],
    lastScrollTop: 0,
    userScrolledUp: false,
    messageId: 0
  };

  try { state.history = JSON.parse(SS.getItem('pie_history') || '[]'); } catch (e) { state.history = []; }
  if (!Array.isArray(state.history)) state.history = [];
  SS.setItem('pie_session', state.sessionId);

  /* ---------- Helpers ---------- */
  function esc(text) {
    var d = document.createElement('div');
    d.textContent = String(text);
    return d.innerHTML;
  }

  function nowTime() {
    return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  function createMessageId() {
    return 'msg-' + (++state.messageId) + '-' + Date.now().toString(36);
  }

  function createBubble(role, content, options) {
    options = options || {};
    var wrap = document.createElement('div');
    wrap.className = 'chat-msg-wrapper ' + role;
    wrap.dataset.msgId = options.id || createMessageId();
    wrap.dataset.time = options.time || nowTime();

    var avatarHtml = '';
    if (role === 'bot') {
      avatarHtml = '<div class="msg-avatar bot-avatar" aria-hidden="true"><span class="avatar-initial">A</span></div>';
    } else if (role === 'user') {
      avatarHtml = '<div class="msg-avatar user-avatar" aria-hidden="true"><span class="avatar-initial">U</span></div>';
    }

    var bubbleHtml = 
      avatarHtml +
      '<div class="msg-bubble-wrap">' +
        '<div class="msg-bubble ' + role + '" role="log" aria-live="polite">' +
          (options.isHTML ? content : esc(content)) +
        '</div>' +
        '<div class="msg-meta">' +
          '<span class="msg-time">' + (options.time || nowTime()) + '</span>' +
          (role === 'user' ? '<span class="msg-status" aria-label="Sent"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L7 12l11 6"/></svg></span>' : '') +
        '</div>' +
      '</div>';

    wrap.innerHTML = bubbleHtml;
    return wrap;
  }

  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /**
   * Reveal a message at its START (never jump to the end of a long answer).
   * The browser clamps the target when the conversation is shorter than the
   * viewport, so short threads simply stay in place.
   */
  function revealMessage(el) {
    if (!el) return;
    if (state.userScrolledUp) return; /* the visitor is reading history — leave it alone */
    var target = Math.max(0, (el.offsetTop || 0) - 8);
    try {
      messages.scrollTo({ top: target, behavior: reduceMotion ? 'auto' : 'smooth' });
    } catch (e) {
      messages.scrollTop = target;
    }
  }

  function appendBubble(role, content, options) {
    var el = createBubble(role, content, options);
    messages.appendChild(el);
    requestAnimationFrame(function () { revealMessage(el); });
    return el;
  }

  function push(role, text) { return appendBubble(role, text, { isHTML: false }); }
  function pushHtml(html) { return appendBubble('bot', html, { isHTML: true }); }

  /** Professional error state with a retry affordance. */
  function pushError(message) {
    var el = createBubble('bot', esc(message)
      + '<div class="chat-lead-actions"><button type="button" class="retry-btn" data-retry="1">'
      + '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg>'
      + ' Try again</button></div>', { isHTML: true });
    var bubble = el.querySelector('.msg-bubble');
    if (bubble) { bubble.classList.add('error'); }
    messages.appendChild(el);
    requestAnimationFrame(function () { revealMessage(el); });
    return el;
  }

  function typing(on) {
    var el = messages.querySelector('.typing-indicator');
    if (on && !el) {
      el = document.createElement('div');
      el.className = 'typing-indicator';
      el.innerHTML = '<div class="msg-avatar bot-avatar" aria-hidden="true"><span class="avatar-initial">A</span></div>'
        + '<div class="typing-bubble"><span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span></div>';
      el.setAttribute('aria-live', 'polite');
      el.setAttribute('aria-label', 'Alia is typing');
      messages.appendChild(el);
      requestAnimationFrame(function () { revealMessage(el); });
    } else if (!on && el) {
      el.parentNode.removeChild(el);
    }
  }

  function saveHistory() {
    try { SS.setItem('pie_history', JSON.stringify(state.history.slice(-12))); } catch (e) { }
  }

  function setOpen(open) {
    state.open = open;
    win.classList.toggle('open', open);
    win.setAttribute('aria-hidden', open ? 'false' : 'true');
    fab.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      setTimeout(function () { input.focus(); }, 250);
      if (!messages.childElementCount) startConversation();
      state.userScrolledUp = false;
      var all = messages.querySelectorAll('.chat-msg-wrapper');
      if (all.length) { requestAnimationFrame(function () { revealMessage(all[all.length - 1]); }); }
    } else {
      typing(false);
    }
  }

  /* ---------- Scroll Detection (respect user scroll position) ----------
     Only a real wheel/touch gesture above the bottom means "the visitor is
     reading history" — our own smooth scrolling must never set that flag. */
  function atBottom() {
    return (messages.scrollHeight - messages.scrollTop - messages.clientHeight) < 60;
  }
  messages.addEventListener('scroll', function () {
    if (atBottom()) { state.userScrolledUp = false; }
  }, { passive: true });
  messages.addEventListener('wheel', function () { if (!atBottom()) { state.userScrolledUp = true; } }, { passive: true });
  messages.addEventListener('touchmove', function () { if (!atBottom()) { state.userScrolledUp = true; } }, { passive: true });

  /* ---------- Lead Capture ---------- */
  function askLeadStage() {
    if (state.leadStage === 'name') {
      pushHtml('Before we start — what\'s your name? <span class="text-muted" style="font-size:.75rem">(helps us personalise things)</span>'
        + '<div class="chat-lead-actions"><button type="button" data-skip="name" class="skip-btn">Skip</button></div>');
    } else if (state.leadStage === 'email') {
      pushHtml('Thanks! And what\'s your email so our team can follow up?'
        + '<div class="chat-lead-actions"><button type="button" data-skip="email" class="skip-btn">Skip</button></div>');
    }
  }

  function startConversation() {
    push('bot', PIE.welcome || "Hi, I'm Alia. How can I help you today?");
    pushHtml('<div class="chat-suggests">'
      + '<button type="button" data-ask="What services do you offer?" class="suggest-btn">What services do you offer?</button>'
      + '<button type="button" data-ask="I need more leads" class="suggest-btn">I need more leads</button>'
      + '<button type="button" data-ask="Tell me about your SEO approach" class="suggest-btn">Your SEO approach</button>'
      + '<button type="button" data-ask="I want to start a project" class="suggest-btn">Start a project</button>'
      + '</div>');
    if (state.leadStage !== 'done') askLeadStage();
  }

  /* Suggested question chips */
  messages.addEventListener('click', function (e) {
    var chip = e.target.closest('[data-ask]');
    if (!chip) return;
    var wrap = chip.closest('.chat-msg-wrapper');
    if (wrap) wrap.parentNode.removeChild(wrap);
    send(chip.getAttribute('data-ask'));
  });

  /* Skip lead capture */
  messages.addEventListener('click', function (e) {
    var skip = e.target.closest('[data-skip]');
    if (!skip) return;
    var stage = skip.getAttribute('data-skip');
    var wrap = skip.closest('.chat-msg-wrapper');
    if (wrap) wrap.removeChild(skip.parentNode);
    if (stage === 'name') {
      state.leadStage = 'email';
      SS.setItem('pie_lead_stage', 'email');
      push('user', 'Skip');
      askLeadStage();
    } else {
      state.leadStage = 'done';
      SS.setItem('pie_lead_stage', 'done');
      push('user', 'Skip');
      push('bot', 'No problem! How can I help you grow today?');
    }
  });

  /* Fallback / handoff messages */
  function aliaFallback(message) {
    var base = (window.PIE && window.PIE.base) ? window.PIE.base : '';
    pushHtml(esc(message || PIE.fallback || "I don't want to guess. You can speak with the TPT team here.")
      + '<div class="chat-lead-actions"><a href="' + esc(base) + '/contact" class="handoff-btn">Talk to a Human →</a></div>');
  }

  function aliaConnectionError() {
    var base = (window.PIE && window.PIE.base) ? window.PIE.base : '';
    pushError(PIE.error || 'I\'m having trouble connecting right now. Please try again in a moment.');
    pushHtml('<div class="chat-lead-actions"><a href="' + esc(base) + '/contact" class="handoff-btn">Talk to a Human →</a></div>');
  }

  /* ---------- Send Message ---------- */
  var timer = null;
  var controller = null;

  function send(text) {
    text = String(text || '').trim();
    if (!text || state.busy) return;

    /* Lead capture interception */
    if (state.leadStage === 'name') {
      state.lead.name = text.slice(0, 120);
      SS.setItem('pie_lead_name', state.lead.name);
      state.leadStage = 'email';
      SS.setItem('pie_lead_stage', 'email');
      push('user', text);
      askLeadStage();
      return;
    }
    if (state.leadStage === 'email') {
      var looksLikeEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(text);
      if (looksLikeEmail) {
        state.lead.email = text.slice(0, 150);
        SS.setItem('pie_lead_email', state.lead.email);
        state.leadStage = 'done';
        SS.setItem('pie_lead_stage', 'done');
        push('user', text);
        push('bot', 'Perfect, ' + (state.lead.name || 'friend') + ' — noted! Our team can reach you at ' + state.lead.email + '. Now, how can I help?');
        return;
      }
      state.leadStage = 'done';
      SS.setItem('pie_lead_stage', 'done');
    }

    state.lastUserText = text;
    push('user', text);
    state.history.push({ role: 'user', content: text });
    saveHistory();
    state.busy = true;
    sendBtn.disabled = true;
    input.disabled = true;
    typing(true);

    var payload = {
      csrf_token: PIE.csrf,
      message: text,
      history: state.history.slice(-12),
      session_id: state.sessionId,
      name: state.lead.name,
      email: state.lead.email
    };

    controller = typeof AbortController === 'function' ? new AbortController() : null;
    timer = controller ? setTimeout(function () { controller.abort(); }, 30000) : null;

    fetch(PIE.api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      signal: controller ? controller.signal : undefined
    })
      .then(function (res) {
        return res.json().catch(function () { throw new Error('malformed response'); });
      })
      .then(function (json) {
        typing(false);
        if (json && json.success && json.reply) {
          push('bot', json.reply);
          state.history.push({ role: 'model', content: json.reply });
          saveHistory();
        } else if (json && json.reply && json.code && json.code !== 'provider' && json.code !== 'empty') {
          aliaFallback(json.reply);
        } else {
          aliaConnectionError();
        }
      })
      .catch(function (err) {
        typing(false);
        if (err.name === 'AbortError') {
          aliaConnectionError();
        } else {
          aliaConnectionError();
        }
      })
      .then(function () {
        if (timer) clearTimeout(timer);
        state.busy = false;
        sendBtn.disabled = false;
        input.disabled = false;
        input.focus();
      });
  }

  /* Retry a failed message from the error bubble. */
  messages.addEventListener('click', function (e) {
    var retry = e.target.closest('[data-retry]');
    if (!retry) return;
    var wrap = retry.closest('.chat-msg-wrapper');
    if (wrap) { wrap.parentNode.removeChild(wrap); }
    if (state.lastUserText) { send(state.lastUserText); }
  });

  /* ---------- Event Listeners ---------- */
  fab.addEventListener('click', function () { setOpen(!state.open); });
  if (closeBtn) closeBtn.addEventListener('click', function () { setOpen(false); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && state.open) setOpen(false);
  });
  sendBtn.addEventListener('click', function () { send(input.value); input.value = ''; });
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(input.value); input.value = ''; }
  });

  /* Expose for debugging */
  window.PIEChatbot = { send: send, open: function() { setOpen(true); }, close: function() { setOpen(false); } };
})();