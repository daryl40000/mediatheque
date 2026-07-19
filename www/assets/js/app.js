/**
 * Moncine — petits comportements côté navigateur.
 */

document.addEventListener('DOMContentLoaded', () => {
    const runInit = (label, fn) => {
        try {
            fn();
        } catch (error) {
            console.error('[mediatheque] Échec init ' + label + ':', error);
        }
    };

    runInit('seriesPossessionFilterMemory', initSeriesPossessionFilterMemory);
    runInit('catalogListNavScrollReset', initCatalogListNavScrollReset);
    runInit('mobileNav', initMobileNav);
    runInit('desktopNavMenus', initDesktopNavMenus);
    runInit('summaryInfoTooltips', initSummaryInfoTooltips);
    runInit('listAnchors', initListAnchors);

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

    runInit('collectionBulkSelection', initCollectionBulkSelection);
    runInit('catalogAdminBulkSelection', initCatalogAdminBulkSelection);
    runInit('contentKindFields', initContentKindFields);
    runInit('catalogTitleAutocomplete', initCatalogTitleAutocomplete);
    runInit('catalogAdminCategoryFields', initCatalogAdminCategoryFields);
    runInit('catalogGameTitleAutocomplete', initCatalogGameTitleAutocomplete);
    runInit('magazineSubjectAutocompleteFields', initMagazineSubjectAutocompleteFields);
    runInit('magazineSeriesTagsField', initMagazineSeriesTagsField);
    runInit('magazineSeriesCategoryFilter', initMagazineSeriesCategoryFilter);
    runInit('magazineSeriesCatalogAutocomplete', initMagazineSeriesCatalogAutocomplete);
    runInit('magazineIssueCatalogAutocomplete', initMagazineIssueCatalogAutocomplete);
    runInit('tagsBadgeFields', initTagsBadgeFields);
    runInit('gamePlatformFields', initGamePlatformFields);
    runInit('gameEditionFields', initGameEditionFields);
    runInit('gameRelationFields', initGameRelationFields);
    runInit('gameShelfHoverPreviews', initGameShelfHoverPreviews);
    runInit('collectionGridHoverBubbles', initCollectionGridHoverBubbles);
    runInit('magazineSubjectStripHoverBubbles', initMagazineSubjectStripHoverBubbles);
    runInit('shareLinkCopy', initShareLinkCopy);
    runInit('steamImportMapping', initSteamImportMapping);
    runInit('catalogOeuvreMerge', initCatalogOeuvreMerge);
    runInit('gameLibraryEditForms', initGameLibraryEditForms);
    runInit('gameDetailQuickActions', initGameDetailQuickActions);
    runInit('globalSearch', initGlobalSearch);

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

const SERIES_POSSESSION_FILTERS = new Set(['all', 'owned', 'unowned', 'hors_serie']);

/**
 * Mémorise le filtre Possédé / Non possédé sur les pages série BD et magazines.
 */
function initSeriesPossessionFilterMemory() {
    const nav = document.querySelector('.magazine-possession-filter');
    if (!nav) {
        return;
    }

    const path = window.location.pathname;
    let storageKey = null;
    if (path.endsWith('/serie-magazine.php')) {
        storageKey = 'mediatheque.seriesPossession.magazine';
    } else if (path.endsWith('/serie-bd.php')) {
        storageKey = 'mediatheque.seriesPossession.bd';
    }
    if (!storageKey) {
        return;
    }

    const params = new URLSearchParams(window.location.search);
    const statut = params.get('statut') || 'collection';
    if (statut === 'wishlist') {
        return;
    }

    if (params.has('possession')) {
        const current = params.get('possession') || 'all';
        if (SERIES_POSSESSION_FILTERS.has(current)) {
            localStorage.setItem(storageKey, current);
        }
    } else {
        const saved = localStorage.getItem(storageKey);
        if (saved && saved !== 'all' && SERIES_POSSESSION_FILTERS.has(saved)) {
            params.set('possession', saved);
            window.location.replace(`${path}?${params.toString()}`);
            return;
        }
    }

    nav.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link || !nav.contains(link)) {
            return;
        }
        try {
            const linkUrl = new URL(link.href, window.location.origin);
            const linkParams = new URLSearchParams(linkUrl.search);
            const possession = linkParams.get('possession') || 'all';
            if (SERIES_POSSESSION_FILTERS.has(possession)) {
                localStorage.setItem(storageKey, possession);
            }
        } catch {
            // URL invalide : on ignore.
        }
    });
}

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

    const mobileQuery = window.matchMedia('(max-width: 899px)');
    let savedScrollY = 0;
    /** @type {((event: TouchEvent) => void) | null} */
    let touchMoveBlocker = null;

    const usesMobilePanel = () => mobileQuery.matches;

    const enableTouchScrollLock = () => {
        if (touchMoveBlocker !== null) {
            return;
        }
        touchMoveBlocker = (event) => {
            if (!header.classList.contains('is-nav-open')) {
                return;
            }
            if (nav.contains(event.target)) {
                return;
            }
            event.preventDefault();
        };
        document.addEventListener('touchmove', touchMoveBlocker, { passive: false });
    };

    const disableTouchScrollLock = () => {
        if (touchMoveBlocker === null) {
            return;
        }
        document.removeEventListener('touchmove', touchMoveBlocker);
        touchMoveBlocker = null;
    };

    const syncMobileNavPanel = () => {
        if (!usesMobilePanel() || !header.classList.contains('is-nav-open')) {
            return;
        }

        nav.classList.remove('is-mobile-nav-panel');
        nav.style.removeProperty('--mobile-nav-panel-top');

        window.requestAnimationFrame(() => {
            if (!header.classList.contains('is-nav-open')) {
                return;
            }
            const top = Math.max(0, Math.round(nav.getBoundingClientRect().top));
            nav.style.setProperty('--mobile-nav-panel-top', `${top}px`);
            nav.classList.add('is-mobile-nav-panel');
        });
    };

    const unlockBodyScroll = () => {
        const scrollY = savedScrollY;
        document.body.classList.remove('is-nav-open');
        document.body.style.removeProperty('top');
        savedScrollY = 0;
        window.scrollTo(0, scrollY);
    };

    const closeNav = () => {
        header.classList.remove('is-nav-open');
        nav.classList.remove('is-mobile-nav-panel');
        nav.style.removeProperty('--mobile-nav-panel-top');
        disableTouchScrollLock();
        unlockBodyScroll();
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Ouvrir le menu');
    };

    const openNav = () => {
        if (usesMobilePanel()) {
            savedScrollY = window.scrollY;
            document.body.style.top = `-${savedScrollY}px`;
        }

        header.classList.add('is-nav-open');
        document.body.classList.add('is-nav-open');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Fermer le menu');

        if (usesMobilePanel()) {
            enableTouchScrollLock();
            syncMobileNavPanel();
        }
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

    window.addEventListener('resize', () => {
        if (header.classList.contains('is-nav-open') && usesMobilePanel()) {
            syncMobileNavPanel();
        }
    });

    const desktopQuery = window.matchMedia('(min-width: 900px)');
    const onViewportChange = (event) => {
        if (event.matches) {
            closeNav();
        } else if (header.classList.contains('is-nav-open')) {
            syncMobileNavPanel();
        }
    };
    if (typeof desktopQuery.addEventListener === 'function') {
        desktopQuery.addEventListener('change', onViewportChange);
    } else if (typeof desktopQuery.addListener === 'function') {
        desktopQuery.addListener(onViewportChange);
    }
}

/**
 * Menus Paramètres / Gestion (desktop) : fermeture au retrait de la souris.
 */
function initDesktopNavMenus() {
    const nav = document.getElementById('site-nav');
    if (!nav) {
        return;
    }

    const desktopQuery = window.matchMedia('(min-width: 900px)');
    const menus = nav.querySelectorAll('.site-nav__menu');
    if (menus.length === 0) {
        return;
    }

    const closeTimers = new WeakMap();

    const cancelScheduledClose = (menu) => {
        const timer = closeTimers.get(menu);
        if (timer !== undefined) {
            clearTimeout(timer);
            closeTimers.delete(menu);
        }
    };

    const scheduleClose = (menu) => {
        if (!desktopQuery.matches) {
            return;
        }
        cancelScheduledClose(menu);
        closeTimers.set(
            menu,
            window.setTimeout(() => {
                menu.removeAttribute('open');
                closeTimers.delete(menu);
            }, 100)
        );
    };

    menus.forEach((menu) => {
        menu.addEventListener('mouseenter', () => {
            cancelScheduledClose(menu);
        });
        menu.addEventListener('mouseleave', () => {
            scheduleClose(menu);
        });

        const summary = menu.querySelector('.site-nav__menu-summary');
        if (summary) {
            summary.addEventListener('click', () => {
                if (!desktopQuery.matches) {
                    return;
                }
                menus.forEach((other) => {
                    if (other !== menu) {
                        other.removeAttribute('open');
                    }
                });
            });
        }
    });

    document.addEventListener('click', (event) => {
        if (!desktopQuery.matches) {
            return;
        }
        if (event.target.closest('.site-nav__menu')) {
            return;
        }
        menus.forEach((menu) => {
            menu.removeAttribute('open');
        });
    });
}

/** Empêche l’icône « i » dans un summary d’ouvrir/fermer le panneau. */
function initSummaryInfoTooltips() {
    document.querySelectorAll('.catalog-admin-panel__summary .info-tooltip, .section-heading-with-info .info-tooltip').forEach((tip) => {
        tip.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
        });
    });
}

/**
 * Cases à cocher sur le catalogue admin : suppression groupée.
 */
