<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/config.php';

requireRole('ADMIN');

$user = getCurrentUser();
$title = 'Audit Log';

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Audit Log</h2>
    <span class="badge bg-danger fs-6">ADMIN</span>
  </div>

<div class="card">
  <div class="card-header">
    <h5 class="mb-0">Recent Activity</h5>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-hover">
        <thead class="table-dark">
          <tr>
            <th style="background-color:#212529!important;color:#ffffff!important;border-color:#495057!important;">Timestamp</th>
            <th style="background-color:#212529!important;color:#ffffff!important;border-color:#495057!important;">Action</th>
            <th style="background-color:#212529!important;color:#ffffff!important;border-color:#495057!important;">Actor</th>
            <th style="background-color:#212529!important;color:#ffffff!important;border-color:#495057!important;">Details</th>
          </tr>
        </thead>
        <tbody id="auditTableBody">
          <tr>
            <td colspan="4" class="text-center">
              <div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-3">
      <button class="btn btn-outline-secondary btn-sm" id="prevBtn" disabled>Prev</button>
      <div><span id="pageInfo">Page 1</span></div>
      <button class="btn btn-outline-secondary btn-sm" id="nextBtn" disabled>Next</button>
    </div>
  </div>
</div>

<script>
let currentPage = 1;
let totalPages = 1;
const limit = 20;

document.addEventListener('DOMContentLoaded', function() {
  loadAudit(currentPage);
  document.getElementById('prevBtn').addEventListener('click', () => {
    if (currentPage > 1) { loadAudit(currentPage - 1); }
  });
  document.getElementById('nextBtn').addEventListener('click', () => {
    if (currentPage < totalPages) { loadAudit(currentPage + 1); }
  });
});

function maskDetails(details) {
  if (!details) return '';
  // Redact emails and IDs
  let masked = details.replace(/[\w.+-]+@\w+\.[\w.-]+/g, '[redacted-email]');
  masked = masked.replace(/\b(id|user|patient)_?id\s*:\s*\d+/gi, '[redacted-id]');
  // Remove pagination noise like "Page: 1, Limit: 10"
  masked = masked.replace(/\bPage:\s*\d+\s*,\s*Limit:\s*\d+\b/gi, '').trim();
  return masked;
}

function loadAudit(page) {
  const body = document.getElementById('auditTableBody');
  body.innerHTML = `<tr><td colspan="4" class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></td></tr>`;

  fetch(`<?= route('admin/audit') ?>&page=${page}&limit=${limit}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
    .then(r => r.json())
    .then(data => {
      const entries = data.audit_entries || [];
      const pagination = data.pagination || { current_page: page, total_pages: 1 };
      currentPage = pagination.current_page;
      totalPages = pagination.total_pages;

      document.getElementById('pageInfo').textContent = `Page ${currentPage} of ${totalPages}`;
      document.getElementById('prevBtn').disabled = currentPage <= 1;
      document.getElementById('nextBtn').disabled = currentPage >= totalPages;

      if (entries.length === 0) {
        body.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No entries</td></tr>';
        return;
      }

      body.innerHTML = entries.map(e => {
        const detailText = e.action === 'ADMIN_AUDIT_VIEW' ? '' : maskDetails(e.details || '');
        return `
        <tr>
          <td>${new Date(e.ts).toLocaleString()}</td>
          <td>${e.action}</td>
          <td>${e.actor_name || 'System'}</td>
          <td><span class="text-muted">${detailText}</span></td>
        </tr>`;
      }).join('');
    })
    .catch(() => {
      body.innerHTML = '<tr><td colspan="4" class="text-danger text-center">Failed to load audit log</td></tr>';
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>


