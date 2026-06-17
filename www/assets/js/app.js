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
    initCatalogAdminCategoryFields();
    initCatalogGameTitleAutocomplete();
    initMagazineSubjectAutocompleteFields();
    initMagazineSeriesTagsField();
    initTagsBadgeFields();
    initGameEditionFields();
    initGameExtensionFields();
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

    let debounceTimer = null;
    let activeIndex = -1;
    let lastResults = [];
    const optionIdPrefix = 'game-catalog-option-' + (root.id || Math.random().toString(36).slice(2, 8));

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
        if (item.platform) {
            fillFieldById(fieldMap.platform, item.platform);
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
            li.className = 'catalog-title-autocomplete__option catalog-title-autocomplete__option--game';
            li.setAttribute('role', 'option');
            li.id = optionIdPrefix + '-' + index;
            li.dataset.index = String(index);

            const main = document.createElement('span');
            main.className = 'catalog-title-autocomplete__option-label';
            main.textContent = item.display_label ?? item.titre ?? '';

            li.appendChild(main);

            if (item.in_library) {
                const badge = document.createElement('span');
                badge.className = 'catalog-title-autocomplete__badge';
                badge.textContent = 'Déjà dans votre bibliothèque';
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
            const activeEl = document.getElementById(optionIdPrefix + '-' + activeIndex);
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
    const gameLinkCategories = new Set(['test', 'preview', 'interview']);

    document.querySelectorAll('[data-magazine-subject-autocomplete]').forEach((row) => {
        const input = row.querySelector('input[type="search"], input[type="text"]');
        const list = row.querySelector('[role="listbox"]');
        const searchUrl = row.getAttribute('data-search-url') || '/rechercher-sujets-magazine.php';
        const mode = row.getAttribute('data-magazine-subject-autocomplete') || 'navigate';
        const form = row.closest('form');
        const categorySelect = form
            ? form.querySelector('#subject_category, #attach_category')
            : document.getElementById('subject_category');
        const gameCatalogUrl = form?.getAttribute('data-game-catalog-url') || '';
        const catalogInput = form?.querySelector('#attach_catalog_oeuvre_id');
        const gameHint = form?.querySelector('#attach_game_catalog_hint');
        const gameHintLabel = form?.querySelector('#attach_game_catalog_label');
        const clearGameBtn = form?.querySelector('#attach_clear_game_catalog');

        if (!input || !list) {
            return;
        }

        let debounceTimer = null;
        let linkedGameLabel = '';

        const supportsGameCatalog = () => (
            gameCatalogUrl !== ''
            && categorySelect
            && gameLinkCategories.has(categorySelect.value)
        );

        const closeList = () => {
            list.hidden = true;
            list.innerHTML = '';
        };

        const clearGameCatalogLink = () => {
            linkedGameLabel = '';
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

        const showGameCatalogLink = (label) => {
            linkedGameLabel = label || '';
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

        const applyGameCatalogSelection = (item) => {
            input.value = item.titre || item.display_label || '';
            linkedGameLabel = input.value.trim();
            if (catalogInput) {
                catalogInput.value = String(item.oeuvre_id || '');
            }
            setParutionYear(item.annee);

            const detailField = form ? form.querySelector('#attach_detail') : null;
            if (detailField && (item.platform_short || item.platform_label)) {
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

            showGameCatalogLink(item.display_label || item.titre || '');
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
                    showGameCatalogLink(item.display_label || item.label || '');
                } else {
                    clearGameCatalogLink();
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
                const isGameCatalog = item.source === 'game_catalog';
                li.className = 'catalog-title-autocomplete__option'
                    + (isGameCatalog ? ' catalog-title-autocomplete__option--game' : '');
                li.setAttribute('role', 'option');

                const main = document.createElement('span');
                main.className = 'catalog-title-autocomplete__option-label';
                main.textContent = item.display_label || item.label || item.titre || '';

                const meta = document.createElement('span');
                meta.className = 'hint';
                if (isGameCatalog) {
                    meta.textContent = 'Catalogue jeux'
                        + (item.in_library ? ' · dans votre bibliothèque' : '');
                } else {
                    meta.textContent = (item.category_label || '')
                        + (item.issue_count ? ' · ' + item.issue_count + ' num.' : '');
                }

                li.appendChild(main);
                li.appendChild(meta);

                li.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    if (isGameCatalog) {
                        applyGameCatalogSelection(item);
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

            if (supportsGameCatalog()) {
                requests.push(
                    fetch(gameCatalogUrl + '?' + new URLSearchParams({ q }).toString(), {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    }).then((response) => response.json())
                );
            }

            Promise.all(requests)
                .then((payloads) => {
                    const subjectResults = Array.isArray(payloads[0]?.results) ? payloads[0].results : [];
                    const gameResults = payloads.length > 1 && Array.isArray(payloads[1]?.results)
                        ? payloads[1].results
                        : [];
                    renderResults([...gameResults, ...subjectResults]);
                })
                .catch(() => closeList());
        };

        input.addEventListener('input', () => {
            if (catalogInput && linkedGameLabel !== '' && input.value.trim() !== linkedGameLabel.trim()) {
                clearGameCatalogLink();
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
                if (!supportsGameCatalog()) {
                    clearGameCatalogLink();
                } else if (input.value.trim().length >= 2) {
                    fetchResults();
                }
            });
        }

        clearGameBtn?.addEventListener('click', () => {
            clearGameCatalogLink();
            input.focus();
        });
    });
}

/**
 * Champs tags / genres en badges (magazines, jeux…).
 */
function initTagsBadgeFields() {
    document.querySelectorAll('[data-tags-badge-field]').forEach((root) => {
        const list = root.querySelector('.magazine-series-tags-field__list');
        const input = root.querySelector('.magazine-series-tags-field__input');
        const addBtn = root.querySelector('.magazine-series-tags-field__add-btn');
        const inputName = root.getAttribute('data-tags-input-name') || 'tags[]';
        if (!list || !input || !addBtn) {
            return;
        }

        const collectKeys = () => new Set(
            [...list.querySelectorAll(`input[name="${CSS.escape(inputName)}"]`)]
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
            const raw = input.value.trim();
            if (raw === '') {
                return;
            }

            raw.split(/[,;]+/).forEach((part) => appendTag(part));
            input.value = '';
            input.focus();
        };

        addBtn.addEventListener('click', addFromInput);

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                addFromInput();
            }
        });

        list.addEventListener('click', (event) => {
            const removeBtn = event.target.closest('.magazine-series-tags-field__remove');
            if (!removeBtn) {
                return;
            }
            removeBtn.closest('.magazine-series-tags-field__item')?.remove();
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

/** Exemplaires jeux : panneaux démat PC vs console selon la plateforme. */
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
        const platformSelect = form.querySelector('[data-game-platform-select]');
        const linuxField = form.querySelector('[data-game-linux-field]');

        const refreshLinuxField = () => {
            if (!linuxField) {
                return;
            }
            const isPc = (platformSelect?.value || '') === 'pc';
            linuxField.hidden = !isPc;
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

        platformSelect?.addEventListener('change', refreshLinuxField);
        refreshLinuxField();
    });

    document.querySelectorAll('[data-game-editions-root]').forEach((root) => {
        const form = root.closest('form');
        const platformSelect = form?.querySelector('[data-game-platform-select]');
        const digitalToggle = root.querySelector('[data-game-digital-toggle]');
        const pcPanel = root.querySelector('[data-game-digital-pc]');
        const consolePanel = root.querySelector('[data-game-digital-console]');
        const consoleLabel = root.querySelector('[data-game-console-store-label]');

        const refresh = () => {
            const platform = platformSelect?.value || '';
            const digitalOn = Boolean(digitalToggle?.checked);
            const isPc = platform === 'pc';
            const isConsole = Object.prototype.hasOwnProperty.call(consoleStoreLabels, platform);

            if (pcPanel) {
                pcPanel.hidden = !(digitalOn && isPc);
            }
            if (consolePanel) {
                consolePanel.hidden = !(digitalOn && isConsole);
            }
            if (consoleLabel && isConsole) {
                consoleLabel.textContent = consoleStoreLabels[platform] || '—';
            }
        };

        platformSelect?.addEventListener('change', refresh);
        digitalToggle?.addEventListener('change', refresh);
        refresh();
    });
}

/** Extensions jeux : checkbox + autocomplétion du jeu de base (catalogue). */
function initGameExtensionFields() {
    document.querySelectorAll('form').forEach((form) => {
        const root = form.querySelector('[data-game-extension-root]');
        if (!root) {
            return;
        }

        const toggle = root.querySelector('[data-game-extension-toggle]');
        const panel = root.querySelector('[data-game-extension-panel]');
        const input = root.querySelector('[data-game-extension-search]');
        const list = root.querySelector('[data-game-extension-list]');
        const oeuvreIdInput = root.querySelector('[data-game-extension-oeuvre-id]');
        const clearBtn = root.querySelector('[data-game-extension-clear]');
        const hint = root.querySelector('#base_game_hint');
        const hintLabel = root.querySelector('#base_game_hint_label');
        const catalogUrl = form.getAttribute('data-game-catalog-url') || '/rechercher-jeux-catalogue.php';

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

        const refreshPanel = () => {
            const on = Boolean(toggle.checked);
            panel.hidden = !on;
            if (!on) {
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

        // Initial state (édition)
        refreshPanel();
        if (String(oeuvreIdInput.value || '').trim() !== '') {
            setHint(input.value);
        }
    });
}