function initCatalogAdminBulkSelection() {
    const form = document.getElementById('catalog-bulk-form');
    if (!form) {
        return;
    }

    const checkboxes = form.querySelectorAll('.catalog-oeuvre-cb');
    const selectAll = document.getElementById('catalog-bulk-select-all');
    const toolbar = document.getElementById('catalog-bulk-toolbar');
    const countEl = document.getElementById('catalog-bulk-selected-count');
    const deselectBtn = document.getElementById('catalog-bulk-deselect-all');
    const deleteBtn = document.getElementById('catalog-bulk-delete-btn');

    const updateUi = () => {
        const selected = form.querySelectorAll('.catalog-oeuvre-cb:checked');
        const n = selected.length;
        if (countEl) {
            countEl.textContent = String(n);
        }
        if (toolbar) {
            toolbar.hidden = n === 0;
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

    if (deleteBtn) {
        form.addEventListener('submit', (event) => {
            const selected = form.querySelectorAll('.catalog-oeuvre-cb:checked').length;
            if (selected === 0) {
                event.preventDefault();
                return;
            }
            const message = deleteBtn.getAttribute('data-confirm')
                || 'Supprimer les fiches sélectionnées du catalogue ?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
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
 * Formulaire film : masque les champs catalogue quand une œuvre existante est choisie.
 */
function setFilmCatalogLinkedState(form, linked) {
    if (!form) {
        return;
    }
    const canManageCatalog = form.dataset.canManageCatalog === '1';
    const catalogEdit = form.querySelector('[data-film-catalog-edit-fields]');
    const libraryFields = form.querySelector('[data-film-library-fields]');
    const pickHint = form.querySelector('[data-film-pick-catalog-hint]');

    if (catalogEdit) {
        catalogEdit.hidden = linked || !canManageCatalog;
    }
    if (libraryFields) {
        libraryFields.hidden = !canManageCatalog && !linked;
    }
    if (pickHint) {
        pickHint.hidden = linked;
    }
    form.dataset.filmCatalogLinked = linked ? '1' : '0';
}

/**
 * Requête JSON standard des autocomplétions catalogue (?q=…).
 *
 * @param {string} searchUrl
 * @param {string} query
 * @returns {Promise<object[]>}
 */
async function fetchCatalogAutocompleteResults(searchUrl, query) {
    const separator = searchUrl.includes('?') ? '&' : '?';
    const url = searchUrl + separator + 'q=' + encodeURIComponent(query);
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });
    if (!response.ok) {
        return [];
    }
    const data = await response.json();
    return Array.isArray(data.results) ? data.results : [];
}

/**
 * Crée une ligne <li> pour une suggestion d’autocomplétion catalogue.
 */
function createCatalogAutocompleteOption({
    item,
    index,
    optionIdPrefix,
    label,
    badgeText = null,
    extraClass = '',
    onSelect,
}) {
    const li = document.createElement('li');
    li.className = 'catalog-title-autocomplete__option'
        + (extraClass ? ' ' + extraClass : '');
    li.setAttribute('role', 'option');
    li.id = optionIdPrefix + '-' + index;
    li.dataset.index = String(index);

    const main = document.createElement('span');
    main.className = 'catalog-title-autocomplete__option-label';
    main.textContent = label;
    li.appendChild(main);

    if (badgeText) {
        const badge = document.createElement('span');
        badge.className = 'catalog-title-autocomplete__badge';
        badge.textContent = badgeText;
        li.appendChild(badge);
    }

    li.addEventListener('mousedown', (event) => {
        event.preventDefault();
        onSelect(item);
    });

    return li;
}

/**
 * Moteur partagé : debounce, fetch, liste, clavier, fermeture.
 *
 * @returns {{ closeList: () => void }}
 */
function attachCatalogAutocomplete(config) {
    const {
        root,
        input,
        list,
        searchUrl,
        optionSelector = '.catalog-title-autocomplete__option',
        optionIdPrefix = 'catalog-autocomplete-option',
        minChars = 2,
        debounceMs = 280,
        onInputClear = () => {},
        onSelect,
        buildOption,
        dismissOn = 'click-outside',
        blurCloseDelayMs = 150,
        keyboardRequiresVisibleList = true,
    } = config;

    if (!input || !list || typeof onSelect !== 'function' || typeof buildOption !== 'function') {
        return { closeList: () => {} };
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

    const renderResults = (results) => {
        lastResults = results;
        list.innerHTML = '';

        if (results.length === 0) {
            closeList();
            return;
        }

        results.forEach((item, index) => {
            list.appendChild(buildOption(item, index, onSelect));
        });

        list.hidden = false;
        setExpanded(true);
        activeIndex = -1;
    };

    const scheduleSearch = () => {
        if (debounceTimer !== null) {
            clearTimeout(debounceTimer);
        }
        debounceTimer = setTimeout(async () => {
            debounceTimer = null;
            const query = input.value.trim();
            if (query.length < minChars) {
                closeList();
                return;
            }
            try {
                const results = await fetchCatalogAutocompleteResults(searchUrl, query);
                renderResults(results);
            } catch {
                closeList();
            }
        }, debounceMs);
    };

    input.addEventListener('input', () => {
        onInputClear();
        scheduleSearch();
    });

    input.addEventListener('keydown', (event) => {
        const options = list.querySelectorAll(optionSelector);
        if (keyboardRequiresVisibleList && (list.hidden || lastResults.length === 0)) {
            return;
        }
        if (options.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            activeIndex = Math.min(activeIndex + 1, lastResults.length - 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            activeIndex = Math.max(activeIndex - 1, 0);
        } else if (event.key === 'Enter' && activeIndex >= 0 && lastResults[activeIndex]) {
            event.preventDefault();
            onSelect(lastResults[activeIndex]);
            return;
        } else if (event.key === 'Escape') {
            closeList();
            return;
        } else {
            return;
        }

        options.forEach((el, i) => {
            const selected = i === activeIndex;
            el.classList.toggle('is-active', selected);
            el.setAttribute('aria-selected', selected ? 'true' : 'false');
        });
        if (activeIndex >= 0) {
            const activeEl = document.getElementById(optionIdPrefix + '-' + activeIndex);
            activeEl?.scrollIntoView({ block: 'nearest' });
        }
    });

    if (dismissOn === 'click-outside' || dismissOn === 'both') {
        document.addEventListener('click', (event) => {
            if (!root.contains(event.target)) {
                closeList();
            }
        });
    }

    if (dismissOn === 'blur' || dismissOn === 'both') {
        input.addEventListener('blur', () => {
            setTimeout(closeList, blurCloseDelayMs);
        });
    }

    return { closeList };
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

    let closeList = () => {};

    const clearCatalogLink = () => {
        if (oeuvreIdInput) {
            oeuvreIdInput.value = '';
        }
        const form = document.querySelector('.film-edit-form');
        setFilmCatalogLinkedState(form, false);
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

        const form = document.querySelector('.film-edit-form');
        setFilmCatalogLinkedState(form, true);

        closeList();
    };

    ({ closeList } = attachCatalogAutocomplete({
        root,
        input,
        list,
        searchUrl,
        optionIdPrefix: 'catalog-title-option',
        onInputClear: clearCatalogLink,
        onSelect: applySelection,
        buildOption: (item, index, onSelect) => createCatalogAutocompleteOption({
            item,
            index,
            optionIdPrefix: 'catalog-title-option',
            label: item.label ?? item.titre ?? '',
            badgeText: item.in_library && item.library_statut_label
                ? 'Déjà dans : ' + item.library_statut_label
                : null,
            onSelect,
        }),
    }));

    const form = document.querySelector('.film-edit-form');
    if (form && oeuvreIdInput && String(oeuvreIdInput.value || '').trim() !== '') {
        setFilmCatalogLinkedState(form, true);
    }
}

/** Bascule film / jeu vidéo dans le formulaire admin catalogue. */
function initCatalogAdminCategoryFields() {
    const form = document.querySelector('.catalog-admin-form');
    if (!form) {
        return;
    }

    const select = form.querySelector('.js-content-kind-select');
    const filmPanel = form.querySelector('[data-catalog-panel="film"]');
    const gamePanel = form.querySelector('[data-catalog-panel="game"]');
    if (!select || !filmPanel || !gamePanel) {
        return;
    }

    const setPanelDisabled = (panel, disabled) => {
        panel.querySelectorAll('input, select, textarea, button').forEach((el) => {
            if (el === select) {
                return;
            }
            el.disabled = disabled;
        });
    };

    const sync = () => {
        const isGame = select.value === 'jeu_video';
        filmPanel.classList.toggle('is-hidden', isGame);
        gamePanel.classList.toggle('is-hidden', !isGame);
        gamePanel.hidden = !isGame;
        setPanelDisabled(filmPanel, isGame);
        setPanelDisabled(gamePanel, !isGame);

        const filmTitre = document.getElementById('add_titre');
        const gameTitre = document.getElementById('add_game_titre');
        if (filmTitre) {
            filmTitre.required = !isGame;
        }
        if (gameTitre) {
            gameTitre.required = isGame;
        }
    };

    select.addEventListener('change', sync);
    sync();
}

/**
 * Autocomplétion du titre — catalogue jeux (ajout collection ou admin catalogue).
 */
function initCatalogGameTitleAutocomplete() {
    document.querySelectorAll('[data-game-catalog-autocomplete]').forEach((root) => {
        initGameCatalogAutocompleteRoot(root);
    });
}

function initGameCatalogAutocompleteRoot(root) {
    const input = root.querySelector('.catalog-title-autocomplete__input');
    const list = root.querySelector('.catalog-title-autocomplete__list');
    const oeuvreIdInput = document.getElementById(root.dataset.oeuvreIdInput || 'add_game_oeuvre_id');
    const searchUrl = root.getAttribute('data-search-url') || '/rechercher-jeux-catalogue.php';
    const fieldMap = {
        annee: root.dataset.anneeInput || 'add_game_annee',
        studio: root.dataset.studioInput || 'add_game_studio',
        platform: root.dataset.platformInput || 'add_game_platform',
        editeur: root.dataset.editeurInput || '',
        synopsis: root.dataset.synopsisInput || '',
    };

    if (!input || !list) {
        return;
    }

    const optionIdPrefix = 'game-catalog-option-' + (root.id || Math.random().toString(36).slice(2, 8));
    let closeList = () => {};

    const clearCatalogLink = () => {
        if (oeuvreIdInput) {
            oeuvreIdInput.value = '';
        }
        syncGameTypeFieldsetForCatalogLink();
        const form = root.closest('form');
        if (form) {
            setGameCatalogPlatformState(form, [], { catalogLinked: false });
        }
    };

    const syncGameTypeFieldsetForCatalogLink = () => {
        const form = root.closest('form');
        const typeFieldset = form?.querySelector('[data-game-type-fieldset]');
        if (!typeFieldset) {
            return;
        }
        const linked = oeuvreIdInput !== null && String(oeuvreIdInput.value || '').trim() !== '';
        typeFieldset.hidden = linked;
        if (linked) {
            typeFieldset.querySelectorAll('[data-game-extension-toggle], [data-game-remake-toggle]').forEach((toggle) => {
                if (toggle instanceof HTMLInputElement && toggle.checked) {
                    toggle.checked = false;
                    toggle.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        }
    };

    const fillFieldById = (id, value) => {
        if (!id) {
            return;
        }
        const el = document.getElementById(id);
        if (!el) {
            return;
        }
        el.value = value ?? '';
        el.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const applySelection = (item) => {
        if (!item) {
            return;
        }

        if (oeuvreIdInput) {
            oeuvreIdInput.value = String(item.oeuvre_id ?? '');
        }
        input.value = item.titre ?? item.display_label ?? '';

        fillFieldById(fieldMap.annee, item.annee > 0 ? String(item.annee) : '');
        fillFieldById(fieldMap.studio, item.studio ?? '');
        fillFieldById(fieldMap.editeur, item.editeur ?? '');
        fillFieldById(fieldMap.synopsis, item.synopsis ?? '');

        const form = root.closest('form');
        const platformKeys = Array.isArray(item.platform_list) && item.platform_list.length > 0
            ? item.platform_list
            : (item.platform ? [item.platform] : []);
        if (form && platformKeys.length > 0) {
            // Nouveau choix catalogue → on repart d’exemplaire vide (pas les cases déjà cochées).
            setGameCatalogPlatformState(form, platformKeys, { catalogLinked: true, resetOwned: true });
            fillFieldById(fieldMap.platform, platformKeys[0] ?? item.platform ?? '');
        }

        closeList();
        syncGameTypeFieldsetForCatalogLink();
    };

    ({ closeList } = attachCatalogAutocomplete({
        root,
        input,
        list,
        searchUrl,
        optionIdPrefix,
        onInputClear: clearCatalogLink,
        onSelect: applySelection,
        buildOption: (item, index, onSelect) => createCatalogAutocompleteOption({
            item,
            index,
            optionIdPrefix,
            extraClass: 'catalog-title-autocomplete__option--game',
            label: item.display_label ?? item.titre ?? '',
            badgeText: item.in_library ? 'Déjà dans votre bibliothèque' : null,
            onSelect,
        }),
    }));

    syncGameTypeFieldsetForCatalogLink();
    const form = root.closest('form');
    if (form && oeuvreIdInput && String(oeuvreIdInput.value || '').trim() !== '') {
        const catalogRoot = form.querySelector('[data-field-name="platforms[]"]');
        const keys = catalogRoot
            ? [...catalogRoot.querySelectorAll('input[type="checkbox"]:checked')].map((el) => el.value)
            : [];
        if (keys.length > 0) {
            setGameCatalogPlatformState(form, keys, { catalogLinked: true });
        }
    }
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
            const isGame = select.value === 'jeu_video';
            block.classList.toggle('is-hidden', !isSerie || isGame);
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

/** Autocomplétion des sujets magazines (recherche, liste, fiche numéro). */
function initMagazineSubjectAutocompleteFields() {
    document.querySelectorAll('[data-magazine-subject-autocomplete]').forEach((row) => {
        const input = row.querySelector('input[type="search"], input[type="text"]');
        const list = row.querySelector('[role="listbox"]');
        const searchUrl = row.getAttribute('data-search-url') || '/rechercher-sujets-magazine.php';
        const mode = row.getAttribute('data-magazine-subject-autocomplete') || 'navigate';
        const form = row.closest('form');
        const mediaLinkCategories = new Set(
            (form?.getAttribute('data-catalog-link-categories') || 'test,preview,interview,dossier,soluce')
                .split(',')
                .map((value) => value.trim())
                .filter(Boolean)
        );
        const categorySelect = form
            ? form.querySelector('#subject_category, #attach_category')
            : document.getElementById('subject_category');
        const mediaDomainSelect = form?.querySelector('#attach_catalog_media_domain');
        const catalogSearchUrl = form?.getAttribute('data-catalog-search-url')
            || form?.getAttribute('data-game-catalog-url')
            || '';
        const catalogInput = form?.querySelector('#attach_catalog_oeuvre_id');
        const gameHint = form?.querySelector('#attach_game_catalog_hint');
        const gameHintLabel = form?.querySelector('#attach_game_catalog_label');
        const clearGameBtn = form?.querySelector('#attach_clear_game_catalog');

        if (!input || !list) {
            return;
        }

        let debounceTimer = null;
        let linkedCatalogLabel = '';

        const selectedMediaDomain = () => (mediaDomainSelect ? mediaDomainSelect.value.trim() : '');

        const supportsMediaCatalog = () => (
            catalogSearchUrl !== ''
            && selectedMediaDomain() !== ''
            && categorySelect
            && mediaLinkCategories.has(categorySelect.value)
        );

        const closeList = () => {
            list.hidden = true;
            list.innerHTML = '';
        };

        const clearCatalogLink = () => {
            linkedCatalogLabel = '';
            if (catalogInput) {
                catalogInput.value = '';
            }
            if (gameHint) {
                gameHint.hidden = true;
            }
            if (gameHintLabel) {
                gameHintLabel.textContent = '';
            }
        };

        const showCatalogLink = (label) => {
            linkedCatalogLabel = label || '';
            if (gameHint && gameHintLabel && label) {
                gameHintLabel.textContent = label;
                gameHint.hidden = false;
            }
        };

        const setParutionYear = (year) => {
            const yearSelect = form?.querySelector('#attach_parution_year');
            if (!yearSelect || !year || year <= 0) {
                return;
            }
            const yearStr = String(year);
            const existing = [...yearSelect.options].find((entry) => entry.value === yearStr);
            if (existing) {
                yearSelect.value = yearStr;
                return;
            }
            const option = document.createElement('option');
            option.value = yearStr;
            option.textContent = yearStr;
            yearSelect.appendChild(option);
            yearSelect.value = yearStr;
        };

        const applyMediaCatalogSelection = (item) => {
            input.value = item.titre || item.display_label || '';
            linkedCatalogLabel = input.value.trim();
            if (catalogInput) {
                catalogInput.value = String(item.oeuvre_id || '');
            }
            setParutionYear(item.annee);

            const detailField = form ? form.querySelector('#attach_detail') : null;
            if (detailField && item.media_domain === 'jeu' && (item.platform_short || item.platform_label)) {
                const platformValue = item.platform_short || item.platform_label || '';
                if (detailField.tagName === 'SELECT') {
                    const option = [...detailField.options].find(
                        (entry) => entry.value.toLowerCase() === String(platformValue).toLowerCase()
                    );
                    if (option) {
                        detailField.value = option.value;
                    }
                } else if (detailField.tagName === 'INPUT') {
                    detailField.value = platformValue;
                }
            }

            showCatalogLink(item.display_label || item.titre || '');
            closeList();
            input.focus();
        };

        const applyFillSelection = (item) => {
            input.value = item.label || '';

            if (categorySelect && item.category) {
                categorySelect.value = item.category;
            }

            const detailField = form ? form.querySelector('#attach_detail') : null;
            if (detailField && item.detail) {
                if (detailField.tagName === 'SELECT') {
                    const option = [...detailField.options].find(
                        (entry) => entry.value.toLowerCase() === String(item.detail).toLowerCase()
                    );
                    if (option) {
                        detailField.value = option.value;
                    }
                } else if (detailField.tagName === 'INPUT') {
                    detailField.value = item.detail;
                }
            }

            if (item.parution_year) {
                setParutionYear(item.parution_year);
            }

            if (catalogInput) {
                if (item.catalog_oeuvre_id && item.catalog_oeuvre_id > 0) {
                    catalogInput.value = String(item.catalog_oeuvre_id);
                    showCatalogLink(item.display_label || item.label || '');
                } else {
                    clearCatalogLink();
                }
            }

            closeList();
            input.focus();
        };

        const renderResults = (results) => {
            list.innerHTML = '';
            if (!results.length) {
                closeList();
                return;
            }

            results.forEach((item) => {
                const li = document.createElement('li');
                const isMediaCatalog = item.source === 'media_catalog' || item.source === 'game_catalog';
                li.className = 'catalog-title-autocomplete__option'
                    + (isMediaCatalog ? ' catalog-title-autocomplete__option--game' : '');
                li.setAttribute('role', 'option');

                const main = document.createElement('span');
                main.className = 'catalog-title-autocomplete__option-label';
                main.textContent = item.display_label || item.label || item.titre || '';

                const meta = document.createElement('span');
                meta.className = 'hint';
                if (isMediaCatalog) {
                    meta.textContent = (item.media_domain_label || 'Catalogue')
                        + (item.in_library ? ' · dans votre bibliothèque' : '');
                } else {
                    meta.textContent = (item.category_label || '')
                        + (item.issue_count ? ' · ' + item.issue_count + ' num.' : '');
                }

                li.appendChild(main);
                li.appendChild(meta);

                li.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    if (isMediaCatalog) {
                        applyMediaCatalogSelection(item);
                        return;
                    }
                    if (mode === 'fill') {
                        applyFillSelection(item);
                        return;
                    }
                    if (item.url) {
                        window.location.href = item.url;
                        return;
                    }
                    input.value = item.label || '';
                    closeList();
                });

                list.appendChild(li);
            });

            list.hidden = false;
        };

        const fetchResults = () => {
            const q = input.value.trim();
            if (q.length < 2) {
                closeList();
                return;
            }

            const subjectParams = new URLSearchParams({ q });
            if (categorySelect && categorySelect.value && categorySelect.id === 'subject_category') {
                subjectParams.set('category', categorySelect.value);
            }

            const requests = [
                fetch(searchUrl + '?' + subjectParams.toString(), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                }).then((response) => response.json()),
            ];

            if (supportsMediaCatalog()) {
                const catalogParams = new URLSearchParams({
                    q,
                    domain: selectedMediaDomain(),
                });
                requests.push(
                    fetch(catalogSearchUrl + '?' + catalogParams.toString(), {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    }).then((response) => response.json())
                );
            }

            Promise.all(requests)
                .then((payloads) => {
                    const subjectResults = Array.isArray(payloads[0]?.results) ? payloads[0].results : [];
                    const mediaResults = payloads.length > 1 && Array.isArray(payloads[1]?.results)
                        ? payloads[1].results
                        : [];
                    renderResults([...mediaResults, ...subjectResults]);
                })
                .catch(() => closeList());
        };

        input.addEventListener('input', () => {
            if (catalogInput && linkedCatalogLabel !== '' && input.value.trim() !== linkedCatalogLabel.trim()) {
                clearCatalogLink();
            }
            window.clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(fetchResults, 250);
        });

        input.addEventListener('blur', () => {
            window.setTimeout(closeList, 150);
        });

        if (categorySelect && categorySelect.id === 'subject_category') {
            categorySelect.addEventListener('change', () => {
                if (input.value.trim().length >= 2) {
                    fetchResults();
                }
            });
        }

        if (categorySelect && categorySelect.id === 'attach_category') {
            categorySelect.addEventListener('change', () => {
                if (!supportsMediaCatalog()) {
                    clearCatalogLink();
                } else if (input.value.trim().length >= 2) {
                    fetchResults();
                }
            });
        }

        mediaDomainSelect?.addEventListener('change', () => {
            clearCatalogLink();
            if (input.value.trim().length >= 2 && supportsMediaCatalog()) {
                fetchResults();
            }
        });

        clearGameBtn?.addEventListener('click', () => {
            clearCatalogLink();
            input.focus();
        });
    });
}

/**
 * Champs tags / genres en badges (magazines, jeux…).
 */
function initTagsBadgeFields() {
    document.querySelectorAll('[data-tags-badge-field]').forEach((root) => {
        // Évite d’attacher deux fois les écouteurs si la fonction est rappelée.
        if (root.dataset.tagsBadgeReady === '1') {
            return;
        }

        const list = root.querySelector('.magazine-series-tags-field__list');
        const input = root.querySelector('.magazine-series-tags-field__input');
        const addBtn = root.querySelector('.magazine-series-tags-field__add-btn');
        const inputName = root.getAttribute('data-tags-input-name') || 'tags[]';
        if (!list) {
            return;
        }

        root.dataset.tagsBadgeReady = '1';

        const escapeAttrSelector = (value) => {
            if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
                return CSS.escape(value);
            }
            return String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
        };

        const collectKeys = () => new Set(
            [...list.querySelectorAll(`input[name="${escapeAttrSelector(inputName)}"]`)]
                .map((field) => field.value.trim().toLowerCase())
                .filter(Boolean)
        );

        const appendTag = (label) => {
            const trimmed = label.trim();
            if (trimmed === '') {
                return;
            }

            const key = trimmed.toLowerCase();
            if (collectKeys().has(key)) {
                return;
            }

            const item = document.createElement('li');
            item.className = 'magazine-series-tags-field__item';
            item.setAttribute('role', 'listitem');

            const badge = document.createElement('span');
            badge.className = root.classList.contains('game-genre-tags-field')
                ? 'magazine-tag magazine-tag--game-genre'
                : 'magazine-tag magazine-tag--series';

            const text = document.createElement('span');
            text.className = 'magazine-series-tags-field__text';
            text.textContent = trimmed;

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'magazine-series-tags-field__remove';
            removeBtn.title = 'Retirer';
            removeBtn.setAttribute('aria-label', 'Retirer ' + trimmed);
            removeBtn.textContent = '×';

            badge.appendChild(text);
            badge.appendChild(removeBtn);

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = inputName;
            hidden.value = trimmed;

            item.appendChild(badge);
            item.appendChild(hidden);
            list.appendChild(item);
        };

        const addFromInput = () => {
            if (!input) {
                return;
            }
            const raw = input.value.trim();
            if (raw === '') {
                return;
            }

            raw.split(/[,;]+/).forEach((part) => appendTag(part));
            input.value = '';
            input.focus();
        };

        // Retrait d’un badge : écoute sur le bloc entier (pas seulement la liste),
        // pour que la croix marche même si la structure HTML varie un peu.
        root.addEventListener('click', (event) => {
            const target = event.target instanceof Element
                ? event.target
                : (event.target && event.target.parentElement);
            if (!target) {
                return;
            }

            const removeBtn = target.closest('.magazine-series-tags-field__remove');
            if (!removeBtn || !root.contains(removeBtn)) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            removeBtn.closest('.magazine-series-tags-field__item')?.remove();
        });

        if (!input || !addBtn) {
            return;
        }

        addBtn.addEventListener('click', (event) => {
            event.preventDefault();
            addFromInput();
        });

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                addFromInput();
            }
        });
    });
}

/**
 * Tags de série magazine : badges + ajout / retrait avant enregistrement du formulaire.
 */
function initMagazineSeriesTagsField() {
    document.querySelectorAll('[data-series-tags-field]').forEach((root) => {
        if (root.hasAttribute('data-tags-badge-field')) {
            return;
        }
        root.setAttribute('data-tags-badge-field', '');
        root.setAttribute('data-tags-input-name', 'tags[]');
    });
}

/** Mes magazines : filtre latéral par catégorie de série. */
function initMagazineSeriesCategoryFilter() {
    const grid = document.querySelector('[data-magazine-series-grid]');
    const stats = document.querySelector('[data-magazine-series-stats]');
    const emptyHint = document.querySelector('[data-magazine-category-empty]');
    const cards = grid ? [...grid.querySelectorAll('.magazine-series-card')] : [];

    document.querySelectorAll('[data-magazine-category-filter]').forEach((filterRoot) => {
        const filterButtons = [...filterRoot.querySelectorAll('[data-category-filter]')];

        if (filterButtons.length === 0 || cards.length === 0) {
            return;
        }

        const allButton = filterButtons.find((btn) => (btn.getAttribute('data-category-filter') || '') === '');

        const activeCategoryKeys = () => filterButtons
            .filter((btn) => {
                const key = btn.getAttribute('data-category-filter') || '';
                return key !== '' && btn.classList.contains('is-active');
            })
            .map((btn) => btn.getAttribute('data-category-filter') || '');

        const cardCategoryKeys = (card) => (card.getAttribute('data-series-categories') || '')
            .split(',')
            .map((value) => value.trim())
            .filter(Boolean);

        const syncAllButton = () => {
            if (!allButton) {
                return;
            }
            const hasActiveCategories = activeCategoryKeys().length > 0;
            allButton.classList.toggle('is-active', !hasActiveCategories);
            allButton.setAttribute('aria-pressed', hasActiveCategories ? 'false' : 'true');
        };

        const applyFilter = () => {
            const activeKeys = activeCategoryKeys();
            let visibleCount = 0;

            cards.forEach((card) => {
                const keys = cardCategoryKeys(card);
                const visible = activeKeys.length === 0
                    || activeKeys.some((activeKey) => keys.includes(activeKey));
                card.classList.toggle('is-filter-hidden', !visible);
                if (visible) {
                    card.removeAttribute('hidden');
                } else {
                    card.setAttribute('hidden', '');
                }
                if (visible) {
                    visibleCount += 1;
                }
            });

            if (stats) {
                const labelPlural = stats.dataset.statsLabel || ' en collection.';
                const labelSingle = stats.dataset.statsLabelSingle || ' en collection.';
                const visiblePlural = stats.dataset.statsVisible || ' série(s) affichée(s).';
                const visibleSingle = stats.dataset.statsVisibleSingle || ' série affichée.';
                if (activeKeys.length === 0) {
                    const suffix = cards.length > 1 ? labelPlural : labelSingle;
                    stats.textContent = `${cards.length} série${cards.length > 1 ? 's' : ''}${suffix}`;
                } else {
                    const suffix = visibleCount > 1 ? visiblePlural : visibleSingle;
                    stats.textContent = `${visibleCount}${suffix}`;
                }
            }

            if (emptyHint) {
                emptyHint.hidden = visibleCount > 0;
            }
        };

        filterButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const key = button.getAttribute('data-category-filter') || '';

                if (key === '') {
                    filterButtons.forEach((btn) => {
                        if ((btn.getAttribute('data-category-filter') || '') !== '') {
                            btn.classList.remove('is-active');
                            btn.setAttribute('aria-pressed', 'false');
                        }
                    });
                    button.classList.add('is-active');
                    button.setAttribute('aria-pressed', 'true');
                    applyFilter();
                    return;
                }

                const willActivate = !button.classList.contains('is-active');
                button.classList.toggle('is-active', willActivate);
                button.setAttribute('aria-pressed', willActivate ? 'true' : 'false');
                syncAllButton();
                applyFilter();
            });
        });

        syncAllButton();
        applyFilter();
    });
}

