(function () {
  function boot() {
    var root = document.getElementById('foundation-admin-app');
    if (!root || root.dataset.frontdeskBooted === '1' || !window.foundationFrontdeskAdmin) {
      return;
    }

    root.dataset.frontdeskBooted = '1';

    var adminShell = root.querySelector('.foundation-app-root');
    var themeButton = root.querySelector('.foundation-shell-theme');
    var darkHidden = root.querySelector('#fnd-dark-hidden');

    function syncThemeField() {
      if (!darkHidden || !adminShell) {
        return;
      }
      darkHidden.value = adminShell.classList.contains('is-dark') ? '1' : '0';
    }

    syncThemeField();

    if (themeButton) {
      themeButton.addEventListener('click', function () {
        window.setTimeout(syncThemeField, 0);
      });
    }

    var providerSel = root.querySelector('#fd_provider');
    var forceChk = root.querySelector('#fd_force_off');
    var keysWrap = root.querySelector('#fd-provider-keys');

    function toggleKeys() {
      if (!keysWrap) {
        return;
      }
      var provider = providerSel ? providerSel.value : 'offline';
      var forceOffline = !!(forceChk && forceChk.checked);
      keysWrap.style.display = provider !== 'offline' && !forceOffline ? 'block' : 'none';
    }

    if (providerSel) {
      providerSel.addEventListener('change', toggleKeys);
    }

    if (forceChk) {
      forceChk.addEventListener('change', toggleKeys);
    }

    toggleKeys();

    var jsonField = root.querySelector('#fd-offline-flow-json');
    var flowList = root.querySelector('#fd-flow-list');
    var addStepButton = root.querySelector('#fd-add-step');
    var loadTemplateButton = root.querySelector('#fd-load-template');
    var clearFlowButton = root.querySelector('#fd-clear');
    var form = root.querySelector('#fnd-admin-form');

    var flow = [];
    if (jsonField) {
      try {
        flow = JSON.parse(jsonField.value || '[]') || [];
      } catch (error) {
        flow = [];
      }
    }
    if (!Array.isArray(flow)) {
      flow = [];
    }

    function nextFlowId() {
      var index = 1;
      var used = {};
      flow.forEach(function (node) {
        if (node && node.id) {
          used[node.id] = true;
        }
      });
      while (used['s' + index]) {
        index += 1;
      }
      return 's' + index;
    }

    function renderFlowButtons(node, buttonWrap, ids) {
      buttonWrap.innerHTML = '';
      (node.buttons || []).forEach(function (button, buttonIndex) {
        var row = document.createElement('div');
        row.className = 'fd-row';

        var options = [
          '<option value="end"' + (button.action === 'end' ? ' selected' : '') + '>End</option>',
          '<option value="contact"' + (button.action === 'contact' ? ' selected' : '') + '>Contact form</option>',
          '<option value="search"' + (button.action === 'search' ? ' selected' : '') + '>Search site</option>',
          '<option disabled>----------</option>'
        ];

        ids.forEach(function (id) {
          var value = 'next:' + id;
          options.push('<option value="' + value + '"' + (button.action === value ? ' selected' : '') + '>→ ' + id + '</option>');
        });

        row.innerHTML = ''
          + '<input type="text" class="fd-btn-label" style="width:240px" placeholder="e.g. Contact support" value="' + (button.label || '').replace(/"/g, '&quot;') + '">'
          + '<select class="fd-btn-action" style="min-width:220px">' + options.join('') + '</select>'
          + '<button type="button" class="fd-btn fd-btn--del fd-del-btn" aria-label="Delete button">×</button>';

        row.querySelector('.fd-btn-label').addEventListener('input', function (event) {
          button.label = event.target.value;
        });
        row.querySelector('.fd-btn-action').addEventListener('change', function (event) {
          button.action = event.target.value;
        });
        row.querySelector('.fd-del-btn').addEventListener('click', function () {
          node.buttons.splice(buttonIndex, 1);
          renderFlow();
        });

        buttonWrap.appendChild(row);
      });
    }

    function renderFlow() {
      if (!flowList) {
        return;
      }

      flowList.innerHTML = '';
      var ids = flow.map(function (node) { return node.id; });

      flow.forEach(function (node, index) {
        var card = document.createElement('div');
        card.className = 'fd-step';
        card.innerHTML = ''
          + '<div class="fd-row" style="grid-template-columns:180px 1fr auto">'
          + '  <div class="fd-id">'
          + '    <label class="fd-subtle">Step ID</label>'
          + '    <input type="text" class="fd-input-id" value="' + (node.id || '').replace(/"/g, '&quot;') + '" placeholder="e.g. welcome, hours, pricing">'
          + '  </div>'
          + '  <div class="fd-col">'
          + '    <label class="fd-subtle">Bot message</label>'
          + '    <textarea class="fd-input-msg" rows="2" placeholder="What should the bot say in this step?">' + (node.message || '') + '</textarea>'
          + '    <p class="fd-help">Keep it short and friendly. Add buttons for next steps, contact, or search.</p>'
          + '  </div>'
          + '  <div class="fd-actions">'
          + '    <button type="button" class="fd-btn fd-btn--ghost fd-move-up" aria-label="Move up">▲</button>'
          + '    <button type="button" class="fd-btn fd-btn--ghost fd-move-down" aria-label="Move down">▼</button>'
          + '    <button type="button" class="fd-btn fd-btn--del fd-del-step">Delete</button>'
          + '  </div>'
          + '</div>'
          + '<div class="fd-row" style="grid-template-columns:1fr;">'
          + '  <div class="fd-col">'
          + '    <label class="fd-subtle">Buttons</label>'
          + '    <div class="fd-buttons"></div>'
          + '    <button type="button" class="fd-btn fd-add-btn" style="margin-top:6px">Add button</button>'
          + '    <p class="fd-help">Actions: End, contact form, search site, or move to the next step.</p>'
          + '  </div>'
          + '</div>';

        card.querySelector('.fd-input-id').addEventListener('input', function (event) {
          node.id = (event.target.value || '').toLowerCase().replace(/[^a-z0-9_-]/g, '').slice(0, 32);
        });
        card.querySelector('.fd-input-msg').addEventListener('input', function (event) {
          node.message = event.target.value;
        });
        card.querySelector('.fd-move-up').addEventListener('click', function () {
          if (index > 0) {
            var previous = flow[index - 1];
            flow[index - 1] = flow[index];
            flow[index] = previous;
            renderFlow();
          }
        });
        card.querySelector('.fd-move-down').addEventListener('click', function () {
          if (index < flow.length - 1) {
            var next = flow[index + 1];
            flow[index + 1] = flow[index];
            flow[index] = next;
            renderFlow();
          }
        });
        card.querySelector('.fd-del-step').addEventListener('click', function () {
          flow.splice(index, 1);
          renderFlow();
        });

        var buttonWrap = card.querySelector('.fd-buttons');
        card.querySelector('.fd-add-btn').addEventListener('click', function () {
          node.buttons = node.buttons || [];
          node.buttons.push({ label: '', action: 'end' });
          renderFlow();
        });
        renderFlowButtons(node, buttonWrap, ids);

        flowList.appendChild(card);
      });
    }

    function loadStarterFlow() {
      flow = [
        {
          id: 'welcome',
          message: 'I might not have every answer yet. What would you like to do?',
          buttons: [
            { label: 'Find a page', action: 'search' },
            { label: 'Contact support', action: 'contact' },
            { label: 'Opening hours', action: 'next:hours' }
          ]
        },
        {
          id: 'hours',
          message: 'Our hours are Mon-Fri 9am-5pm, Sat-Sun closed.',
          buttons: [
            { label: 'Contact support', action: 'contact' },
            { label: 'Back', action: 'next:welcome' }
          ]
        }
      ];
      renderFlow();
    }

    if (addStepButton) {
      addStepButton.addEventListener('click', function () {
        flow.push({ id: nextFlowId(), message: '', buttons: [] });
        renderFlow();
      });
    }
    if (loadTemplateButton) {
      loadTemplateButton.addEventListener('click', loadStarterFlow);
    }
    if (clearFlowButton) {
      clearFlowButton.addEventListener('click', function () {
        flow = [];
        renderFlow();
      });
    }
    renderFlow();

    var faqField = root.querySelector('#fnd-faqs-json');
    var faqList = root.querySelector('#faq-list');
    var faqAdd = root.querySelector('#faq-add');
    var faqLoad = root.querySelector('#faq-load');
    var faqClear = root.querySelector('#faq-clear');
    var faqs = [];

    if (faqField) {
      try {
        faqs = JSON.parse(faqField.value || '[]') || [];
      } catch (error) {
        faqs = [];
      }
    }
    if (!Array.isArray(faqs)) {
      faqs = [];
    }

    function renderFaqs() {
      if (!faqList) {
        return;
      }

      faqList.innerHTML = '';

      faqs.forEach(function (faq, index) {
        var card = document.createElement('div');
        card.className = 'fd-faq';
        card.innerHTML = ''
          + '<div class="fd-row">'
          + '  <input type="text" class="fd-faq-q" placeholder="Question" value="' + (faq.q || '').replace(/"/g, '&quot;') + '">'
          + '  <div class="fd-actions">'
          + '    <button type="button" class="fd-btn fd-btn--ghost fd-up" aria-label="Move up">▲</button>'
          + '    <button type="button" class="fd-btn fd-btn--ghost fd-down" aria-label="Move down">▼</button>'
          + '    <button type="button" class="fd-btn fd-btn--del fd-del-faq" aria-label="Delete">×</button>'
          + '  </div>'
          + '</div>'
          + '<div class="fd-row" style="grid-template-columns:1fr;">'
          + '  <textarea class="fd-faq-a" rows="2" placeholder="Answer">' + (faq.a || '') + '</textarea>'
          + '</div>'
          + '<div class="fd-row">'
          + '  <input type="url" class="fd-faq-url" placeholder="Optional link (Learn more)" value="' + (faq.url || '').replace(/"/g, '&quot;') + '">'
          + '</div>';

        card.querySelector('.fd-faq-q').addEventListener('input', function (event) {
          faqs[index].q = event.target.value;
        });
        card.querySelector('.fd-faq-a').addEventListener('input', function (event) {
          faqs[index].a = event.target.value;
        });
        card.querySelector('.fd-faq-url').addEventListener('input', function (event) {
          faqs[index].url = event.target.value;
        });
        card.querySelector('.fd-up').addEventListener('click', function () {
          if (index > 0) {
            var previous = faqs[index - 1];
            faqs[index - 1] = faqs[index];
            faqs[index] = previous;
            renderFaqs();
          }
        });
        card.querySelector('.fd-down').addEventListener('click', function () {
          if (index < faqs.length - 1) {
            var next = faqs[index + 1];
            faqs[index + 1] = faqs[index];
            faqs[index] = next;
            renderFaqs();
          }
        });
        card.querySelector('.fd-del-faq').addEventListener('click', function () {
          faqs.splice(index, 1);
          renderFaqs();
        });

        faqList.appendChild(card);
      });
    }

    if (faqAdd) {
      faqAdd.addEventListener('click', function () {
        faqs.push({ q: '', a: '', url: '' });
        renderFaqs();
      });
    }
    if (faqLoad) {
      faqLoad.addEventListener('click', function () {
        faqs = Array.isArray(window.foundationFrontdeskAdmin.faqSeed)
          ? JSON.parse(JSON.stringify(window.foundationFrontdeskAdmin.faqSeed))
          : [];
        renderFaqs();
      });
    }
    if (faqClear) {
      faqClear.addEventListener('click', function () {
        faqs = [];
        renderFaqs();
      });
    }
    renderFaqs();

    var postTypeSelect = root.querySelector('#rag_post_types');
    var postTypeHidden = root.querySelector('#rag_post_types_hidden');

    function selectedPostTypes() {
      if (!postTypeSelect) {
        return [];
      }
      return Array.prototype.slice.call(postTypeSelect.selectedOptions || []).map(function (item) {
        return item.value;
      });
    }

    if (form) {
      form.addEventListener('submit', function () {
        if (jsonField) {
          jsonField.value = JSON.stringify(flow);
        }
        if (faqField) {
          faqField.value = JSON.stringify(faqs.filter(function (item) {
            return item.q && item.a;
          }));
        }
        if (postTypeHidden) {
          postTypeHidden.value = selectedPostTypes().join(',');
        }
        syncThemeField();
      });
    }

    var ragStart = root.querySelector('#fnd-rag-start');
    var ragStop = root.querySelector('#fnd-rag-stop');
    var ragBar = root.querySelector('#fnd-rag-bar-fill');
    var ragStatus = root.querySelector('#fnd-rag-status');
    var ragPoll = null;

    function updateRagUi(state) {
      var total = parseInt((state && state.total) || 0, 10) || 0;
      var indexed = parseInt((state && state.indexed) || 0, 10) || 0;
      var percent = total > 0 ? Math.min(100, Math.round((indexed / total) * 100)) : (state && state.status === 'complete' ? 100 : 0);

      if (ragBar) {
        ragBar.style.width = percent + '%';
      }
      if (ragStatus) {
        ragStatus.textContent = ((state && state.status) || 'idle').toUpperCase() + (total ? (' - ' + indexed + '/' + total + ' (' + percent + '%)') : '');
      }
      if (ragStart) {
        ragStart.disabled = state && state.status === 'scanning';
      }
    }

    function ajax(body) {
      return window.fetch(window.foundationFrontdeskAdmin.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: new URLSearchParams(body)
      }).then(function (response) {
        return response.json();
      });
    }

    function pollRagStatus() {
      ajax({
        action: 'fnd_conversa_rag_status',
        nonce: window.foundationFrontdeskAdmin.nonce
      }).then(function (response) {
        if (response && response.success && response.data) {
          updateRagUi(response.data);
          if (response.data.status === 'scanning') {
            ragPoll = window.setTimeout(pollRagStatus, 1200);
          }
        }
      }).catch(function () {
        // noop
      });
    }

    if (ragStart) {
      ragStart.addEventListener('click', function () {
        var body = {
          action: 'fnd_conversa_rag_start',
          nonce: window.foundationFrontdeskAdmin.nonce
        };

        selectedPostTypes().forEach(function (postType) {
          if (!body['post_types[]']) {
            body['post_types[]'] = [];
          }
        });

        var params = new URLSearchParams();
        params.append('action', 'fnd_conversa_rag_start');
        params.append('nonce', window.foundationFrontdeskAdmin.nonce);
        selectedPostTypes().forEach(function (postType) {
          params.append('post_types[]', postType);
        });

        window.fetch(window.foundationFrontdeskAdmin.ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: params
        }).then(function (response) {
          return response.json();
        }).then(function (response) {
          if (response && response.success && response.data) {
            updateRagUi(response.data);
            pollRagStatus();
          }
        });
      });
    }

    if (ragStop) {
      ragStop.addEventListener('click', function () {
        ajax({
          action: 'fnd_conversa_rag_stop',
          nonce: window.foundationFrontdeskAdmin.nonce
        }).then(function (response) {
          if (response && response.success && response.data) {
            updateRagUi(response.data);
            if (ragPoll) {
              window.clearTimeout(ragPoll);
            }
          }
        });
      });
    }
  }

  window.addEventListener('foundation-admin:ready', function (event) {
    if (event.detail && event.detail.plugin === 'frontdesk') {
      boot();
    }
  });

  document.addEventListener('DOMContentLoaded', boot);
})();
