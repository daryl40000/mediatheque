/**
 * Moncine — petits comportements côté navigateur.
 */

document.addEventListener('DOMContentLoaded', () => {
    initCatalogListNavScrollReset();
    initMobileNav();
    initListAnchors();

    document.querySelectorAll('.marquer-vu-today').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-target');
            const today = btn.getAttribute('data-today');
            if (!id || !today) {
                return;
            }
            const input = document.getElementById(id);
            if (input) {
                input.value = today;
                input.focus();
            }
        });
    });

    initCollectionBulkSelection();
    initContentKindFields();
    initCatalogTitleAutocomplete();
    initShareLinkCopy();

    const params = new URLSearchParams(window.location.search);
    if (params.get('vu') === '1') {
        const main = document.querySelector('main');
        if (main) {
            const box = document.createElement('div');
            box.className = 'alert alert-success';
            box.textContent = 'Film enregistré comme vu. Bon visionnage !';
            main.prepend(box);
        }
    }
});

/** Décalage sous l’en-tête fixe (aligné sur scroll-margin-top des barres de navigation). */
const LIST_NAV_SCROLL_OFFSET_PX = 88;

/**
 * Catalogue : évite la restauration de scroll du navigateur avant l’ancre #catalog-list-nav.
 */
function initCatalogListNavScrollReset() {
    if (window.location.hash !== '#catalog-list-nav') {
        return;
    }
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }
    window.scrollTo(0, 0);
}

/**
 * Ancres #film-list-nav, #catalog-list-nav, #catalog-oeuvre-nav : cadrage sous l’en-tête.
 */
function initListAnchors() {
    const scrollToHash = () => {
        const hash = window.location.hash;
        if (!hash || hash.length < 2) {
            return;
        }
        let target = document.querySelector(hash);
        if (!target && hash === '#film-detail') {
            target = document.getElementById('film-list-nav');
        }
        if (!target) {
            return;
        }

        const top = target.getBoundingClientRect().top + window.scrollY - LIST_NAV_SCROLL_OFFSET_PX;
        const root = document.documentElement;
        const prevScrollBehavior = root.style.scrollBehavior;
        root.style.scrollBehavior = 'auto';
        window.scrollTo(0, Math.max(0, top));
        root.style.scrollBehavior = prevScrollBehavior;
    };

    if (!window.location.hash) {
        return;
    }

    scrollToHash();
}

/**
 * Menu hamburger : ouvre / ferme la navigation sur mobile et tablette.
 */
function initMobileNav() {
    const header = document.getElementById('site-header');
    const toggle = document.getElementById('nav-toggle');
    const nav = document.getElementById('site-nav');
    if (!header || !toggle || !nav) {
        return;
    }

    const closeNav = () => {
        header.classList.remove('is-nav-open');
        document.body.classList.remove('is-nav-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Ouvrir le menu');
    };

    const openNav = () => {
        header.classList.add('is-nav-open');
        document.body.classList.add('is-nav-open');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Fermer le menu');
    };

    toggle.addEventListener('click', () => {
        if (header.classList.contains('is-nav-open')) {
            closeNav();
        } else {
            openNav();
        }
    });

    nav.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', closeNav);
    });

    nav.querySelectorAll('.site-nav__submenu a').forEach((link) => {
        link.addEventListener('click', () => {
            nav.querySelectorAll('.site-nav__menu[open]').forEach((menu) => {
                menu.removeAttribute('open');
            });
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeNav();
        }
    });

    const desktopQuery = window.matchMedia('(min-width: 900px)');
    const onViewportChange = (event) => {
        if (event.matches) {
            closeNav();
        }
    };
    if (typeof desktopQuery.addEventListener === 'function') {
        desktopQuery.addEventListener('change', onViewportChange);
    } else if (typeof desktopQuery.addListener === 'function') {
        desktopQuery.addListener(onViewportChange);
    }
}

/**
 * Cases à cocher sur Ma collection : barre d’actions avec onglets.
 */
