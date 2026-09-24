/**
 * Show / hide password on login and register forms
 */
document.addEventListener('DOMContentLoaded', function () {
  var toggled = {};

  function getControls(inputId) {
    if (!toggled[inputId]) {
      var input = document.getElementById(inputId);
      var wrap = input ? input.closest('.form-group') : null;
      toggled[inputId] = {
        input: input,
        btn: wrap ? wrap.querySelector('.password-toggle-btn[data-toggle-password="' + inputId + '"]') : null,
        checkbox: wrap ? wrap.querySelector('input[type="checkbox"][data-toggle-password="' + inputId + '"]') : null
      };
    }
    return toggled[inputId];
  }

  function setVisible(inputId, show) {
    var c = getControls(inputId);
    if (!c.input) return;

    c.input.type = show ? 'text' : 'password';

    if (c.btn) {
      var icon = c.btn.querySelector('i');
      if (icon) icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
      c.btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      c.btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    }
    if (c.checkbox) {
      c.checkbox.checked = show;
    }
  }

  document.querySelectorAll('[data-toggle-password]').forEach(function (control) {
    var inputId = control.getAttribute('data-toggle-password');

    if (control.type === 'checkbox') {
      control.addEventListener('change', function () {
        setVisible(inputId, this.checked);
      });
    } else {
      control.addEventListener('click', function () {
        var c = getControls(inputId);
        var show = c.input && c.input.type === 'password';
        setVisible(inputId, show);
      });
    }
  });
});