/** Exemplaires jeux : panneaux démat PC vs console selon les plateformes cochées. */
function getGameFormPlatformKeys(form) {
    const catalog = [...form.querySelectorAll('input[name="platforms[]"]:checked')].map((el) => el.value);
    const owned = [...form.querySelectorAll('input[name="owned_platforms[]"]:checked')].map((el) => el.value);
    if (owned.length > 0) {
        return owned;
    }
    if (catalog.length > 0) {
        return catalog;
    }
    const legacy = form.querySelector('[data-game-platform-legacy]')?.value || '';
    return legacy ? [legacy] : [];
}

/** État plateformes catalogue / exemplaire (lien catalogue ou saisie manuelle). */
function setGameCatalogPlatformState(form, platformKeys, options = {}) {
    const catalogLinked = Boolean(options.catalogLinked);
    // resetOwned = true uniquement quand on choisit un NOUVEAU jeu au catalogue.
    // Sinon on garde les cases déjà cochées (PHP / saisie utilisateur).
    const resetOwned = Boolean(options.resetOwned);
    const catalogEditOnly = form.dataset.catalogEditOnly === '1';
    const keys = new Set((platformKeys || []).filter(Boolean));
    const canManageCatalog = form.dataset.canManageCatalog === '1';

    const catalogEditBlock = form.querySelector('[data-game-catalog-edit-fields]');
    const libraryBlock = form.querySelector('[data-game-library-fields]');
    const pickHint = form.querySelector('[data-game-pick-catalog-hint]');
    const catalogBlock = form.querySelector('[data-game-catalog-platforms-fieldset]');
    const catalogLabel = form.querySelector('label[for="platform"]');
    const catalogRoot = form.querySelector('[data-field-name="platforms[]"]');
    const ownedRoot = form.querySelector('[data-field-name="owned_platforms[]"]');

    if (catalogEditOnly) {
        if (catalogEditBlock) {
            catalogEditBlock.hidden = false;
        }
        if (libraryBlock) {
            libraryBlock.hidden = true;
        }
        if (pickHint) {
            pickHint.hidden = true;
        }
        if (catalogBlock) {
            catalogBlock.hidden = false;
        }
        if (catalogLabel) {
            catalogLabel.hidden = false;
        }
        form.dataset.catalogPlatformKeys = '';
        form.dataset.catalogPlatformLinked = '0';
        form.dispatchEvent(new CustomEvent('game-platforms-changed'));
        return;
    }

    if (catalogEditBlock) {
        catalogEditBlock.hidden = catalogLinked || !canManageCatalog;
    }
    if (libraryBlock) {
        libraryBlock.hidden = !canManageCatalog && !catalogLinked;
    }
    if (pickHint) {
        pickHint.hidden = catalogLinked;
    }

    if (catalogBlock) {
        catalogBlock.hidden = catalogLinked;
    }
    if (catalogLabel) {
        catalogLabel.hidden = catalogLinked;
    }

    if (catalogRoot) {
        catalogRoot.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
            checkbox.checked = keys.has(checkbox.value);
        });
    }

    form.dataset.catalogPlatformKeys = catalogLinked ? [...keys].join(',') : '';
    form.dataset.catalogPlatformLinked = catalogLinked ? '1' : '0';

    if (ownedRoot && catalogLinked && resetOwned) {
        ownedRoot.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
            checkbox.checked = false;
        });
    }

    syncGameOwnedPlatformChoices(form);
    form.dispatchEvent(new CustomEvent('game-platforms-changed'));
}