function initCollectionBulkSelection() {
    const form = document.getElementById('collection-bulk-form');
    if (!form) {
        return;
    }

    const checkboxes = form.querySelectorAll('.collection-film-cb');
    const selectAll = document.getElementById('collection-select-all');
    const toolbar = document.getElementById('collection-toolbar');
    const countEl = document.getElementById('collection-selected-count');
    const deselectBtn = document.getElementById('collection-deselect-all');
    const tabs = form.querySelectorAll('.collection-toolbar__tab');
    const panels = form.querySelectorAll('.collection-toolbar__panel');

    const setBulkTab = (tabId) => {
        tabs.forEach((tab) => {
            const active = tab.getAttribute('data-bulk-tab') === tabId;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach((panel) => {
            const active = panel.id === 'collection-panel-' + tabId;
            panel.classList.toggle('is-active', active);
            panel.hidden = !active;
        });
    };

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const tabId = tab.getAttribute('data-bulk-tab');
            if (tabId) {
                setBulkTab(tabId);
            }
        });
    });

    const updateUi = () => {
        const selected = form.querySelectorAll('.collection-film-cb:checked');
        const n = selected.length;
        if (countEl) {
            countEl.textContent = String(n);
        }
        if (toolbar) {
            toolbar.hidden = n === 0;
            toolbar.classList.toggle('is-multiple', n > 1);
        }
        if (selectAll) {
            selectAll.checked = n > 0 && n === checkboxes.length;
            selectAll.indeterminate = n > 0 && n < checkboxes.length;
        }
    };

    checkboxes.forEach((cb) => {
        cb.addEventListener('change', updateUi);
    });

    if (selectAll) {
        selectAll.addEventListener('change', () => {
            const checked = selectAll.checked;
            checkboxes.forEach((cb) => {
                cb.checked = checked;
            });
            updateUi();
        });
    }

    if (deselectBtn) {
        deselectBtn.addEventListener('click', () => {
            checkboxes.forEach((cb) => {
                cb.checked = false;
            });
            if (selectAll) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            }
            updateUi();
        });
    }

    form.addEventListener('submit', (event) => {
        const selected = form.querySelectorAll('.collection-film-cb:checked');
        if (selected.length === 0) {
            event.preventDefault();
            window.alert('Cochez au moins un film avant d’appliquer une action.');
            return;
        }

        const submitter = event.submitter;
        const action = submitter instanceof HTMLButtonElement ? submitter.value : '';

        if (action === 'assign_saga') {
            const existing = form.querySelector('#saga_existing');
            const newName = form.querySelector('#saga_new');
            const pick = existing instanceof HTMLSelectElement ? existing.value.trim() : '';
            const created = newName instanceof HTMLInputElement ? newName.value.trim() : '';
            if (pick === '' && created === '') {
                event.preventDefault();
                window.alert('Choisissez une saga existante ou saisissez un nouveau nom.');
                setBulkTab('saga');
            }
            return;
        }

        if (action === 'enrich_tmdb') {
            const n = selected.length;
            const label = n > 1 ? n + ' films' : '1 film';
            if (!window.confirm(
                'Mettre à jour ' + label + ' via TMDB ?\n\n'
                + 'Les fiches sans identifiant TMDB seront ignorées. Cela peut prendre quelques secondes.'
            )) {
                event.preventDefault();
            }
            return;
        }

        if (action === 'delete_films') {
            const n = selected.length;
            const label = n > 1 ? n + ' films' : '1 film';
            if (!window.confirm(
                'Supprimer définitivement ' + label + ' de vos films ?\n\n'
                + 'L’historique des visions sera aussi effacé. Cette action est irréversible.'
            )) {
                event.preventDefault();
            }
        }
    });

    updateUi();
}

/**
 * Autocomplétion du titre à l’ajout : catalogue partagé (titre — réalisateur).
 */
