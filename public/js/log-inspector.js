// Explorin Log Inspector Interactive Modal & Clipboard Helper
document.addEventListener('DOMContentLoaded', function () {
    // 1. Copy to clipboard helper
    document.addEventListener('click', function (e) {
        var copyBtn = e.target.closest('[data-copy-target]');
        if (copyBtn) {
            var targetId = copyBtn.getAttribute('data-copy-target');
            var targetEl = document.getElementById(targetId);
            if (targetEl) {
                var text = targetEl.innerText || targetEl.textContent;
                navigator.clipboard.writeText(text).then(function () {
                    var originalText = copyBtn.innerText;
                    copyBtn.innerText = 'Copied!';
                    setTimeout(function () {
                        copyBtn.innerText = originalText;
                    }, 2000);
                });
            }
        }
    });

    // 2. Log Entry Detail Modal via AJAX
    var detailModalEl = document.getElementById('logDetailModal');
    if (detailModalEl && typeof bootstrap !== 'undefined') {
        var logModal = new bootstrap.Modal(detailModalEl);

        document.addEventListener('click', function (e) {
            var triggerBtn = e.target.closest('.js-view-log');
            if (!triggerBtn) return;

            e.preventDefault();
            var detailUrl = triggerBtn.getAttribute('data-detail-url');
            if (!detailUrl) return;

            // Show Loading state inside modal
            document.getElementById('modalLoading').style.display = 'block';
            document.getElementById('modalContent').style.display = 'none';
            logModal.show();

            fetch(detailUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (data) {
                // Populate Modal Elements
                var badgeEl = document.getElementById('modalLevelBadge');
                badgeEl.innerText = data.level;
                badgeEl.className = 'li-level-badge ' + data.badge_class;

                document.getElementById('modalTimestamp').innerText = data.timestamp;
                document.getElementById('modalChannel').innerText = data.channel;
                document.getElementById('modalEnvironment').innerText = data.environment;
                document.getElementById('modalOffsets').innerText = 'Offset: ' + data.byte_offset + ' | Byte: ' + data.byte_length;
                document.getElementById('modalMessage').innerText = data.message;
                document.getElementById('modalRawContent').innerText = data.raw;

                // Handle Exception Block
                var exSection = document.getElementById('modalExceptionSection');
                if (data.has_exception) {
                    exSection.style.display = 'block';
                    document.getElementById('modalExClass').innerText = data.exception_class;
                    document.getElementById('modalExLocation').innerText = data.exception_location;
                    document.getElementById('modalExHash').innerText = '#' + data.exception_hash;

                    var traceSection = document.getElementById('modalTraceSection');
                    if (data.stack_trace && data.stack_trace.length > 0) {
                        traceSection.style.display = 'block';
                        document.getElementById('modalTraceCount').innerText = data.stack_trace.length;
                        document.getElementById('modalStackTrace').innerText = data.stack_trace.join('\n');
                    } else {
                        traceSection.style.display = 'none';
                    }
                } else {
                    exSection.style.display = 'none';
                    document.getElementById('modalTraceSection').style.display = 'none';
                }

                // Handle Context JSON
                var ctxSection = document.getElementById('modalContextSection');
                if (data.context && Object.keys(data.context).length > 0) {
                    ctxSection.style.display = 'block';
                    document.getElementById('modalContext').innerText = JSON.stringify(data.context, null, 2);
                } else {
                    ctxSection.style.display = 'none';
                }

                // Handle Extra JSON
                var extraSection = document.getElementById('modalExtraSection');
                if (data.extra && Object.keys(data.extra).length > 0) {
                    extraSection.style.display = 'block';
                    document.getElementById('modalExtra').innerText = JSON.stringify(data.extra, null, 2);
                } else {
                    extraSection.style.display = 'none';
                }

                // Switch loading to content
                document.getElementById('modalLoading').style.display = 'none';
                document.getElementById('modalContent').style.display = 'block';
            })
            .catch(function (err) {
                document.getElementById('modalLoading').innerHTML = '<div class="alert alert-danger mb-0">Gagal memuat detail log: ' + err.message + '</div>';
            });
        });
    }
});