function syncGameOwnedPlatformChoices(form) {
    const ownedRoot = form.querySelector('[data-field-name="owned_platforms[]"]');
    const catalogRoot = form.querySelector('[data-field-name="platforms[]"]');
    if (!ownedRoot) {
        return;
    }

    const linked = form.dataset.catalogPlatformLinked === '1';
    let allowed;
    if (linked) {
        allowed = new Set((form.dataset.catalogPlatformKeys || '').split(',').filter(Boolean));
    } else if (catalogRoot) {
        allowed = new Set(
            [...catalogRoot.querySelectorAll('input[type="checkbox"]:checked')].map((el) => el.value)
        );
    } else {
        return;
    }

    ownedRoot.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
        const label = checkbox.closest('label');
        const isAllowed = allowed.has(checkbox.value);
        checkbox.disabled = !isAllowed;
        if (label) {
            label.style.display = isAllowed ? '' : 'none';
            label.hidden = !isAllowed;
        }
        if (!isAllowed) {
            checkbox.checked = false;
        }
    });
}

function initGamePlatformFields() {
    document.querySelectorAll('form').forEach((form) => {
        const catalogRoot = form.querySelector('[data-field-name="platforms[]"]');
        const ownedRoot = form.querySelector('[data-field-name="owned_platforms[]"]');
        if (!ownedRoot) {
            return;
        }
        if (!catalogRoot) {
            if (form.dataset.gameLibraryEditForm === '1') {
                const keys = (form.dataset.catalogPlatformKeys || '').split(',').filter(Boolean);
                setGameCatalogPlatformState(form, keys, { catalogLinked: true });
            }
            return;
        }

        catalogRoot.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                syncGameOwnedPlatformChoices(form);
                form.dispatchEvent(new CustomEvent('game-platforms-changed'));
            });
        });

        syncGameOwnedPlatformChoices(form);
    });
}