function initCatalogTitleAutocomplete() {
    const root = document.getElementById('catalog-title-autocomplete');
    if (!root) {
        return;
    }

    const input = root.querySelector('.catalog-title-autocomplete__input');
    const list = document.getElementById('catalog-title-suggestions');
    const oeuvreIdInput = document.getElementById('add_oeuvre_id');
    const searchUrl = root.getAttribute('data-search-url') || '/rechercher-oeuvres.php';

    if (!input || !list) {
        return;
    }

    let debounceTimer = null;
    let activeIndex = -1;
    let lastResults = [];

    const setExpanded = (open) => {
        input.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    const closeList = () => {
        list.hidden = true;
        list.innerHTML = '';
        activeIndex = -1;
        lastResults = [];
        setExpanded(false);
    };

    const clearCatalogLink = () => {
        if (oeuvreIdInput) {
            oeuvreIdInput.value = '';
        }
    };

    const fillField = (id, value) => {
        const el = document.getElementById(id);
        if (el) {
            el.value = value ?? '';
        }
    };

    const applySelection = (item) => {
        if (!item) {
            return;
        }

        if (oeuvreIdInput) {
            oeuvreIdInput.value = String(item.id ?? '');
        }
        input.value = item.titre ?? '';

        fillField('add_realisateur', item.realisateur ?? '');
        fillField('add_annee', item.annee > 0 ? String(item.annee) : '');
        fillField('add_styles', item.styles ?? '');
        fillField('add_titre_original', item.titre_original ?? '');
        fillField('add_acteur_1', item.acteur_1 ?? '');
        fillField('add_duree', item.duree ?? '');
        fillField('add_poster_url', item.poster_url ?? '');
        fillField('add_synopsis', item.synopsis ?? '');
        fillField('add_tmdb', item.tmdb_id > 0 ? String(item.tmdb_id) : '');

        const kindSelect = document.getElementById('add_content_kind');
        if (kindSelect && item.content_kind) {
            kindSelect.value = item.content_kind;
            kindSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }

        closeList();
    };

    const renderResults = (results) => {
        lastResults = results;
        list.innerHTML = '';

        if (results.length === 0) {
            closeList();
            return;
        }

        results.forEach((item, index) => {
            const li = document.createElement('li');
            li.className = 'catalog-title-autocomplete__option';
            li.setAttribute('role', 'option');
            li.id = 'catalog-title-option-' + index;
            li.dataset.index = String(index);

            const main = document.createElement('span');
            main.className = 'catalog-title-autocomplete__option-label';
            main.textContent = item.label ?? item.titre ?? '';

            li.appendChild(main);

            if (item.in_library && item.library_statut_label) {
                const badge = document.createElement('span');
                badge.className = 'catalog-title-autocomplete__badge';
                badge.textContent = 'Déjà dans : ' + item.library_statut_label;
                li.appendChild(badge);
            }

            li.addEventListener('mousedown', (event) => {
                event.preventDefault();
                applySelection(item);
            });

            list.appendChild(li);
        });

        list.hidden = false;
        setExpanded(true);
        activeIndex = -1;
    };

    const fetchSuggestions = async (query) => {
        const url = searchUrl + '?q=' + encodeURIComponent(query);
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            return [];
        }
        const data = await response.json();
        return Array.isArray(data.results) ? data.results : [];
    };

    const scheduleSearch = () => {
        if (debounceTimer !== null) {
            clearTimeout(debounceTimer);
        }
        debounceTimer = setTimeout(async () => {
            debounceTimer = null;
            const query = input.value.trim();
            if (query.length < 2) {
                closeList();
                return;
            }
            try {
                const results = await fetchSuggestions(query);
                renderResults(results);
            } catch {
                closeList();
            }
        }, 280);
    };

    input.addEventListener('input', () => {
        clearCatalogLink();
        scheduleSearch();
    });

    input.addEventListener('keydown', (event) => {
        if (list.hidden || lastResults.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            activeIndex = Math.min(activeIndex + 1, lastResults.length - 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            activeIndex = Math.max(activeIndex - 1, 0);
        } else if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            applySelection(lastResults[activeIndex]);
            return;
        } else if (event.key === 'Escape') {
            closeList();
            return;
        } else {
            return;
        }

        list.querySelectorAll('.catalog-title-autocomplete__option').forEach((el, i) => {
            const selected = i === activeIndex;
            el.classList.toggle('is-active', selected);
            el.setAttribute('aria-selected', selected ? 'true' : 'false');
        });
        if (activeIndex >= 0) {
            const activeEl = document.getElementById('catalog-title-option-' + activeIndex);
            activeEl?.scrollIntoView({ block: 'nearest' });
        }
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            closeList();
        }
    });
}

/** Affiche les champs « saison » quand la catégorie Série est choisie. */
function initContentKindFields() {
    document.querySelectorAll('.js-content-kind-select').forEach((select) => {
        const prefix = select.id.replace(/_content_kind$/, '');
        const block = document.getElementById(prefix + '_serie_fields');
        if (!block) {
            return;
        }
        const sync = () => {
            const isSerie = select.value === 'serie';
            block.classList.toggle('is-hidden', !isSerie);
        };
        select.addEventListener('change', sync);
        sync();
    });
}

/** Copie l’URL d’un lien de partage dans le presse-papiers. */
function initShareLinkCopy() {
    document.querySelectorAll('.share-delivery__copy').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const targetId = btn.getAttribute('data-copy-target');
            if (!targetId) {
                return;
            }
            const input = document.getElementById(targetId);
            if (!input || !(input instanceof HTMLInputElement)) {
                return;
            }
            const url = input.value;
            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(url);
                } else {
                    input.select();
                    document.execCommand('copy');
                }
                const label = btn.textContent;
                btn.textContent = 'Copié !';
                window.setTimeout(() => {
                    btn.textContent = label || 'Copier';
                }, 2000);
            } catch {
                input.select();
            }
        });
    });
}
