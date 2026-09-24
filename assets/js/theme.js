/**
 * DARK / LIGHT THEME TOGGLE
 * Saves preference in localStorage key: fms_theme
 * Applies data-theme attribute on <html> element
 */
(function () {
  const STORAGE_KEY = 'fms_theme';

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    const icon = document.getElementById('themeIcon');
    if (icon) {
      icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
  }

  function getStoredTheme() {
    return localStorage.getItem(STORAGE_KEY) ||
      (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  }

  function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const next = current === 'light' ? 'dark' : 'light';
    localStorage.setItem(STORAGE_KEY, next);
    applyTheme(next);
  }

  // Apply on load (before paint flicker)
  applyTheme(getStoredTheme());

  document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('themeToggle');
    const authBtn = document.getElementById('authThemeToggle');
    if (btn) btn.addEventListener('click', toggleTheme);
    if (authBtn) authBtn.addEventListener('click', toggleTheme);
  });
})();