function initGameEditionFields() {
    const consoleStoreLabels = {
        ps5: 'PlayStation Store',
        ps4: 'PlayStation Store',
        xbox_series: 'Microsoft Store / Xbox',
        xbox_one: 'Microsoft Store / Xbox',
        switch: 'Nintendo eShop',
        switch2: 'Nintendo eShop',
    };

    document.querySelectorAll('form').forEach((form) => {
        const linuxField = form.querySelector('[data-game-linux-field]');

        const refreshLinuxField = () => {
            if (!linuxField) {
                return;
            }
            const keys = getGameFormPlatformKeys(form);
            linuxField.hidden = !keys.includes('pc');
        };

        const testedBox = form.querySelector('[data-linux-tested]');
        const notSupportedBox = form.querySelector('[data-linux-not-supported]');
        const syncLinuxChecks = (changed) => {
            if (!testedBox || !notSupportedBox) {
                return;
            }
            if (changed === testedBox && testedBox.checked) {
                notSupportedBox.checked = false;
            }
            if (changed === notSupportedBox && notSupportedBox.checked) {
                testedBox.checked = false;
            }
        };

        testedBox?.addEventListener('change', () => syncLinuxChecks(testedBox));
        notSupportedBox?.addEventListener('change', () => syncLinuxChecks(notSupportedBox));

        form.addEventListener('game-platforms-changed', refreshLinuxField);
        form.querySelectorAll('input[name="owned_platforms[]"]').forEach((el) => {
            el.addEventListener('change', refreshLinuxField);
        });
        refreshLinuxField();
    });

    document.querySelectorAll('[data-game-editions-root]').forEach((root) => {
        const form = root.closest('form');
        const digitalToggle = root.querySelector('[data-game-digital-toggle]');
        const pcPanel = root.querySelector('[data-game-digital-pc]');
        const consolePanel = root.querySelector('[data-game-digital-console]');
        const consoleLabel = root.querySelector('[data-game-console-store-label]');

        const refresh = () => {
            const keys = form ? getGameFormPlatformKeys(form) : [];
            const digitalOn = Boolean(digitalToggle?.checked);
            const isPc = keys.includes('pc');
            const consoleKey = keys.find((key) => Object.prototype.hasOwnProperty.call(consoleStoreLabels, key));

            if (pcPanel) {
                pcPanel.hidden = !(digitalOn && isPc);
            }
            if (consolePanel) {
                consolePanel.hidden = !(digitalOn && consoleKey);
            }
            if (consoleLabel && consoleKey) {
                consoleLabel.textContent = consoleStoreLabels[consoleKey] || '—';
            }
        };

        form?.addEventListener('game-platforms-changed', refresh);
        form?.querySelectorAll('input[name="owned_platforms[]"]').forEach((el) => {
            el.addEventListener('change', refresh);
        });
        digitalToggle?.addEventListener('change', refresh);
        refresh();
    });
}

/** Extensions / remakes jeux : checkbox + autocomplétion catalogue lié. */
function initGameRelationFields() {
    document.querySelectorAll('form').forEach((form) => {
        const catalogUrl = form.getAttribute('data-game-catalog-url') || '/rechercher-jeux-catalogue.php';

        const configs = [
            {
                root: form.querySelector('[data-game-extension-root]'),
                toggle: '[data-game-extension-toggle]',
                panel: '[data-game-extension-panel]',
                search: '[data-game-extension-search]',
                list: '[data-game-extension-list]',
                oeuvreId: '[data-game-extension-oeuvre-id]',
                clearBtn: '[data-game-extension-clear]',
                hint: '[data-game-extension-hint], #base_game_hint',
                hintLabel: '[data-game-extension-hint-label], #base_game_hint_label',
                oppositeToggle: '[data-game-remake-toggle]',
                oppositePanel: '[data-game-remake-panel]',
            },
            {
                root: form.querySelector('[data-game-remake-root]'),
                toggle: '[data-game-remake-toggle]',
                panel: '[data-game-remake-panel]',
                search: '[data-game-remake-search]',
                list: '[data-game-remake-list]',
                oeuvreId: '[data-game-remake-oeuvre-id]',
                clearBtn: '[data-game-remake-clear]',
                hint: '[data-game-remake-hint], #original_game_hint',
                hintLabel: '[data-game-remake-hint-label], #original_game_hint_label',
                oppositeToggle: '[data-game-extension-toggle]',
                oppositePanel: '[data-game-extension-panel]',
            },
        ];

        configs.forEach((cfg) => {
            if (!cfg.root) {
                return;
            }

            const toggle = cfg.root.querySelector(cfg.toggle);
            const panel = cfg.root.querySelector(cfg.panel);
            const input = cfg.root.querySelector(cfg.search);
            const list = cfg.root.querySelector(cfg.list);
            const oeuvreIdInput = cfg.root.querySelector(cfg.oeuvreId);
            const clearBtn = cfg.root.querySelector(cfg.clearBtn);
            const hint = cfg.root.querySelector(cfg.hint) || form.querySelector(cfg.hint);
            const hintLabel = cfg.root.querySelector(cfg.hintLabel) || form.querySelector(cfg.hintLabel);

            if (!toggle || !panel || !input || !list || !oeuvreIdInput) {
                return;
            }

            let debounceTimer = null;

            const closeList = () => {
                list.hidden = true;
                list.innerHTML = '';
            };

            const setHint = (label) => {
                if (!hint || !hintLabel) {
                    return;
                }
                const text = String(label || '').trim();
                hintLabel.textContent = text;
                hint.hidden = text === '';
            };

            const clearSelection = () => {
                oeuvreIdInput.value = '';
                setHint('');
            };

            const uncheckOpposite = () => {
                const opposite = form.querySelector(cfg.oppositeToggle);
                const oppositePanelEl = form.querySelector(cfg.oppositePanel);
                if (opposite instanceof HTMLInputElement && opposite.checked) {
                    opposite.checked = false;
                }
                if (oppositePanelEl) {
                    oppositePanelEl.hidden = true;
                }
            };

            const refreshPanel = () => {
                const on = Boolean(toggle.checked);
                panel.hidden = !on;
                if (on) {
                    uncheckOpposite();
                } else {
                    closeList();
                    clearSelection();
                }
            };

            const render = (items) => {
                list.innerHTML = '';
                if (!Array.isArray(items) || items.length === 0) {
                    closeList();
                    return;
                }
                items.slice(0, 12).forEach((item) => {
                    const titre = item.display_label || item.titre || '';
                    const el = document.createElement('div');
                    el.className = 'catalog-title-autocomplete__option catalog-title-autocomplete__option--game';
                    el.setAttribute('role', 'option');
                    const label = document.createElement('span');
                    label.className = 'catalog-title-autocomplete__option-label';
                    label.textContent = titre;
                    el.appendChild(label);
                    el.addEventListener('mousedown', (event) => {
                        event.preventDefault();
                        input.value = item.titre || item.display_label || '';
                        oeuvreIdInput.value = String(item.oeuvre_id || '');
                        setHint(item.display_label || item.titre || '');
                        closeList();
                        input.focus();
                    });
                    list.appendChild(el);
                });
                list.hidden = false;
            };

            const fetchSuggestions = async () => {
                const q = String(input.value || '').trim();
                if (q.length < 2) {
                    closeList();
                    return;
                }
                try {
                    const url = catalogUrl + (catalogUrl.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(q);
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) {
                        closeList();
                        return;
                    }
                    const data = await res.json();
                    render(data.results || []);
                } catch {
                    closeList();
                }
            };

            toggle.addEventListener('change', refreshPanel);
            clearBtn?.addEventListener('click', () => {
                input.value = '';
                clearSelection();
                closeList();
                input.focus();
            });

            input.addEventListener('input', () => {
                clearSelection();
                closeList();
                window.clearTimeout(debounceTimer);
                debounceTimer = window.setTimeout(fetchSuggestions, 180);
            });

            input.addEventListener('blur', () => {
                window.setTimeout(() => closeList(), 150);
            });

            refreshPanel();
            if (String(oeuvreIdInput.value || '').trim() !== '') {
                setHint(input.value);
            }
        });
    });
}

