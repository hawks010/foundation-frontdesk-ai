(function () {
  function qs(root, sel) {
    return root.querySelector(sel);
  }

  function createNode(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (typeof text === 'string') node.textContent = text;
    return node;
  }

  function buildMessage(root, role, text, response) {
    const wrap = createNode('div', 'frontdesk-ai__message frontdesk-ai__message--' + role);
    const bubble = createNode('div', 'frontdesk-ai__bubble', text);
    const meta = createNode('div', 'frontdesk-ai__meta', new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));

    wrap.appendChild(bubble);

    if (response && Array.isArray(response.sources) && response.sources.length) {
      const sources = createNode('div', 'frontdesk-ai__sources');
      response.sources.forEach(function (source) {
        if (!source || !source.title) return;
        const link = createNode(source.url ? 'a' : 'span', 'frontdesk-ai__source', source.title);
        if (source.url) {
          link.href = source.url;
          link.target = '_blank';
          link.rel = 'noopener';
        }
        sources.appendChild(link);
      });
      bubble.appendChild(sources);
    }

    if (response && Array.isArray(response.actions) && response.actions.length) {
      const actions = createNode('div', 'frontdesk-ai__actions');
      response.actions.forEach(function (action) {
        if (!action || !action.label) return;
        const button = createNode('button', 'frontdesk-ai__action', action.label);
        button.type = 'button';
        button.dataset.actionType = action.type || '';
        actions.appendChild(button);
      });
      bubble.appendChild(actions);
    }

    wrap.appendChild(meta);
    root.appendChild(wrap);
    root.scrollTop = root.scrollHeight;
    return wrap;
  }

  function filterFaqs(instance, value) {
    const list = qs(instance.root, '[data-frontdesk-kb-list]');
    if (!list) return;
    const query = (value || '').trim().toLowerCase();
    while (list.firstChild) {
      list.removeChild(list.firstChild);
    }
    instance.boot.faqs
      .filter(function (faq) {
        if (!query) return true;
        return ((faq.q || '') + ' ' + (faq.a || '')).toLowerCase().indexOf(query) !== -1;
      })
      .forEach(function (faq) {
        const item = createNode('div', 'frontdesk-ai__kb-item');
        const q = createNode('button', 'frontdesk-ai__kb-question', faq.q || 'FAQ');
        q.type = 'button';
        const a = createNode('div', 'frontdesk-ai__kb-answer', faq.a || '');
        a.hidden = true;
        q.addEventListener('click', function () {
          a.hidden = !a.hidden;
        });
        item.appendChild(q);
        item.appendChild(a);
        list.appendChild(item);
      });
  }

  function setOpen(instance, open) {
    if (instance.display !== 'floating') return;
    instance.root.hidden = !open;
    if (instance.launcher) {
      instance.launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    if (instance.teaser) {
      instance.teaser.hidden = true;
    }
    if (open && instance.input) {
      setTimeout(function () { instance.input.focus(); }, 30);
    }
    if (!open && instance.launcher) {
      instance.launcher.focus();
    }
  }

  function setKb(instance, open) {
    if (!instance.kb) return;
    instance.kb.hidden = !open;
    const toggle = qs(instance.root, '[data-frontdesk-kb-toggle]');
    if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      filterFaqs(instance, '');
      const filter = qs(instance.root, '[data-frontdesk-kb-filter]');
      if (filter) setTimeout(function () { filter.focus(); }, 20);
    } else if (instance.input) {
      instance.input.focus();
    }
  }

  function submitContact(instance, form) {
    const status = qs(form, '[data-frontdesk-contact-status]');
    const body = new URLSearchParams(new FormData(form));
    body.set('action', instance.boot.contactAction);
    body.set('nonce', instance.boot.contactNonce);
    body.set('conversation_id', instance.conversationId);

    fetch(instance.boot.ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        status.textContent = data && data.message ? data.message : instance.boot.copy.errorMessage;
        if (data && data.ok) {
          form.reset();
        }
      })
      .catch(function () {
        status.textContent = instance.boot.copy.errorMessage;
      });
  }

  function handleChat(instance, text) {
    const body = new URLSearchParams();
    body.set('action', instance.boot.chatAction);
    body.set('nonce', instance.boot.chatNonce);
    body.set('message', text);
    body.set('conversation_id', instance.conversationId);
    body.set('page_url', window.location.href);
    body.set('page_title', document.title);
    body.set('instance_id', instance.boot.instanceId);

    const loading = buildMessage(instance.messages, 'bot', '…');
    fetch(instance.boot.ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (loading && loading.parentNode) loading.parentNode.removeChild(loading);
        buildMessage(instance.messages, 'bot', data && data.answer ? data.answer : instance.boot.copy.errorMessage, data);
      })
      .catch(function () {
        if (loading && loading.parentNode) loading.parentNode.removeChild(loading);
        buildMessage(instance.messages, 'bot', instance.boot.copy.errorMessage);
      });
  }

  function init(root) {
    if (!root || root.dataset.frontdeskBound === '1') return;
    root.dataset.frontdeskBound = '1';

    const bootEl = document.getElementById(root.id + '-boot');
    if (!bootEl) return;

    let boot = {};
    try {
      boot = JSON.parse(bootEl.textContent || '{}');
    } catch (error) {
      boot = {};
    }
    const instance = {
      root: root,
      boot: boot,
      display: root.classList.contains('frontdesk-ai--floating') ? 'floating' : 'inline',
      launcher: document.querySelector('[data-frontdesk-launcher="' + boot.instanceId + '"]'),
      teaser: document.querySelector('[data-frontdesk-teaser="' + boot.instanceId + '"]'),
      kb: qs(root, '[data-frontdesk-kb]'),
      messages: qs(root, '[data-frontdesk-messages]'),
      input: qs(root, '[data-frontdesk-input]'),
      conversationId: boot.instanceId + '-' + Date.now()
    };

    if (!instance.messages || !instance.input || !boot.copy) return;

    buildMessage(instance.messages, 'bot', boot.copy.greeting || 'Hi, how can I help?', null);
    filterFaqs(instance, '');

    if (instance.launcher) {
      instance.launcher.addEventListener('mouseenter', function () { if (instance.teaser) instance.teaser.hidden = false; });
      instance.launcher.addEventListener('focus', function () { if (instance.teaser) instance.teaser.hidden = false; });
      instance.launcher.addEventListener('mouseleave', function () { if (instance.teaser) instance.teaser.hidden = true; });
      instance.launcher.addEventListener('click', function () { setOpen(instance, true); });
    }

    if (instance.teaser) {
      instance.teaser.addEventListener('mouseleave', function () { instance.teaser.hidden = true; });
      const openBtn = instance.teaser.querySelector('[data-frontdesk-open]');
      const openKbBtn = instance.teaser.querySelector('[data-frontdesk-open-kb]');
      if (openBtn) openBtn.addEventListener('click', function () { setOpen(instance, true); });
      if (openKbBtn) openKbBtn.addEventListener('click', function () { setOpen(instance, true); setKb(instance, true); });
    }

    const form = qs(root, '[data-frontdesk-form]');
    if (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        const text = (instance.input.value || '').trim();
        if (!text) return;
        buildMessage(instance.messages, 'user', text);
        instance.input.value = '';
        handleChat(instance, text);
      });
    }

    const contactToggle = qs(root, '[data-frontdesk-contact-toggle]');
    const contactForm = qs(root, '[data-frontdesk-contact]');
    if (contactToggle && contactForm) {
      contactToggle.addEventListener('click', function () {
        const isOpen = contactToggle.getAttribute('aria-expanded') === 'true';
        contactToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
        contactForm.hidden = isOpen;
      });
      contactForm.addEventListener('submit', function (event) {
        event.preventDefault();
        submitContact(instance, contactForm);
      });
    }

    const kbToggle = qs(root, '[data-frontdesk-kb-toggle]');
    const kbClose = qs(root, '[data-frontdesk-kb-close]');
    const kbFilter = qs(root, '[data-frontdesk-kb-filter]');
    if (kbToggle) kbToggle.addEventListener('click', function () { setKb(instance, instance.kb.hidden); });
    if (kbClose) kbClose.addEventListener('click', function () { setKb(instance, false); });
    if (kbFilter) kbFilter.addEventListener('input', function () { filterFaqs(instance, kbFilter.value); });

    root.addEventListener('click', function (event) {
      const action = event.target.closest('.frontdesk-ai__action');
      if (!action) return;
      const type = action.dataset.actionType;
      if (type === 'contact' && contactToggle) {
        contactToggle.click();
      }
      if (type === 'faq') {
        setKb(instance, true);
      }
    });

    const closeBtn = qs(root, '[data-frontdesk-close]');
    if (closeBtn) closeBtn.addEventListener('click', function () { setOpen(instance, false); });

    root.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        if (instance.kb && !instance.kb.hidden) {
          setKb(instance, false);
          return;
        }
        setOpen(instance, false);
      }
    });
  }

  function bootAll() {
    document.querySelectorAll('[data-frontdesk-root]').forEach(init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootAll);
  } else {
    bootAll();
  }
})();
