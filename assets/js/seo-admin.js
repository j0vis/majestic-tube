/* Theme-native SEO forms: progressive enhancement only. */
(function () {
  'use strict';
  let focused = null;
  document.querySelectorAll('.mt-seo-template').forEach(function (field) {
    field.addEventListener('focus', function () { focused = field; });
  });
  document.querySelectorAll('.mt-seo-token').forEach(function (button) {
    button.addEventListener('click', function () {
      const field = focused || document.querySelector('.mt-seo-template');
      if (!field) return;
      field.setRangeText(button.dataset.token, field.selectionStart, field.selectionEnd, 'end');
      field.focus();
      field.dispatchEvent(new Event('input', { bubbles: true }));
    });
  });
  const preview = document.querySelector('.mt-seo-live-preview');
  if (preview) {
    const title = document.getElementById('majestic_tube_seo_title');
    const description = document.getElementById('majestic_tube_seo_description');
    const paint = function () {
      preview.querySelector('.mt-seo-live-title').textContent = title.value || title.placeholder;
      preview.querySelector('.mt-seo-live-description').textContent = description.value || description.placeholder;
    };
    title.addEventListener('input', paint);
    description.addEventListener('input', paint);
    paint();
  }
  document.querySelectorAll('.mt-seo-counter, .mt-seo-template').forEach(function (field) {
    const counter = document.createElement('p');
    counter.className = 'description';
    counter.setAttribute('aria-live', 'polite');
    field.insertAdjacentElement('afterend', counter);
    const update = function () { counter.textContent = Array.from(field.value).length + ' characters'; };
    field.addEventListener('input', update);
    update();
  });
})();