/** Vue bibliothèque jeux : vignette au survol (position fixe, hors zone scroll). */
function initGameShelfHoverPreviews() {
    const margin = 8;
    const gap = 10;

    document.querySelectorAll('.game-shelf__card').forEach((card) => {
        const preview = card.querySelector('.game-shelf__preview');
        const spine = card.querySelector('.game-shelf__link');
        if (!preview || !spine) {
            return;
        }

        const placePreview = () => {
            preview.style.left = '-9999px';
            preview.style.top = '0';
            preview.classList.add('is-visible');
            preview.setAttribute('aria-hidden', 'false');

            const spineRect = spine.getBoundingClientRect();
            const previewWidth = preview.offsetWidth || 148;
            const previewHeight = preview.offsetHeight || 280;

            let top = spineRect.top - previewHeight - gap;
            let left = spineRect.left + spineRect.width / 2 - previewWidth / 2;

            left = Math.max(margin, Math.min(left, window.innerWidth - previewWidth - margin));

            if (top < margin) {
                top = spineRect.bottom + gap;
            }

            preview.style.left = `${Math.round(left)}px`;
            preview.style.top = `${Math.round(top)}px`;
        };

        const hidePreview = () => {
            preview.classList.remove('is-visible');
            preview.setAttribute('aria-hidden', 'true');
            preview.style.left = '';
            preview.style.top = '';
        };

        card.addEventListener('mouseenter', placePreview);
        card.addEventListener('mouseleave', hidePreview);
        card.addEventListener('focusin', placePreview);
        card.addEventListener('focusout', (event) => {
            if (!card.contains(event.relatedTarget)) {
                hidePreview();
            }
        });

        window.addEventListener('scroll', hidePreview, { passive: true });
        window.addEventListener('resize', hidePreview);
    });
}

/** Attache une bulle d’infos flottante à une tuile (films, jeux, magazines…). */
function attachGridHoverBubble(card, bubble, anchor) {
    if (!bubble || !anchor) {
        return;
    }

    const margin = 8;
    const gap = 10;

    const placeBubble = () => {
        bubble.style.left = '-9999px';
        bubble.style.top = '0';
        bubble.classList.add('is-visible');
        bubble.setAttribute('aria-hidden', 'false');

        const anchorRect = anchor.getBoundingClientRect();
        const bubbleWidth = bubble.offsetWidth || 220;
        const bubbleHeight = bubble.offsetHeight || 120;

        let top = anchorRect.top - bubbleHeight - gap;
        let left = anchorRect.left + anchorRect.width / 2 - bubbleWidth / 2;

        left = Math.max(margin, Math.min(left, window.innerWidth - bubbleWidth - margin));

        if (top < margin) {
            top = anchorRect.bottom + gap;
        }

        bubble.style.left = `${Math.round(left)}px`;
        bubble.style.top = `${Math.round(top)}px`;
    };

    const hideBubble = () => {
        bubble.classList.remove('is-visible');
        bubble.setAttribute('aria-hidden', 'true');
        bubble.style.left = '';
        bubble.style.top = '';
    };

    card.addEventListener('mouseenter', placeBubble);
    card.addEventListener('mouseleave', hideBubble);
    card.addEventListener('focusin', placeBubble);
    card.addEventListener('focusout', (event) => {
        if (!card.contains(event.relatedTarget)) {
            hideBubble();
        }
    });

    window.addEventListener('scroll', hideBubble, { passive: true });
    window.addEventListener('resize', hideBubble);
}

/** Vue vignettes films / jeux / magazines : bulle d’infos au survol de l’affiche. */
function initCollectionGridHoverBubbles() {
    const bubbleTargets = [
        {
            cardSelector: '.collection-grid--poster-only .collection-grid__card',
            bubbleSelector: '.collection-grid__hover-bubble',
            anchorSelector: '.collection-grid__link',
        },
        {
            cardSelector: '.magazine-series-grid--poster-only .magazine-series-card',
            bubbleSelector: '.collection-grid__hover-bubble',
            anchorSelector: '.magazine-series-card__link',
        },
        {
            cardSelector: '.magazine-issues-grid--compact .magazine-issue-card',
            bubbleSelector: '.collection-grid__hover-bubble',
            anchorSelector: '.magazine-issue-card__cover-link',
        },
    ];

    bubbleTargets.forEach(({ cardSelector, bubbleSelector, anchorSelector }) => {
        document.querySelectorAll(cardSelector).forEach((card) => {
            attachGridHoverBubble(
                card,
                card.querySelector(bubbleSelector),
                card.querySelector(anchorSelector)
            );
        });
    });
}

/**
 * Bulles informatives au survol des vignettes sujets / tests (fiche numéro magazine).
 */
function initMagazineSubjectStripHoverBubbles() {
    const margin = 8;
    const gap = 10;

    document.querySelectorAll('.magazine-subject-strip__card').forEach((card) => {
        const bubble = card.querySelector('.magazine-subject-strip__bubble');
        const anchor = card.querySelector('.magazine-subject-strip__link');
        if (!bubble || !anchor) {
            return;
        }

        const placeBubble = () => {
            bubble.style.left = '-9999px';
            bubble.style.top = '0';
            bubble.classList.add('is-visible');
            bubble.setAttribute('aria-hidden', 'false');

            const anchorRect = anchor.getBoundingClientRect();
            const bubbleWidth = bubble.offsetWidth || 200;
            const bubbleHeight = bubble.offsetHeight || 100;

            let top = anchorRect.top - bubbleHeight - gap;
            let left = anchorRect.left + anchorRect.width / 2 - bubbleWidth / 2;

            left = Math.max(margin, Math.min(left, window.innerWidth - bubbleWidth - margin));

            if (top < margin) {
                top = anchorRect.bottom + gap;
            }

            bubble.style.left = `${Math.round(left)}px`;
            bubble.style.top = `${Math.round(top)}px`;
        };

        const hideBubble = () => {
            bubble.classList.remove('is-visible');
            bubble.setAttribute('aria-hidden', 'true');
            bubble.style.left = '';
            bubble.style.top = '';
        };

        card.addEventListener('mouseenter', placeBubble);
        card.addEventListener('mouseleave', hideBubble);
        card.addEventListener('focusin', placeBubble);
        card.addEventListener('focusout', (event) => {
            if (!card.contains(event.relatedTarget)) {
                hideBubble();
            }
        });

        const strip = card.closest('.magazine-subject-strip__list');
        if (strip) {
            strip.addEventListener('scroll', hideBubble, { passive: true });
        }

        window.addEventListener('scroll', hideBubble, { passive: true });
        window.addEventListener('resize', hideBubble);
    });
}

/**
 * Autocomplétion — séries magazines du catalogue (ajout à la bibliothèque).
 */
function initMagazineSeriesCatalogAutocomplete() {
    document.querySelectorAll('[data-magazine-series-catalog-autocomplete]').forEach((root) => {
        const input = root.querySelector('.catalog-title-autocomplete__input');
        const list = root.querySelector('.catalog-title-autocomplete__list');
        const seriesIdInput = document.getElementById(root.dataset.seriesIdInput || 'catalog_series_id');
        const submitBtn = document.getElementById('catalog_series_submit');
        const hintEl = document.getElementById('catalog_series_hint');
        const searchUrl = root.getAttribute('data-search-url') || '/rechercher-series-catalogue.php';

        if (!input || !list || !seriesIdInput) {
            return;
        }

        let closeList = () => {};

        const clearSelection = () => {
            seriesIdInput.value = '';
            if (submitBtn) {
                submitBtn.disabled = true;
            }
            if (hintEl) {
                hintEl.hidden = true;
                hintEl.textContent = '';
            }
        };

        const applySelection = (item) => {
            if (!item) {
                return;
            }
            seriesIdInput.value = String(item.series_id || '');
            input.value = item.titre || '';
            closeList();
            if (submitBtn) {
                submitBtn.disabled = false;
            }
            if (hintEl) {
                const count = Number(item.catalog_issue_count || 0);
                let hint = count > 0
                    ? count + ' numéro(s) au catalogue partagé.'
                    : 'Série au catalogue (aucun numéro référencé pour l’instant).';
                if (item.in_collection) {
                    hint += ' Déjà dans vos magazines — vous pouvez quand même valider pour ouvrir la série.';
                }
                hintEl.textContent = hint;
                hintEl.hidden = false;
            }
        };

        ({ closeList } = attachCatalogAutocomplete({
            root,
            input,
            list,
            searchUrl,
            optionIdPrefix: 'mag-series-opt',
            minChars: 1,
            debounceMs: 220,
            dismissOn: 'blur',
            keyboardRequiresVisibleList: false,
            onInputClear: clearSelection,
            onSelect: applySelection,
            buildOption: (item, index, onSelect) => createCatalogAutocompleteOption({
                item,
                index,
                optionIdPrefix: 'mag-series-opt',
                label: item.display_label || item.titre || '',
                badgeText: item.in_collection ? 'Déjà suivie' : null,
                onSelect,
            }),
        }));
    });
}

