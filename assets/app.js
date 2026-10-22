// Small progressive enhancements. The site works without JavaScript.
document.addEventListener('submit', function (event) {
  var form = event.target;
  var message = form.getAttribute('data-confirm');
  if (message && !window.confirm(message)) {
    event.preventDefault();
    return;
  }
  // Stop double submits (for example, borrowing the same book twice).
  var buttons = form.querySelectorAll('button[type="submit"]');
  window.setTimeout(function () {
    buttons.forEach(function (b) { b.disabled = true; });
  }, 0);
});

// Re-enable buttons if the browser restores the page from its back/forward cache.
window.addEventListener('pageshow', function (event) {
  if (event.persisted) {
    document.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = false; });
  }
});
