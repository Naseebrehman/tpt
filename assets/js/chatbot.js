/* ===========================================================================
   PIE Bot — Gemini-powered chat widget client
   History lives in sessionStorage; optional lead capture (name + email).
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
    leadStage: SS.getItem('pie_lead_stage') || 'name',   /* name -> email -> done */
    lead: {
      name: SS.getItem('pie_lead_name') || '',
      email: SS.getItem('pie_lead_email') || ''
    },
    history: []
  };
  try { state.history = JSON.parse(SS.getItem('pie_history') || '[]'); } catch (e) { state.history = []; }
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
    SS.setItem('pie_history', JSON.stringify(state.history.slice(-12)));
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
    push('bot', 'Hey 👋 I\'m PIE Bot, the assistant for The Pie Technologies. Ask me anything about our services — Meta Ads, SEO, social media, web development and more.');
    if (state.leadStage !== 'done') askLeadStage();
  }

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
    state.history.push({ role: 'user', parts: [{ text: text }] });
    saveHistory();
    state.busy = true;
    typing(true);

    var payload = {
      message: text,
      history: state.history.slice(-12),
      session_id: state.sessionId,
      name: state.lead.name,
      email: state.lead.email
    };

    fetch(PIE.api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        typing(false);
        var reply = (json && json.reply) ? json.reply : 'Sorry, I\'m having trouble connecting. Please email us at hello@thepietechnologies.com or use the contact form.';
        push('bot', reply);
        state.history.push({ role: 'model', parts: [{ text: reply }] });
        saveHistory();
      })
      .catch(function () {
        typing(false);
        push('bot', 'Sorry, I\'m having trouble connecting. Please email us at hello@thepietechnologies.com or use the contact form.');
      })
      .then(function () { state.busy = false; });
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
