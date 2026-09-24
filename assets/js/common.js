/**
 * Common UI interactions - FMS 2.0
 */
document.addEventListener('DOMContentLoaded', function () {
  const toggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
    });
  }

  document.querySelectorAll('.flash').forEach(function (el) {
    setTimeout(function () {
      el.style.opacity = '0';
      el.style.transition = 'opacity 0.5s';
      setTimeout(function () { el.remove(); }, 500);
    }, 5000);
  });

  document.querySelectorAll('[data-preview]').forEach(function (input) {
    input.addEventListener('change', function () {
      const previewId = this.getAttribute('data-preview');
      const preview = document.getElementById(previewId);
      if (!preview || !this.files[0]) return;
      const reader = new FileReader();
      reader.onload = function (e) {
        preview.src = e.target.result;
        preview.style.display = 'block';
      };
      reader.readAsDataURL(this.files[0]);
    });
  });

  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (typeof Swal !== 'undefined') {
        e.preventDefault();
        const form = this.closest('form');
        Swal.fire({
          title: 'Confirm',
          text: this.getAttribute('data-confirm'),
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#2d6a4f',
        }).then((r) => { if (r.isConfirmed && form) form.submit(); });
      } else if (!confirm(this.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    });
  });

  if (typeof $ !== 'undefined' && $.fn.DataTable) {
    $('.datatable').DataTable({
      pageLength: 10,
      responsive: true,
      order: [],
      language: { search: 'Search:', lengthMenu: 'Show _MENU_ entries' },
    });
  }

  document.querySelectorAll('form[method="POST"]').forEach(function (form) {
    if (!form.querySelector('input[name="_csrf"]') && document.querySelector('meta[name="csrf-token"]')) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = '_csrf';
      input.value = document.querySelector('meta[name="csrf-token"]').content;
      form.appendChild(input);
    }
  });
});
