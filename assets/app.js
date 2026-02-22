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
  };

  const clamp = (num, min, max) => Math.min(Math.max(num, min), max);

  const setOrientation = (orientation) => {
    state.orientation = orientation;
    rail.dataset.orientation = orientation;
    rail.setAttribute('aria-orientation', orientation);
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
    state.currentIndex = clamp(idx, 0, state.panels.length - 1);
    const children = rail.querySelectorAll('.experience-panel');

    children.forEach((item, i) => {
      item.classList.toggle('is-active', i === state.currentIndex);
    });
  };

  const scrollToIndex = (idx) => {
    const children = rail.querySelectorAll('.experience-panel');
    const target = children[clamp(idx, 0, children.length - 1)];
    if (!target) {
      return;
    }

    target.scrollIntoView({
      behavior: state.reducedMotion ? 'auto' : 'smooth',
      block: state.orientation === 'vertical' ? 'start' : 'nearest',
      inline: state.orientation === 'horizontal' ? 'start' : 'nearest',
    });
  };

  const observePanels = () => {
    const options = {
      root: rail,
      threshold: [0.45, 0.6, 0.75],
    };

    const observer = new IntersectionObserver((entries) => {
      let best = null;

      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        if (!best || entry.intersectionRatio > best.intersectionRatio) {
          best = entry;
        }
      });

      if (best) {
        const idx = Number(best.target.dataset.index || 0);
        setActive(idx);
      }
    }, options);

    rail.querySelectorAll('.experience-panel').forEach((panel) => observer.observe(panel));
  };

  const onControl = (action) => {
    if (action === 'prev') scrollToIndex(state.currentIndex - 1);
    if (action === 'next') scrollToIndex(state.currentIndex + 1);
    if (action === 'toggle') {
      setOrientation(state.orientation === 'horizontal' ? 'vertical' : 'horizontal');
      scrollToIndex(state.currentIndex);
    }
  };

  const bindEvents = () => {
    document.querySelectorAll('.hud-btn').forEach((button) => {
      button.addEventListener('click', () => onControl(button.dataset.action));
    });

    rail.addEventListener('keydown', (event) => {
      const isHorizontal = state.orientation === 'horizontal';
      const prevKey = isHorizontal ? 'ArrowLeft' : 'ArrowUp';
      const nextKey = isHorizontal ? 'ArrowRight' : 'ArrowDown';

      if (event.key === prevKey) {
        event.preventDefault();
        onControl('prev');
      }
      if (event.key === nextKey) {
        event.preventDefault();
        onControl('next');
      }
      if (event.key === 'Enter' || event.key === ' ') {
        const active = rail.querySelector('.experience-panel.is-active .experience-panel__cta');
        if (active) {
          event.preventDefault();
          active.click();
        }
      }
    });
  };

  const loadPanels = async () => {
    try {
      const response = await fetch(ascinderData.panelsUrl, { cache: 'no-store' });
      if (!response.ok) throw new Error('Panel response not ok');
      const payload = await response.json();
      if (!Array.isArray(payload)) throw new Error('Panel payload must be array');
      state.panels = payload;
    } catch (error) {
      state.panels = [
        {
          id: 'fallback',
          kicker: 'ASCINDER',
          title: ascinderData.lang === 'ar' ? 'مرحبا بكم في أسيندر' : 'Welcome to ASCINDER',
          text: ascinderData.lang === 'ar' ? 'تعذر تحميل اللوحات حالياً.' : 'Panel data could not be loaded.',
          media: { type: 'image', url: '' },
          cta: { label: ascinderData.lang === 'ar' ? 'تواصل معنا' : 'Contact us', href: ascinderData.contactUrl || ascinderData.homeUrl },
        },
      ];
    }
  };

  const init = async () => {
    setOrientation(state.orientation);
    await loadPanels();
    renderPanels(state.panels);
    bindEvents();
    observePanels();
    setActive(0);
  };

  init();
})();
