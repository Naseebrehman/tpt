/* ===========================================================================
   Alia — the TPT growth assistant (chat widget client)
   History lives in sessionStorage; optional lead capture (name + email).
   Robust networking: request timeouts, malformed-response handling and a
   professional fallback message whenever the AI provider fails, so the
   widget never becomes stuck (Task 13).
   =========================================================================== */
(function () {
  'use strict';

  var PIE = window.PIE || { api: 'chatbot-api.php' };
  var SS = window.sessionStorage;

  var fab = document.getElementById('chatbotFab');
  var win = document.getElementById('chatbotWindow');
  var closeBtn = document.getElementById('chatbotClose');
  var messages = document.getElementById('chatbotMessages');
  var input = document.getElementById('chatbotInput');
  var sendBtn = document.getElementById('chatbotSend');
  if (!fab || !win || !messages || !input || !sendBtn) return;

  var state = {
    open: false,
    busy: false,
    sessionId: SS.getItem('pie_session') || ('s_' + Math.random().toString(36).slice(2) + Date.now().toString(36)),
    leadStage: PIE.leadCollection === false ? 'done' : (SS.getItem('pie_lead_stage') || 'name'),   /* name -> email -> done */
    lead: {
      name: SS.getItem('pie_lead_name') || '',
      email: SS.getItem('pie_lead_email') || ''
    },
    history: []
  };
  try { state.history = JSON.parse(SS.getItem('pie_history') || '[]'); } catch (e) { state.history = []; }
  if (!Array.isArray(state.history)) state.history = [];
  SS.setItem('pie_session', state.sessionId);

  /* ------------------------------ helpers ------------------------------ */
  function esc(text) {
    var d = document.createElement('div');
    d.textContent = String(text);
    return d.innerHTML;
  }
  function push(role, text) {
    var div = document.createElement('div');
    div.className = 'chat-msg ' + role;
    div.textContent = text;
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
    return div;
  }
  function pushHtml(html) {
    var div = document.createElement('div');
    div.className = 'chat-msg bot';
    div.innerHTML = html;
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
    return div;
  }
  function typing(on) {
    var el = messages.querySelector('.typing');
    if (on && !el) {
      el = document.createElement('div');
      el.className = 'typing';
      el.innerHTML = '<i></i><i></i><i></i>';
      messages.appendChild(el);
      messages.scrollTop = messages.scrollHeight;
    } else if (!on && el) {
      el.parentNode.removeChild(el);
    }
  }
  function saveHistory() {
    try { SS.setItem('pie_history', JSON.stringify(state.history.slice(-12))); } catch (e) { /* storage full — ignore */ }
  }
  function setOpen(open) {
    state.open = open;
    win.classList.toggle('open', open);
    win.setAttribute('aria-hidden', open ? 'false' : 'true');
    fab.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      setTimeout(function () { input.focus(); }, 250);
      if (!messages.childElementCount) startConversation();
    }
  }

  /* --------------------------- lead capture ---------------------------- */
  function askLeadStage() {
    if (state.leadStage === 'name') {
      pushHtml('Before we start — what&#39;s your name? <span class="text-muted" style="font-size:.75rem">(helps us personalise things)</span>'
        + '<div class="chat-lead-actions"><button type="button" data-skip="name">Skip</button></div>');
    } else if (state.leadStage === 'email') {
      pushHtml('Thanks! And what&#39;s your email so our team can follow up?'
        + '<div class="chat-lead-actions"><button type="button" data-skip="email">Skip</button></div>');
    }
  }
  function startConversation() {
    push('bot', PIE.welcome || "Hi, I'm Alia. How can I help you today?");
    pushHtml('<div class="chat-suggests">'
      + '<button type="button" data-ask="What services do you offer?">What services do you offer?</button>'
      + '<button type="button" data-ask="I need more leads">I need more leads</button>'
      + '<button type="button" data-ask="Tell me about your SEO approach">Your SEO approach</button>'
      + '<button type="button" data-ask="I want to start a project">Start a project</button>'
      + '</div>');
    if (state.leadStage !== 'done') askLeadStage();
  }

  /* suggested-question chips */
  messages.addEventListener('click', function (e) {
    var chip = e.target.closest('[data-ask]');
    if (!chip) return;
    var wrap = chip.closest('.chat-msg');
    if (wrap) wrap.parentNode.removeChild(wrap);
    send(chip.getAttribute('data-ask'));
  });

  messages.addEventListener('click', function (e) {
    var skip = e.target.closest('[data-skip]');
    if (!skip) return;
    var stage = skip.getAttribute('data-skip');
    var wrap = skip.closest('.chat-msg');
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

  /* Alia never guesses — when she can't answer, she hands off to a human. */
  function aliaFallback(message) {
    var base = (window.PIE && window.PIE.base) ? window.PIE.base : '';
    pushHtml(esc(message || PIE.fallback || "I don't want to guess. You can speak with the TPT team here.")
      + '<div class="chat-lead-actions"><a href="' + esc(base) + '/contact">Talk to a Human →</a></div>');
  }

  /* Connection problems get their own professional message (Task 13). */
  function aliaConnectionError() {
    pushHtml(esc(PIE.error || 'I’m having trouble connecting right now. Please try again in a moment.')
      + '<div class="chat-lead-actions"><a href="' + esc((window.PIE && window.PIE.base) || '') + '/contact">Talk to a Human →</a></div>');
  }

  /* ------------------------------- sending ----------------------------- */
  function send(text) {
    text = String(text || '').trim();
    if (!text || state.busy) return;

    /* lead capture interception */
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
      /* not an email: treat as a normal question, stop asking */
      state.leadStage = 'done';
      SS.setItem('pie_lead_stage', 'done');
    }

    push('user', text);
    state.history.push({ role: 'user', content: text });
    saveHistory();
    state.busy = true;
    typing(true);

    var payload = {
      csrf_token: PIE.csrf,
      message: text,
      history: state.history.slice(-12),
      session_id: state.sessionId,
      name: state.lead.name,
      email: state.lead.email
    };

    /* Timeout keeps the widget usable even when the network hangs (Task 13). */
    var controller = typeof AbortController === 'function' ? new AbortController() : null;
    var timer = controller ? setTimeout(function () { controller.abort(); }, 40000) : null;

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
          /* Server-sent fallback/config message with a human hand-off. */
          aliaFallback(json.reply);
        } else {
          aliaConnectionError();
        }
      })
      .catch(function () {
        typing(false);
        aliaConnectionError();
      })
      .then(function () {
        if (timer) clearTimeout(timer);
        state.busy = false;
      });
  }

  /* ------------------------------- wiring ------------------------------ */
  fab.addEventListener('click', function () { setOpen(!state.open); });
  if (closeBtn) closeBtn.addEventListener('click', function () { setOpen(false); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && state.open) setOpen(false);
  });
  sendBtn.addEventListener('click', function () { send(input.value); input.value = ''; input.focus(); });
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); send(input.value); input.value = ''; }
  });
})();
