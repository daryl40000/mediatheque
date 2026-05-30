/**
 * Page imprimable : déclenche la boîte de dialogue d’impression du navigateur.
 * Fichier externe requis (CSP script-src 'self' interdit les onclick inline).
 */
(function () {
    function triggerPrint() {
        window.print();
    }

    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('print-trigger');
        if (btn) {
            btn.addEventListener('click', triggerPrint);
        }
    });
})();