/**
 * Autocomplétion — numéros catalogue d’une série (ajout à la bibliothèque).
 */
function initMagazineIssueCatalogAutocomplete() {
    document.querySelectorAll('[data-magazine-issue-catalog-autocomplete]').forEach((root) => {
        const input = root.querySelector('.catalog-title-autocomplete__input');
        const list = root.querySelector('.catalog-title-autocomplete__list');
        const oeuvreIdInput = document.getElementById(root.dataset.oeuvreIdInput || 'catalog_issue_oeuvre_id');
        const numeroInput = document.getElementById(root.dataset.numeroInput || 'numero');
        const dateInput = document.getElementById(root.dataset.dateInput || 'date_parution');
        const ordreInput = document.getElementById(root.dataset.ordreInput || 'numero_ordre');
        const horsSerieInput = document.getElementById(root.dataset.horsSerieInput || 'est_hors_serie');
        const hintEl = document.getElementById(root.dataset.hintId || 'catalog_issue_hint');
        const searchUrl = root.getAttribute('data-search-url') || '/rechercher-numeros-catalogue.php';

        if (!input || !list || !oeuvreIdInput) {
            return;
        }

        let closeList = () => {};

        const clearSelection = () => {
            oeuvreIdInput.value = '';
            if (hintEl) {
                hintEl.hidden = true;
                hintEl.textContent = '';
            }
        };

        const applySelection = (item) => {
            if (!item) {
                return;
            }
            oeuvreIdInput.value = String(item.oeuvre_id || '');
            if (numeroInput) {
                numeroInput.value = item.numero || '';
            }
            if (dateInput && item.date_parution) {
                dateInput.value = item.date_parution;
            }
            if (ordreInput && item.numero_ordre) {
                ordreInput.value = String(item.numero_ordre);
            }
            if (horsSerieInput) {
                horsSerieInput.checked = Boolean(item.est_hors_serie);
            }
            closeList();
            if (hintEl) {
                let hint = 'Numéro sélectionné au catalogue partagé.';
                if (item.in_library) {
                    hint += ' Déjà dans votre bibliothèque — vous pouvez quand même enregistrer (papier, PDF…).';
                }
                hintEl.textContent = hint;
                hintEl.hidden = false;
            }
        };

        ({ closeList } = attachCatalogAutocomplete({
            root,
            input,
            list,
            searchUrl,
            optionIdPrefix: 'mag-issue-opt',
            minChars: 1,
            debounceMs: 220,
            dismissOn: 'blur',
            keyboardRequiresVisibleList: false,
            onInputClear: clearSelection,
            onSelect: applySelection,
            buildOption: (item, index, onSelect) => createCatalogAutocompleteOption({
                item,
                index,
                optionIdPrefix: 'mag-issue-opt',
                label: item.display_label || item.numero || '',
                badgeText: item.in_library ? 'Déjà ajouté' : null,
                onSelect,
            }),
        }));
    });
}

/** Import Steam : autocomplétion pour lier un AppID à une fiche catalogue. */
function initSteamImportMapping() {
    const page = document.querySelector('[data-steam-import-page]');
    if (!page) {
        return;
    }

    const searchUrl = page.getAttribute('data-catalog-search-url') || '/rechercher-jeux-catalogue.php';

    page.querySelectorAll('[data-steam-map-root]').forEach((root) => {
        const form = root.querySelector('.steam-map-form');
        const input = root.querySelector('[data-steam-map-search]');
        const list = root.querySelector('[data-steam-map-list]');
        const oeuvreIdInput = root.querySelector('[data-steam-map-oeuvre-id]');
        const hint = root.querySelector('[data-steam-map-hint]');

        if (!form || !input || !list || !oeuvreIdInput) {
            return;
        }

        attachCatalogAutocomplete({
            root,
            input,
            list,
            searchUrl,
            optionIdPrefix: 'steam-map-opt',
            minChars: 2,
            onInputClear: () => {
                oeuvreIdInput.value = '';
                if (hint) {
                    hint.textContent = '';
                    hint.hidden = true;
                }
            },
            onSelect: (item) => {
                input.value = item.titre || item.display_label || '';
                oeuvreIdInput.value = String(item.oeuvre_id || '');
                if (hint) {
                    hint.textContent = item.display_label || item.titre || '';
                    hint.hidden = (hint.textContent || '').trim() === '';
                }
            },
            buildOption: (item, index, onSelect) => createCatalogAutocompleteOption({
                item,
                index,
                optionIdPrefix: 'steam-map-opt',
                label: item.display_label || item.titre || '',
                onSelect,
            }),
        });

        form.addEventListener('submit', (event) => {
            if (!String(oeuvreIdInput.value || '').trim()) {
                event.preventDefault();
                input.focus();
                if (hint) {
                    hint.textContent = 'Choisissez un jeu dans la liste de suggestions.';
                    hint.hidden = false;
                }
            }
        });
    });
}

/** Fiche catalogue : fusion manuelle avec autocomplétion. */
function initCatalogOeuvreMerge() {
    document.querySelectorAll('[data-catalog-oeuvre-merge]').forEach((root) => {
        const form = root.querySelector('[data-catalog-oeuvre-merge-form]');
        const input = root.querySelector('[data-catalog-merge-search]');
        const list = root.querySelector('[data-catalog-merge-list]');
        const keepIdInput = root.querySelector('[data-catalog-merge-keep-id]');
        const removeIdInput = root.querySelector('[data-catalog-merge-remove-id]');
        const otherIdInput = root.querySelector('[data-catalog-merge-other-id]');
        const hint = root.querySelector('[data-catalog-merge-hint]');
        const directionRadios = root.querySelectorAll('[data-catalog-merge-direction]');
        const submitBtn = root.querySelector('[data-catalog-merge-submit]');
        const currentOeuvreId = parseInt(root.getAttribute('data-current-oeuvre-id') || '0', 10);
        const searchUrl = root.getAttribute('data-catalog-search-url') || '/rechercher-oeuvres.php';

        if (!form || !input || !list || !keepIdInput || !removeIdInput || !otherIdInput || currentOeuvreId <= 0) {
            return;
        }

        const resolveOeuvreId = (item) => {
            const raw = item?.oeuvre_id ?? item?.id ?? 0;
            const parsed = parseInt(String(raw), 10);
            return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
        };

        const resolveLabel = (item) => item?.display_label || item?.label || item?.titre || '';

        const syncMergeIds = () => {
            const otherId = parseInt(String(otherIdInput.value || ''), 10);
            const keepOther = root.querySelector('[data-catalog-merge-direction][value="keep_other"]')?.checked === true;

            if (!Number.isFinite(otherId) || otherId <= 0) {
                keepIdInput.value = keepOther ? '' : String(currentOeuvreId);
                removeIdInput.value = keepOther ? String(currentOeuvreId) : '';
                return;
            }

            if (keepOther) {
                keepIdInput.value = String(otherId);
                removeIdInput.value = String(currentOeuvreId);
            } else {
                keepIdInput.value = String(currentOeuvreId);
                removeIdInput.value = String(otherId);
            }
        };

        const clearSelection = () => {
            otherIdInput.value = '';
            if (hint) {
                hint.textContent = '';
                hint.hidden = true;
            }
            syncMergeIds();
        };

        attachCatalogAutocomplete({
            root,
            input,
            list,
            searchUrl,
            optionIdPrefix: 'catalog-merge-opt',
            minChars: 2,
            onInputClear: clearSelection,
            onSelect: (item) => {
                const oeuvreId = resolveOeuvreId(item);
                if (oeuvreId <= 0 || oeuvreId === currentOeuvreId) {
                    clearSelection();
                    input.value = '';
                    if (hint) {
                        hint.textContent = oeuvreId === currentOeuvreId
                            ? 'Choisissez une fiche différente de celle-ci.'
                            : 'Sélection invalide.';
                        hint.hidden = false;
                    }
                    return;
                }

                input.value = item.titre || resolveLabel(item);
                otherIdInput.value = String(oeuvreId);
                syncMergeIds();
                if (hint) {
                    hint.textContent = resolveLabel(item);
                    hint.hidden = (hint.textContent || '').trim() === '';
                }
            },
            buildOption: (item, index, onSelect) => {
                const oeuvreId = resolveOeuvreId(item);
                if (oeuvreId === currentOeuvreId) {
                    return document.createDocumentFragment();
                }

                return createCatalogAutocompleteOption({
                    item,
                    index,
                    optionIdPrefix: 'catalog-merge-opt',
                    label: resolveLabel(item),
                    onSelect,
                });
            },
        });

        directionRadios.forEach((radio) => {
            radio.addEventListener('change', syncMergeIds);
        });

        form.addEventListener('submit', (event) => {
            syncMergeIds();
            const otherId = parseInt(String(otherIdInput.value || ''), 10);
            if (!Number.isFinite(otherId) || otherId <= 0) {
                event.preventDefault();
                input.focus();
                if (hint) {
                    hint.textContent = 'Choisissez une fiche dans la liste de suggestions.';
                    hint.hidden = false;
                }
                return;
            }

            if (otherId === currentOeuvreId) {
                event.preventDefault();
                if (hint) {
                    hint.textContent = 'Choisissez une fiche différente de celle-ci.';
                    hint.hidden = false;
                }
                return;
            }

            const keepOther = root.querySelector('[data-catalog-merge-direction][value="keep_other"]')?.checked === true;
            const confirmText = keepOther
                ? 'Fusionner cette fiche dans l’autre ? Cette fiche sera supprimée du catalogue.'
                : 'Fusionner l’autre fiche dans celle-ci ? L’autre fiche sera supprimée du catalogue.';

            if (!window.confirm(confirmText)) {
                event.preventDefault();
            }
        });

        if (submitBtn) {
            submitBtn.addEventListener('click', () => {
                syncMergeIds();
            });
        }

        syncMergeIds();
    });
}

