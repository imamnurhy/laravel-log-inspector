// Explorin Log Inspector Interactive Modal, Clipboard & Filter Helpers
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

    // 2. Realtime Sidebar Log Files Search Filter
    var fileSearchInput = document.getElementById('logFileSearchInput');
    if (fileSearchInput) {
        fileSearchInput.addEventListener('input', function () {
            var filter = this.value.toLowerCase().trim();
            var fileItems = document.querySelectorAll('.li-file-list .li-file-item');
            var matchCount = 0;

            fileItems.forEach(function (item) {
                var fileName = item.getAttribute('data-filename') || item.innerText.toLowerCase();
                if (fileName.toLowerCase().indexOf(filter) > -1) {
                    item.style.display = 'flex';
                    matchCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            var noMatchesEl = document.getElementById('liNoFileMatches');
            if (noMatchesEl) {
                noMatchesEl.style.display = matchCount === 0 ? 'block' : 'none';
            }
        });
    }

    // 3. Log Entry Detail Modal via AJAX
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

    // 4. Live Tail Polling (Short Polling every 4s for active log file)
    var liveTailToggle = document.getElementById('liveTailToggle');
    var logTableBody = document.getElementById('logTableBody');
    if (liveTailToggle && logTableBody) {
        var tailTimer = null;
        var currentOffset = parseInt(liveTailToggle.getAttribute('data-tail-offset') || '0', 10);
        var tailBaseUrl = liveTailToggle.getAttribute('data-tail-url');

        function buildLogRow(entry) {
            var tr = document.createElement('tr');
            tr.style.backgroundColor = '#f0fdf4';

            var tdTime = document.createElement('td');
            var spanTime = document.createElement('span');
            spanTime.className = 'li-log-timestamp';
            spanTime.textContent = entry.timestamp;
            tdTime.appendChild(spanTime);

            var tdLevel = document.createElement('td');
            var spanLevel = document.createElement('span');
            spanLevel.className = 'li-level-badge ' + entry.badge_class;
            spanLevel.textContent = entry.level;
            tdLevel.appendChild(spanLevel);

            var tdChannel = document.createElement('td');
            var spanChannel = document.createElement('span');
            spanChannel.className = 'li-log-channel';
            spanChannel.textContent = entry.channel;
            tdChannel.appendChild(spanChannel);

            var tdMsg = document.createElement('td');
            var divMsg = document.createElement('div');
            divMsg.className = 'li-log-msg';
            divMsg.textContent = entry.message;
            tdMsg.appendChild(divMsg);

            if (entry.has_exception) {
                var divEx = document.createElement('div');
                divEx.className = 'li-exception-highlight';
                divEx.textContent = '💥 ' + (entry.exception_class || '') + ': ' + (entry.exception_location || '');
                tdMsg.appendChild(divEx);
            }

            var tdAction = document.createElement('td');
            tdAction.className = 'text-end';
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-secondary py-1 px-2 js-view-log';
            btn.style.fontSize = '0.775rem';
            btn.setAttribute('data-detail-url', entry.detail_url);
            btn.textContent = 'View';
            tdAction.appendChild(btn);

            tr.appendChild(tdTime);
            tr.appendChild(tdLevel);
            tr.appendChild(tdChannel);
            tr.appendChild(tdMsg);
            tr.appendChild(tdAction);

            setTimeout(function () {
                tr.style.transition = 'background-color 1s ease';
                tr.style.backgroundColor = '';
            }, 2500);

            return tr;
        }

        function pollTail() {
            if (!liveTailToggle.checked) return;

            var sep = tailBaseUrl.indexOf('?') !== -1 ? '&' : '?';
            fetch(tailBaseUrl + sep + 'offset=' + currentOffset, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function (res) {
                if (res.status === 404 || res.status === 403) {
                    liveTailToggle.checked = false;
                    clearInterval(tailTimer);
                    tailTimer = null;
                    return null;
                }
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (data) {
                if (!data) return;
                if (typeof data.offset === 'number') {
                    currentOffset = data.offset;
                }
                if (data.entries && data.entries.length > 0) {
                    // Prepend newest entries at top of table
                    for (var i = 0; i < data.entries.length; i++) {
                        var row = buildLogRow(data.entries[i]);
                        logTableBody.insertBefore(row, logTableBody.firstChild);
                    }
                }
            })
            .catch(function () {
                // Silently retry on transient network error
            });
        }

        liveTailToggle.addEventListener('change', function () {
            if (this.checked) {
                pollTail();
                tailTimer = setInterval(pollTail, 4000);
            } else if (tailTimer) {
                clearInterval(tailTimer);
                tailTimer = null;
            }
        });
    }
});
