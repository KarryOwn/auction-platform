window.toast = function(message, type = 'info', duration = 4000) {
  window.dispatchEvent(new CustomEvent('toast', {
    detail: { message, type, duration }
  }));
};

window.toast.success = (msg, dur) => window.toast(msg, 'success', dur);
window.toast.error   = (msg, dur) => window.toast(msg, 'error', dur);
window.toast.info    = (msg, dur) => window.toast(msg, 'info', dur);
window.toast.warning = (msg, dur) => window.toast(msg, 'warning', dur);