/** Fiche jeu bibliothèque : initialiser les plateformes autorisées pour « mon exemplaire ». */
function initGameLibraryEditForms() {
    document.querySelectorAll('[data-game-library-edit-form="1"]').forEach((form) => {
        const keys = (form.getAttribute('data-catalog-platform-keys') || '').split(',').filter(Boolean);
        setGameCatalogPlatformState(form, keys, { catalogLinked: true });
    });
}

/** Fiches média : actions rapides sous la jaquette (bulles au clic). */
function initGameDetailQuickActions() {
    const roots = document.querySelectorAll('[data-detail-actions]');
    if (roots.length === 0) {
        return;
    }

    const closeAllRoots = () => {
        roots.forEach((root) => {
            root.querySelectorAll('[data-detail-popover]').forEach((popover) => {
                popover.hidden = true;
                popover.style.left = '';
                popover.style.top = '';
                popover.style.visibility = '';
            });
            root.querySelectorAll('[data-detail-action-anchor]').forEach((anchor) => {
                anchor.classList.remove('is-open');
                const btn = anchor.querySelector('[data-detail-action]');
                if (btn) {
                    btn.setAttribute('aria-expanded', 'false');
                }
            });
        });
    };

    roots.forEach((root) => {
        initDetailQuickActionsRoot(root, closeAllRoots);
    });

    document.addEventListener('click', (event) => {
        let shouldClose = false;
        roots.forEach((root) => {
            if (!root.contains(event.target)) {
                const openPopoverEl = root.querySelector('[data-detail-popover]:not([hidden])');
                if (openPopoverEl && !openPopoverEl.contains(event.target)) {
                    shouldClose = true;
                }
            }
        });
        if (shouldClose) {
            closeAllRoots();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllRoots();
        }
    });
}

function initDetailQuickActionsRoot(root, closeAllRoots) {
    const actionButtonSelector = '[data-detail-action]';
    const anchorSelector = (action) => `[data-detail-action-anchor="${action}"]`;
    const popoverSelector = (action) => `[data-detail-popover="${action}"]`;

    const positionPopover = (anchor, popover) => {
        const button = anchor.querySelector(actionButtonSelector);
        const panel = popover.querySelector('.game-action-popover__panel');
        if (!button || !panel) {
            return;
        }

        popover.hidden = false;
        popover.style.visibility = 'hidden';
        popover.style.left = '0';
        popover.style.top = '0';

        const buttonRect = button.getBoundingClientRect();
        const panelRect = panel.getBoundingClientRect();
        const margin = 12;
        const gap = 8;
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;

        let left = buttonRect.right + gap;
        if (left + panelRect.width > viewportWidth - margin) {
            left = Math.max(margin, viewportWidth - panelRect.width - margin);
        }

        let top = buttonRect.top + (buttonRect.height / 2) - (panelRect.height / 2);
        top = Math.max(margin, Math.min(top, viewportHeight - panelRect.height - margin));

        popover.style.left = `${Math.round(left)}px`;
        popover.style.top = `${Math.round(top)}px`;
        popover.style.visibility = '';
    };

    const closeAll = () => {
        if (typeof closeAllRoots === 'function') {
            closeAllRoots();
            return;
        }
        root.querySelectorAll('[data-detail-popover]').forEach((popover) => {
            popover.hidden = true;
            popover.style.left = '';
            popover.style.top = '';
            popover.style.visibility = '';
        });
        root.querySelectorAll('[data-detail-action-anchor]').forEach((anchor) => {
            anchor.classList.remove('is-open');
            const btn = anchor.querySelector(actionButtonSelector);
            if (btn) {
                btn.setAttribute('aria-expanded', 'false');
            }
        });
    };

    const openPopover = (action) => {
        const anchor = root.querySelector(anchorSelector(action));
        const popover = anchor?.querySelector(popoverSelector(action));
        if (!anchor || !popover) {
            return;
        }
        closeAll();
        anchor.classList.add('is-open');
        const btn = anchor.querySelector(actionButtonSelector);
        if (btn) {
            btn.setAttribute('aria-expanded', 'true');
        }
        positionPopover(anchor, popover);
        const focusable = popover.querySelector(
            'input:not([type="hidden"]), select, textarea, button:not([type="submit"])'
        );
        if (focusable && typeof focusable.focus === 'function') {
            focusable.focus();
        }
    };

    root.querySelectorAll(actionButtonSelector).forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.stopPropagation();
            const action = btn.getAttribute('data-detail-action') || '';
            const anchor = btn.closest('[data-detail-action-anchor]');
            const popover = anchor?.querySelector(`[data-detail-popover="${action}"]`);
            if (popover && !popover.hidden) {
                closeAll();
                return;
            }
            openPopover(action);
        });
    });

    window.addEventListener('resize', () => {
        const openAnchor = root.querySelector('[data-detail-action-anchor].is-open');
        const openPopoverEl = openAnchor?.querySelector('[data-detail-popover]:not([hidden])');
        if (openAnchor && openPopoverEl && !openPopoverEl.hidden) {
            positionPopover(openAnchor, openPopoverEl);
        }
    });

    const openOnLoad = root.getAttribute('data-popover-open');
    if (openOnLoad) {
        openPopover(openOnLoad);
    }
}

/** Barre de recherche globale (bibliothèque + catalogue). */
function initGlobalSearch() {
    const root = document.querySelector('[data-global-search]');
    if (!root) {
        return;
    }

    const form = root.querySelector('.global-search__form');
    const input = root.querySelector('.global-search__input');
    const panel = root.querySelector('.global-search__suggestions');
    const list = root.querySelector('.global-search__list');
    const allLink = root.querySelector('.global-search__all-link');
    const apiUrl = root.getAttribute('data-search-api') || '/rechercher-global.php';
    if (!form || !input || !panel || !list) {
        return;
    }

    let debounceTimer = null;
    let flatOptions = [];
    let activeIndex = -1;

    const closePanel = () => {
        panel.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        flatOptions = [];
        activeIndex = -1;
        list.innerHTML = '';
        if (allLink) {
            allLink.hidden = true;
        }
    };

    const setActive = (index) => {
        const options = list.querySelectorAll('.global-search__option');
        options.forEach((el, i) => {
            el.classList.toggle('is-active', i === index);
        });
        activeIndex = index;
        const activeEl = options[index];
        if (activeEl) {
            input.setAttribute('aria-activedescendant', activeEl.id);
        } else {
            input.removeAttribute('aria-activedescendant');
        }
    };

    const navigateTo = (item) => {
        if (item?.url) {
            window.location.href = item.url;
        }
    };

    const renderGroup = (title, items, startIndex) => {
        if (!Array.isArray(items) || items.length === 0) {
            return startIndex;
        }

        const heading = document.createElement('li');
        heading.className = 'global-search__group-title';
        heading.setAttribute('role', 'presentation');
        heading.textContent = title;
        list.appendChild(heading);

        items.forEach((item) => {
            const li = document.createElement('li');
            li.setAttribute('role', 'presentation');

            const link = document.createElement('a');
            link.className = 'global-search__option';
            link.href = item.url || '#';
            link.id = 'global-search-option-' + startIndex;
            link.setAttribute('role', 'option');

            const label = document.createElement('span');
            label.className = 'global-search__option-label';
            label.textContent = item.display_label || item.titre || '';
            link.appendChild(label);

            const badges = document.createElement('span');
            badges.className = 'global-search__option-badges';

            if (item.media_label) {
                const mediaBadge = document.createElement('span');
                mediaBadge.className = 'global-search__badge';
                mediaBadge.textContent = item.media_label;
                badges.appendChild(mediaBadge);
            }

            const secondary = item.statut_label || (item.source === 'catalog' ? 'Catalogue' : '');
            if (secondary) {
                const secondaryBadge = document.createElement('span');
                secondaryBadge.className = 'global-search__badge';
                secondaryBadge.textContent = secondary;
                badges.appendChild(secondaryBadge);
            }

            link.appendChild(badges);

            link.addEventListener('mousedown', (event) => {
                event.preventDefault();
                navigateTo(item);
            });

            li.appendChild(link);
            list.appendChild(li);
            flatOptions.push(item);
            startIndex += 1;
        });

        return startIndex;
    };

    const renderResults = (data) => {
        list.innerHTML = '';
        flatOptions = [];
        let index = 0;
        index = renderGroup('Ma bibliothèque', data.library || [], index);
        index = renderGroup('Catalogue', data.catalog || [], index);

        if (flatOptions.length === 0) {
            closePanel();
            return;
        }

        if (allLink) {
            const query = input.value.trim();
            allLink.href = '/recherche.php?q=' + encodeURIComponent(query);
            allLink.textContent = 'Voir tous les résultats';
            allLink.hidden = false;
        }

        panel.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        setActive(0);
    };

    const fetchResults = async () => {
        const query = input.value.trim();
        if (query.length < 2) {
            closePanel();
            return;
        }

        const separator = apiUrl.includes('?') ? '&' : '?';
        const url = apiUrl + separator + 'q=' + encodeURIComponent(query) + '&limit=6';
        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                closePanel();
                return;
            }
            const data = await response.json();
            renderResults(data);
        } catch {
            closePanel();
        }
    };

    input.addEventListener('input', () => {
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(fetchResults, 220);
    });

    input.addEventListener('focus', () => {
        if (input.value.trim().length >= 2 && flatOptions.length > 0) {
            panel.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            if (panel.hidden) {
                fetchResults();
                return;
            }
            event.preventDefault();
            if (flatOptions.length === 0) {
                return;
            }
            const next = activeIndex < flatOptions.length - 1 ? activeIndex + 1 : 0;
            setActive(next);
            return;
        }

        if (event.key === 'ArrowUp') {
            if (panel.hidden || flatOptions.length === 0) {
                return;
            }
            event.preventDefault();
            const next = activeIndex > 0 ? activeIndex - 1 : flatOptions.length - 1;
            setActive(next);
            return;
        }

        if (event.key === 'Escape') {
            closePanel();
            return;
        }

        if (event.key === 'Enter' && !panel.hidden && activeIndex >= 0 && flatOptions[activeIndex]) {
            event.preventDefault();
            navigateTo(flatOptions[activeIndex]);
        }
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            closePanel();
        }
    });

    form.addEventListener('submit', () => {
        closePanel();
    });
}
