(() => {
  'use strict';

  const cfg = window.OrionAI;
  if (!cfg) return;

  const root = document.createElement('div');
  root.id = 'orion-ai-root';
  root.className = `orion-ai-${cfg.position}`;
  root.style.setProperty('--orion-accent', cfg.primaryColor);
  root.innerHTML = `
    <button class="orion-ai-launcher" type="button" aria-label="Open ${esc(cfg.title)}" aria-expanded="false">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11.5a8 8 0 0 1-8.5 8 8.7 8.7 0 0 1-3.4-.7L4 20l1.25-3.65A8 8 0 1 1 20 11.5Z"/><path d="M8 10h8M8 13.5h5"/></svg>
    </button>
    <section class="orion-ai-panel" aria-hidden="true" aria-label="${esc(cfg.title)}">
      <header><div><strong>${esc(cfg.title)}</strong><span>Product & store help</span></div><div class="orion-ai-header-actions"><button class="orion-ai-clear" type="button" aria-label="Clear chat view" title="Clear chat view">↻</button><button class="orion-ai-close" type="button" aria-label="Close chat">×</button></div></header>
      <div class="orion-ai-messages" role="log" aria-live="polite"></div>
      <div class="orion-ai-counter">${Number(cfg.questionsPerSession)} questions available</div>
      <form class="orion-ai-form"><label class="screen-reader-text" for="orion-ai-input">Your question</label><textarea id="orion-ai-input" rows="1" maxlength="1200" placeholder="Ask about products, delivery or returns…" required></textarea><button type="submit" aria-label="Send message"><svg viewBox="0 0 24 24"><path d="m4 4 17 8-17 8 3-8-3-8Zm3 8h14"/></svg></button></form>
      <small class="orion-ai-disclaimer">AI can make mistakes. Check product instructions before use.</small>
    </section>`;
  document.body.appendChild(root);

  const launcher = root.querySelector('.orion-ai-launcher');
  const panel = root.querySelector('.orion-ai-panel');
  const close = root.querySelector('.orion-ai-close');
  const clear = root.querySelector('.orion-ai-clear');
  const form = root.querySelector('.orion-ai-form');
  const input = root.querySelector('#orion-ai-input');
  const messages = root.querySelector('.orion-ai-messages');
  const counter = root.querySelector('.orion-ai-counter');
  let busy = false;
  const storageKey = 'orion-ai-chat-view-v1';
  let saveTimer = 0;

  if (!restoreView()) resetView();
  const observer = new MutationObserver(scheduleSave);
  observer.observe(messages, { childList: true, subtree: true, characterData: true });
  observer.observe(counter, { childList: true, subtree: true, characterData: true });
  const toggle = open => {
    root.classList.toggle('is-open', open);
    panel.classList.toggle('is-open', open);
    panel.setAttribute('aria-hidden', String(!open));
    launcher.setAttribute('aria-expanded', String(open));
    if (open) setTimeout(() => input.focus(), 100);
  };

  launcher.addEventListener('click', () => toggle(!panel.classList.contains('is-open')));
  close.addEventListener('click', () => toggle(false));
  clear.addEventListener('click', () => { if (!busy) { sessionStorage.removeItem(storageKey); resetView(); } });
  input.addEventListener('keydown', event => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      form.requestSubmit();
    }
  });

  form.addEventListener('submit', async event => {
    event.preventDefault();
    const text = input.value.trim();
    if (!text || busy) return;
    removePrompts();
    addMessage('user', text);
    input.value = '';
    setBusy(true);
    const thinking = addMessage('assistant', 'Checking Orion Supplies…', false, 'is-thinking');
    try {
      const data = await api('chat', { message: text });
      thinking.remove();
      addMessage('assistant', data.message || data.answer || 'I could not prepare a reply.', true);
      if (Array.isArray(data.questions) && data.questions.length) addQuestions(data.questions);
      if (data.estimate) addEstimate(data.estimate);
      if (Array.isArray(data.sources) && data.sources.length) addSources(data.sources);
      if (Array.isArray(data.products) && data.products.length) addProducts(data.products);
      counter.textContent = data.remaining > 0
        ? `${data.remaining} questions left in this session`
        : 'Session complete — another session may be available today';
    } catch (error) {
      thinking.remove();
      addMessage('assistant', error.message || 'The assistant is temporarily unavailable.');
      if (error.status === 429) {
        counter.textContent = error.retryAfter ? `Please try again in ${error.retryAfter} seconds` : 'Daily chat limit reached';
      }
    } finally {
      setBusy(false);
    }
  });

  function resetView() {
    messages.replaceChildren();
    addMessage('assistant', cfg.greeting);
    addPrompts();
    input.disabled = false;
    counter.textContent = `${Number(cfg.questionsPerSession)} questions available`;
  }

  function addPrompts() {
    if (!Array.isArray(cfg.quickPrompts) || !cfg.quickPrompts.length) return;
    const wrap = document.createElement('div');
    wrap.className = 'orion-ai-prompts';
    cfg.quickPrompts.forEach(label => {
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = label;
      button.addEventListener('click', () => { input.value = label; form.requestSubmit(); });
      wrap.appendChild(button);
    });
    messages.appendChild(wrap);
  }

  function removePrompts() {
    messages.querySelector('.orion-ai-prompts')?.remove();
  }

  function addMessage(role, text, formatted = false, extra = '') {
    const element = document.createElement('div');
    element.className = `orion-ai-message is-${role} ${extra}`;
    if (formatted) renderText(element, text);
    else element.textContent = text;
    messages.appendChild(element);
    scroll();
    return element;
  }

  function renderText(container, text) {
    const lines = String(text ?? '').split(/\n+/).map(line => line.trim()).filter(Boolean);
    let list = null;
    lines.forEach(line => {
      const heading = line.match(/^#{1,4}\s+(.+)/);
      const bullet = line.match(/^[-*•]\s+(.+)/);
      if (bullet) {
        if (!list) {
          list = document.createElement('ul');
          container.appendChild(list);
        }
        const item = document.createElement('li');
        appendInline(item, bullet[1]);
        list.appendChild(item);
        return;
      }
      list = null;
      const element = document.createElement(heading ? 'strong' : 'p');
      appendInline(element, heading ? heading[1] : line);
      if (heading) element.className = 'orion-ai-answer-heading';
      container.appendChild(element);
    });
  }

  function appendInline(element, text) {
    String(text).split(/(\*\*[^*]+\*\*)/g).filter(Boolean).forEach(part => {
      if (part.startsWith('**') && part.endsWith('**')) {
        const strong = document.createElement('strong');
        strong.textContent = part.slice(2, -2);
        element.appendChild(strong);
      } else {
        element.appendChild(document.createTextNode(part));
      }
    });
  }

  function addQuestions(items) {
    const wrap = document.createElement('div');
    wrap.className = 'orion-ai-questions';
    const label = document.createElement('strong');
    label.textContent = 'To continue, please answer:';
    const list = document.createElement('ul');
    items.forEach(question => {
      const item = document.createElement('li');
      item.textContent = question;
      list.appendChild(item);
    });
    wrap.append(label, list);
    messages.appendChild(wrap);
    scroll();
  }

  function addEstimate(estimate) {
    if (!Array.isArray(estimate.items) || !estimate.items.length) return;
    const wrap = document.createElement('section');
    wrap.className = 'orion-ai-estimate';
    const title = document.createElement('strong');
    title.textContent = 'Indicative materials estimate';
    const list = document.createElement('ul');
    estimate.items.forEach(entry => {
      const item = document.createElement('li');
      item.textContent = `${entry.label}: ${entry.amount}${entry.unit ? ` ${entry.unit}` : ''}`;
      list.appendChild(item);
    });
    wrap.append(title, list);
    if (Array.isArray(estimate.assumptions) && estimate.assumptions.length) {
      const note = document.createElement('small');
      note.textContent = estimate.assumptions.join(' ');
      wrap.appendChild(note);
    }
    messages.appendChild(wrap);
    scroll();
  }

  function addSources(items) {
    const valid = items.filter(item => item && item.url);
    if (!valid.length) return;
    const wrap = document.createElement('div');
    wrap.className = 'orion-ai-sources';
    const label = document.createElement('span');
    label.textContent = 'Store sources:';
    wrap.appendChild(label);
    valid.forEach(item => {
      const link = document.createElement('a');
      link.href = item.url;
      link.target = '_blank';
      link.rel = 'noopener';
      link.textContent = item.title || 'Source';
      wrap.appendChild(link);
    });
    messages.appendChild(wrap);
    scroll();
  }

  function addProducts(items) {
    const grid = document.createElement('div');
    grid.className = 'orion-ai-products';
    items.forEach(product => {
      if (!product || !product.id || !product.url) return;
      const card = document.createElement('article');
      card.className = 'orion-ai-product';
      const image = document.createElement('img');
      image.src = product.image || '';
      image.alt = '';
      const content = document.createElement('div');
      const name = document.createElement('strong');
      name.textContent = product.name || 'Product';
      const price = document.createElement('span');
      price.textContent = product.price_text || product.price || '';
      const stock = document.createElement('em');
      stock.textContent = product.in_stock ? 'In stock' : 'Out of stock';
      const actions = document.createElement('div');
      actions.className = 'orion-ai-actions';
      const view = document.createElement('a');
      view.href = product.url;
      view.textContent = product.can_add_to_cart ? 'View product' : 'Choose options';
      actions.appendChild(view);
      if (product.can_add_to_cart) {
        const add = document.createElement('button');
        add.type = 'button';
        add.dataset.productId = String(product.id);
        add.textContent = 'Add to basket';
        add.addEventListener('click', () => addToCart(add, Number(product.id)));
        actions.appendChild(add);
      }
      content.append(name, price, stock, actions);
      const categories = Array.isArray(product.categories)
        ? product.categories.map(category => String(category).trim()).filter(Boolean)
        : [];
      if (categories.length) {
        const category = document.createElement('small');
        category.className = 'orion-ai-product-category';
        category.textContent = `Category: ${categories.join(' · ')}`;
        content.appendChild(category);
      }
      card.append(image, content);
      grid.appendChild(card);
    });
    if (grid.children.length) {
      messages.appendChild(grid);
      scroll();
    }
  }

  async function addToCart(button, productId) {
    button.disabled = true;
    const previous = button.textContent;
    button.textContent = 'Adding…';
    try {
      const data = await api('cart', { product_id: productId, quantity: 1 });
      button.textContent = 'Added ✓';
      button.setAttribute('aria-label', `${data.count || 0} items in basket`);
    } catch (error) {
      button.textContent = error.message || previous;
      button.disabled = false;
    }
  }

  function scheduleSave() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
      try {
        sessionStorage.setItem(storageKey, JSON.stringify({
          html: messages.innerHTML,
          counter: counter.textContent,
          savedAt: Date.now(),
        }));
      } catch (_) {}
    }, 50);
  }

  function restoreView() {
    try {
      const state = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
      if (!state || !state.html || Date.now() - Number(state.savedAt || 0) > 30 * 60 * 1000) {
        sessionStorage.removeItem(storageKey);
        return false;
      }
      messages.innerHTML = state.html;
      counter.textContent = state.counter || `${Number(cfg.questionsPerSession)} questions available`;
      messages.querySelectorAll('button[data-product-id]').forEach(button => {
        button.addEventListener('click', () => addToCart(button, Number(button.dataset.productId)));
      });
      scroll();
      return true;
    } catch (_) {
      sessionStorage.removeItem(storageKey);
      return false;
    }
  }

  function setBusy(value) {
    busy = value;
    form.querySelector('button').disabled = value;
    input.disabled = value;
  }

  function scroll() {
    messages.scrollTop = messages.scrollHeight;
  }

  async function api(path, body) {
    const response = await fetch(cfg.rest + path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
      body: JSON.stringify(body),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      let message = typeof data.message === 'string' ? data.message : 'Request failed.';
      if (/<\/?[a-z][\s\S]*>/i.test(message)) message = 'The assistant encountered a server error. Please try again.';
      const error = new Error(message);
      error.status = response.status;
      error.retryAfter = Number(data?.data?.retry_after || 0);
      throw error;
    }
    return data;
  }

  function esc(value) {
    return String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
  }
})();
