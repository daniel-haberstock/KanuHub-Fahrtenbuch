/**
 * Fahrtenbuch Beta — member.js
 * Task 13: Member Area — Tab-Navigation + Lazy Loading
 */
(function () {
    'use strict';

    const TABS = ['statistik', 'fahrten', 'errungenschaften', 'rangliste', 'arbeitseinsatz', 'einstellungen'];
    const _loaded = {};

    function activeTab() {
        const hash = (location.hash || '#statistik').replace('#', '');
        return TABS.includes(hash) ? hash : TABS[0];
    }

    function switchTab(tab) {
        TABS.forEach(t => {
            const pane = document.getElementById('pane-' + t);
            const link = document.querySelector('[data-tab="' + t + '"]');
            if (pane) pane.style.display = t === tab ? '' : 'none';
            if (link) link.classList.toggle('active', t === tab);
        });

        if (!_loaded[tab]) {
            _loaded[tab] = true;
            document.dispatchEvent(new CustomEvent('kcs:tab-change', { detail: { tab } }));
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Tab clicks
        document.querySelectorAll('[data-tab]').forEach(link => {
            link.addEventListener('click', e => {
                e.preventDefault();
                const tab = link.dataset.tab;
                location.hash = tab;
                switchTab(tab);
            });
        });

        // Initial tab from hash
        switchTab(activeTab());
    });

    window.addEventListener('hashchange', () => switchTab(activeTab()));

    // Member button in header → navigate to member.php
    document.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('btnOpenMember');
        if (btn) {
            btn.addEventListener('click', () => {
                window.location.href = (KCS.basePath || '') + '/statistik/';
            });
        }
    });

})();
