(() => {
  const root = document.querySelector('[data-asc-panel-media-root]');
  if (!root || typeof wp === 'undefined' || !wp.media) {
    return;
  }

  const input = root.querySelector('[data-asc-panel-media-input]');
  const typeField = root.querySelector('[data-asc-panel-media-type]');
  const preview = root.querySelector('[data-asc-panel-media-preview]');
  const selectBtn = root.querySelector('[data-asc-panel-media-select]');
  const clearBtn = root.querySelector('[data-asc-panel-media-clear]');

  if (!input || !typeField || !preview || !selectBtn || !clearBtn) {
    return;
  }

  let frame;
  const labels = window.ascPanelAdmin || {};

  const renderPreview = (attachment) => {
    if (!attachment) {
      preview.innerHTML = '<em>No media selected.</em>';
      return;
    }

    if ((attachment.type === 'image' || attachment.mime?.startsWith('image/')) && attachment.sizes) {
      const thumb = attachment.sizes.thumbnail || attachment.sizes.medium || attachment.sizes.full;
      if (thumb && thumb.url) {
        preview.innerHTML = `<img src="${thumb.url}" alt="" style="max-width: 180px; height: auto; display: block;" />`;
        return;
      }
    }

    const fileName = attachment.filename || attachment.title || 'Selected media';
    preview.textContent = fileName;
  };

  const openFrame = () => {
    if (!frame) {
      frame = wp.media({
        title: labels.selectMedia || 'Select panel media',
        button: { text: labels.useMedia || 'Use this media' },
        multiple: false,
        library: {
          type: ['image', 'video'],
        },
      });

      frame.on('select', () => {
        const attachment = frame.state().get('selection').first();
        if (!attachment) {
          return;
        }

        const data = attachment.toJSON();
        input.value = String(data.id || '');
        typeField.value = data.type === 'video' || (data.mime && data.mime.startsWith('video/')) ? 'video' : 'image';
        renderPreview(data);
      });
    }

    frame.open();
  };

  selectBtn.addEventListener('click', (event) => {
    event.preventDefault();
    openFrame();
  });

  clearBtn.addEventListener('click', (event) => {
    event.preventDefault();
    input.value = '';
    preview.innerHTML = '<em>No media selected.</em>';
  });
})();
