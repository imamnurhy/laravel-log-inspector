// Explorin Log Inspector Minimal JS Helper
document.addEventListener('DOMContentLoaded', function () {
    // Copy to clipboard helper
    document.querySelectorAll('[data-copy-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            var targetId = button.getAttribute('data-copy-target');
            var targetEl = document.getElementById(targetId);
            if (targetEl) {
                var text = targetEl.innerText || targetEl.textContent;
                navigator.clipboard.writeText(text).then(function () {
                    var originalText = button.innerText;
                    button.innerText = 'Copied!';
                    setTimeout(function () {
                        button.innerText = originalText;
                    }, 2000);
                });
            }
        });
    });
});
