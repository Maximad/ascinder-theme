(() => {
  const rail = document.getElementById('experience-rail');
  if (!rail || typeof ascinderData === 'undefined') {
    return;
  }

  const state = {
    panels: [],
    currentIndex: 0,
    reducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    orientation: window.innerWidth <= 860 ? 'vertical' : 'horizontal',
    anchors: [],
    isSettling: false,
    settleLockUntil: 0,
    scrollEndTimer: null,
    scrollRaf: 0,
    lastScrollPosition: 0,
    lastScrollTs: performance.now(),
    lastVelocity: 0,
    rtlModel: 'default',
    isRTL: Boolean(ascinderData.isRTL),
  };

  const SETTLE_DEBOUNCE_MS = 140;
  const SETTLE_VELOCITY_THRESHOLD = 0.02;
  const SETTLE_LOCK_MS = 220;

  const clamp = (num, min, max) => Math.min(Math.max(num, min), max);

  function detectRtlScrollModel() {
    if (!state.isRTL || state.orientation !== 'horizontal') {
      return 'default';
    }

    const probe = document.createElement('div');
    const child = document.createElement('div');
    probe.style.width = '4px';
    probe.style.height = '1px';
    probe.style.overflow = 'auto';
    probe.style.position = 'absolute';
    probe.style.top = '-9999px';
    probe.style.direction = 'rtl';
    child.style.width = '8px';
    child.style.height = '1px';
    probe.appendChild(child);
    document.body.appendChild(probe);

    probe.scrollLeft = 0;
    const atZero = probe.scrollLeft;
    probe.scrollLeft = 1;
    const afterSetOne = probe.scrollLeft;

    document.body.removeChild(probe);

    if (atZero > 0) return 'reverse';
    if (afterSetOne === 0) return 'negative';
    return 'default';
  }

  function getLogicalScrollPosition() {
    if (state.orientation !== 'horizontal' || !state.isRTL) {
      return state.orientation === 'horizontal' ? rail.scrollLeft : rail.scrollTop;
    }

    const max = Math.max(0, rail.scrollWidth - rail.clientWidth);
    if (state.rtlModel === 'negative') {
      return -rail.scrollLeft;
    }
    if (state.rtlModel === 'reverse') {
      return max - rail.scrollLeft;
    }
    return rail.scrollLeft;
  }

  function setLogicalScrollPosition(position, behavior) {
    const targetPos = Math.max(0, position);

    if (state.orientation !== 'horizontal') {
      rail.scrollTo({ top: targetPos, behavior });
      return;
    }

    if (!state.isRTL) {
      rail.scrollTo({ left: targetPos, behavior });
      return;
    }

    const max = Math.max(0, rail.scrollWidth - rail.clientWidth);
    let raw = targetPos;

    if (state.rtlModel === 'negative') {
      raw = -targetPos;
    } else if (state.rtlModel === 'reverse') {
      raw = max - targetPos;
    }

    rail.scrollTo({ left: raw, behavior });
  }

  const setOrientation = (orientation) => {
    state.orientation = orientation;
    rail.dataset.orientation = orientation;
    rail.setAttribute('aria-orientation', orientation);
    state.rtlModel = detectRtlScrollModel();
  };

  const normalizePanelsPayload = (payload) => {
    if (!payload || typeof payload !== 'object') {
      return [];
    }

    if (typeof payload.lang === 'string' && payload.lang) {
      ascinderData.lang = payload.lang;
    }

    if (typeof payload.rtl === 'boolean') {
      state.isRTL = payload.rtl;
    }

    return Array.isArray(payload.panels) ? payload.panels : [];
  };

  const readFetchCacheMode = () => {
    return ascinderData.cacheMode === 'no-store' ? 'no-store' : 'default';
  };

  const getBuiltInFallbackPanels = () => ([
    {
      id: 'fallback',
      kicker: 'ASCINDER',
      title: ascinderData.lang === 'ar' ? 'مرحبا بكم في أسيندر' : 'Welcome to ASCINDER',
      text: ascinderData.lang === 'ar' ? 'تعذر تحميل اللوحات حالياً.' : 'Panel data could not be loaded.',
      media: { type: 'image', url: '' },
      cta: { label: ascinderData.lang === 'ar' ? 'تواصل معنا' : 'Contact us', href: ascinderData.contactUrl || ascinderData.homeUrl },
    },
  ]);

  const fetchCptPanels = async (lang) => {
    if (!ascinderData.panelsEndpointUrl) {
      return [];
    }

    const endpointUrl = new URL(ascinderData.panelsEndpointUrl, window.location.origin);
    endpointUrl.searchParams.set('lang', lang || ascinderData.lang || 'en');
    endpointUrl.searchParams.set('context', 'front');

    const response = await fetch(endpointUrl.toString(), { cache: readFetchCacheMode() });
    if (!response.ok) {
      throw new Error('Endpoint response not ok');
    }

    return normalizePanelsPayload(await response.json());
  };

  const fetchJsonPanels = async () => {
    const jsonUrl = ascinderData.panelsJsonUrl || ascinderData.panelsUrl;
    if (!jsonUrl) {
      return [];
    }

    const response = await fetch(jsonUrl, { cache: readFetchCacheMode() });
    if (!response.ok) {
      throw new Error('JSON response not ok');
    }

    const payload = await response.json();
    return Array.isArray(payload) ? payload : normalizePanelsPayload(payload);
  };

  const fetchPanels = async () => {
    const sourceMode = ascinderData.sourceMode || 'cpt';
    const allowLangFallback = ascinderData.allowLangFallback !== false;
    const defaultLang = ascinderData.defaultLang || 'en';

    if (sourceMode === 'json-only') {
      try {
        const jsonPanels = await fetchJsonPanels();
        return jsonPanels.length ? jsonPanels : getBuiltInFallbackPanels();
      } catch (error) {
        return getBuiltInFallbackPanels();
      }
    }

    const tryCptChain = async () => {
      try {
        const primaryPanels = await fetchCptPanels(ascinderData.lang || 'en');
        if (primaryPanels.length > 0) {
          return primaryPanels;
        }
      } catch (error) {
        // continue with chain.
      }

      if (allowLangFallback && defaultLang && defaultLang !== ascinderData.lang) {
        try {
          const fallbackPanels = await fetchCptPanels(defaultLang);
          if (fallbackPanels.length > 0) {
            return fallbackPanels;
          }
        } catch (error) {
          // continue with chain.
        }
      }

      return [];
    };

    const cptPanels = await tryCptChain();
    if (cptPanels.length) {
      return cptPanels;
    }

    if (sourceMode === 'cpt-with-json-fallback') {
      try {
        const jsonPanels = await fetchJsonPanels();
        if (jsonPanels.length > 0) {
          return jsonPanels;
        }
      } catch (error) {
        // continue to built-in fallback
      }
    }

    return getBuiltInFallbackPanels();
  };

  const buildMedia = (panel) => {
    const mediaWrap = document.createElement('div');
    mediaWrap.className = 'experience-media';

    if (panel.media && panel.media.url) {
      if (panel.media.type === 'video') {
        const video = document.createElement('video');
        video.src = panel.media.url;
        video.autoplay = true;
        video.muted = true;
        video.loop = true;
        video.playsInline = true;
        video.preload = 'metadata';
        mediaWrap.appendChild(video);
      } else {
        const img = document.createElement('img');
        img.src = panel.media.url;
        img.alt = '';
        img.loading = 'lazy';
        mediaWrap.appendChild(img);
      }
    }

    return mediaWrap;
  };

  const renderPanels = (panels) => {
    const fragment = document.createDocumentFragment();

    panels.forEach((panel, index) => {
      const article = document.createElement('article');
      article.className = 'experience-panel';
      article.id = panel.id || `panel-${index + 1}`;
      article.dataset.index = String(index);
      article.tabIndex = -1;

      const overlay = document.createElement('div');
      overlay.className = 'experience-overlay';

      const content = document.createElement('div');
      content.className = 'experience-content';

      const card = document.createElement('div');
      card.className = 'experience-card';

      if (panel.kicker) {
        const kicker = document.createElement('p');
        kicker.className = 'experience-kicker';
        kicker.textContent = panel.kicker;
        card.appendChild(kicker);
      }

      const title = document.createElement('h2');
      title.className = 'experience-title';
      title.textContent = panel.title || '';
      card.appendChild(title);

      if (panel.text) {
        const text = document.createElement('p');
        text.className = 'experience-text';
        text.textContent = panel.text;
        card.appendChild(text);
      }

      if (panel.cta && panel.cta.href && panel.cta.label) {
        const cta = document.createElement('a');
        cta.className = 'experience-panel__cta';
        cta.href = panel.cta.href;
        cta.textContent = panel.cta.label;
        card.appendChild(cta);
      }

      content.appendChild(card);
      article.appendChild(buildMedia(panel));
      article.appendChild(overlay);
      article.appendChild(content);
      fragment.appendChild(article);
    });

    rail.innerHTML = '';
    rail.appendChild(fragment);
  };

  const setActive = (idx) => {
    state.currentIndex = clamp(idx, 0, Math.max(0, state.panels.length - 1));
    const children = rail.querySelectorAll('.experience-panel');

    children.forEach((item, i) => {
      item.classList.toggle('is-active', i === state.currentIndex);
    });
  };

  const computeAnchors = () => {
    const panels = Array.from(rail.querySelectorAll('.experience-panel'));
    const logicalStart = getLogicalScrollPosition();
    const railRect = rail.getBoundingClientRect();

    state.anchors = panels.map((panel, index) => {
      const rect = panel.getBoundingClientRect();
      if (state.orientation === 'horizontal') {
        const start = logicalStart + (rect.left - railRect.left);
        return {
          index,
          start,
          center: start + rect.width / 2,
        };
      }

      const start = rail.scrollTop + (rect.top - railRect.top);
      return {
        index,
        start,
        center: start + rect.height / 2,
      };
    });
  };

  const findNearestAnchorIndex = () => {
    if (!state.anchors.length) return 0;

    const logicalPos = getLogicalScrollPosition();
    const viewportCenter = logicalPos + (state.orientation === 'horizontal' ? rail.clientWidth : rail.clientHeight) / 2;

    let nearest = state.anchors[0];
    let bestDistance = Math.abs(nearest.center - viewportCenter);

    for (let i = 1; i < state.anchors.length; i += 1) {
      const candidate = state.anchors[i];
      const distance = Math.abs(candidate.center - viewportCenter);
      if (distance < bestDistance) {
        nearest = candidate;
        bestDistance = distance;
      }
    }

    return nearest.index;
  };

  const goToPanel = (idx, options = {}) => {
    const targetIndex = clamp(idx, 0, Math.max(0, state.anchors.length - 1));
    const anchor = state.anchors[targetIndex];
    if (!anchor) return;

    setActive(targetIndex);

    const currentPos = getLogicalScrollPosition();
    const delta = Math.abs(currentPos - anchor.start);
    if (!options.force && delta < 1) {
      return;
    }

    const behavior = options.instant || state.reducedMotion ? 'auto' : 'smooth';
    state.isSettling = true;
    state.settleLockUntil = performance.now() + SETTLE_LOCK_MS;
    setLogicalScrollPosition(anchor.start, behavior);

    window.setTimeout(() => {
      state.isSettling = false;
    }, SETTLE_LOCK_MS);
  };

  const settleToNearest = () => {
    if (state.isSettling || performance.now() < state.settleLockUntil) {
      return;
    }

    if (Math.abs(state.lastVelocity) > SETTLE_VELOCITY_THRESHOLD) {
      state.scrollEndTimer = window.setTimeout(settleToNearest, SETTLE_DEBOUNCE_MS);
      return;
    }

    const nearestIndex = findNearestAnchorIndex();
    goToPanel(nearestIndex);
  };

  const onScroll = () => {
    if (state.scrollRaf) return;

    state.scrollRaf = window.requestAnimationFrame(() => {
      state.scrollRaf = 0;

      const now = performance.now();
      const pos = getLogicalScrollPosition();
      const dt = Math.max(1, now - state.lastScrollTs);
      state.lastVelocity = (pos - state.lastScrollPosition) / dt;
      state.lastScrollPosition = pos;
      state.lastScrollTs = now;

      setActive(findNearestAnchorIndex());

      if (state.scrollEndTimer) {
        clearTimeout(state.scrollEndTimer);
      }
      state.scrollEndTimer = window.setTimeout(settleToNearest, SETTLE_DEBOUNCE_MS);
    });
  };

  const onControl = (action) => {
    if (action === 'prev') goToPanel(state.currentIndex - 1);
    if (action === 'next') goToPanel(state.currentIndex + 1);
    if (action === 'toggle') {
      const preservedIndex = state.currentIndex;
      setOrientation(state.orientation === 'horizontal' ? 'vertical' : 'horizontal');
      computeAnchors();
      goToPanel(preservedIndex, { force: true });
    }
  };

  const bindEvents = () => {
    document.querySelectorAll('.hud-btn').forEach((button) => {
      const action = button.dataset.action;
      if (!button.getAttribute('aria-label') && ascinderData.i18n && ascinderData.i18n[action]) {
        button.setAttribute('aria-label', ascinderData.i18n[action]);
      }
      button.addEventListener('click', () => onControl(action));
    });

    rail.addEventListener('scroll', onScroll, { passive: true });

    rail.addEventListener('keydown', (event) => {
      const isHorizontal = state.orientation === 'horizontal';
      const prevKey = isHorizontal ? 'ArrowLeft' : 'ArrowUp';
      const nextKey = isHorizontal ? 'ArrowRight' : 'ArrowDown';

      if (event.key === prevKey) {
        event.preventDefault();
        goToPanel(state.currentIndex - 1);
      }
      if (event.key === nextKey) {
        event.preventDefault();
        goToPanel(state.currentIndex + 1);
      }
      if (event.key === 'Enter' || event.key === ' ') {
        const active = rail.querySelector('.experience-panel.is-active .experience-panel__cta');
        if (active) {
          event.preventDefault();
          active.click();
        }
      }
    });

    let resizeTimer = null;
    window.addEventListener('resize', () => {
      if (resizeTimer) {
        clearTimeout(resizeTimer);
      }
      resizeTimer = window.setTimeout(() => {
        window.requestAnimationFrame(() => {
          computeAnchors();
          goToPanel(state.currentIndex, { force: true, instant: true });
        });
      }, 120);
    });
  };

  const loadPanels = async () => {
    const payload = await fetchPanels();
    state.panels = Array.isArray(payload) && payload.length ? payload : getBuiltInFallbackPanels();
  };

  const init = async () => {
    setOrientation(state.orientation);
    await loadPanels();
    renderPanels(state.panels);
    bindEvents();
    computeAnchors();
    setActive(0);
    goToPanel(0, { force: true, instant: true });
  };

  init();
})();
