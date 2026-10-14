'use strict';

(() => {
    const FAVOR_BASE = new URL('../../', document.currentScript.src).pathname; // raíz de la web, sea cual sea la carpeta
    const configNode = document.getElementById('inventoryConfig');
    if (!configNode) return;

    const config = JSON.parse(configNode.textContent || '{}');
    const CACHE_TTL = 30_000;
    const PAGE_SIZE = 20;
    const ENTRANCE_MS = 700; // entrada de la lista: rowIn (.34s) + el mayor retraso escalonado (12 × 24 ms)

    const $ = (id) => document.getElementById(id);
    const dom = {
        app: $('inventoryApp'),
        topbar: $('inventoryTopbar'),
        sidebar: $('inventorySidebar'),
        playerSelect: $('playerSelect'),
        characterSearch: $('characterSearch'),
        characterState: $('characterState'),
        characterList: $('characterList'),
        refreshCharactersBtn: $('refreshCharactersBtn'),
        globalMessage: $('globalMessage'),
        welcomeState: $('welcomeState'),
        characterWorkspace: $('characterWorkspace'),
        heroPortrait: $('heroPortrait'),
        heroPlayer: $('heroPlayer'),
        heroName: $('heroName'),
        heroStatus: $('heroStatus'),
        heroMeta: $('heroMeta'),
        heroQuote: $('heroQuote'),
        bankLink: $('bankLink'),
        archiveLink: $('archiveLink'),
        importSheetLink: $('importSheetLink'),
        addItemBtn: $('addItemBtn'),
        refreshInventoryBtn: $('refreshInventoryBtn'),
        inventorySummary: $('inventorySummary'),
        summaryItems: $('summaryItems'),
        summaryUnits: $('summaryUnits'),
        summaryEquipped: $('summaryEquipped'),
        summaryCategories: $('summaryCategories'),
        inventoryFilters: $('inventoryFilters'),
        itemSearch: $('itemSearch'),
        categoryFilter: $('categoryFilter'),
        equipmentFilter: $('equipmentFilter'),
        sortFilter: $('sortFilter'),
        inventoryState: $('inventoryState'),
        inventoryList: $('inventoryList'),
        itemRows: $('itemRows'),
        inventoryCards: $('inventoryCards'),
        itemCards: $('itemCards'),
        cardsViewBtn: $('cardsViewBtn'),
        listViewBtn: $('listViewBtn'),
        inventoryFooter: $('inventoryFooter'),
        resultCount: $('resultCount'),
        itemPager: $('itemPager'),
        itemModal: $('itemModal'),
        itemForm: $('itemForm'),
        modalTitle: $('modalTitle'),
        itemId: $('itemId'),
        itemName: $('itemName'),
        itemCategory: $('itemCategory'),
        itemQuantity: $('itemQuantity'),
        itemEquipped: $('itemEquipped'),
        itemDescription: $('itemDescription'),
        itemNotes: $('itemNotes'),
        itemImage: $('itemImage'),
        formError: $('formError'),
        saveItemBtn: $('saveItemBtn'),
        toastStack: $('toastStack'),
    };

    const state = {
        players: [],
        currentPlayerId: '',
        currentCharacterId: '',
        currentInventory: null,
        charactersCache: new Map(),
        inventoryCache: new Map(),
        charactersAbort: null,
        inventoryAbort: null,
        navigation: 0,
        pendingAdd: '',
        enteredAt: 0,
        page: 1,
        view: 'list',
        booted: false,
    };

    const categoryVisuals = [
        { key: 'combat', label: 'Arma / Armadura', test: /(arma|armadura|espada|daga|hacha|arco|ballesta|lanza|martillo|mazo|escudo|casco|capa)/i, code: 'AR' },
        { key: 'magic', label: 'Objeto mágico', test: /(objeto\s*m[aá]gico|m[aá]gic|encantad|poci[oó]n|pergamino|varita|bast[oó]n|orbe|reliquia)/i, code: 'MG' },
        { key: 'vanity', label: 'Vanidad', test: /(vanidad|joya|gema|anillo|collar|broche|adorno|cosm[eé]tico|tesoro|moneda)/i, code: 'VA' },
        { key: 'utility', label: 'Utilidad', test: /.*/i, code: 'UT' },
    ];

    function setBusy(isBusy) {
        dom.app?.setAttribute('aria-busy', isBusy ? 'true' : 'false');
    }

    // Mantiene el sidebar pegado exactamente debajo del header real.
    // Esto evita huecos si otro módulo global modifica el tamaño del header.
    function syncLayoutMetrics() {
        if (!dom.topbar) return;
        const height = Math.max(0, Math.round(dom.topbar.getBoundingClientRect().height));
        if (height) {
            document.documentElement.style.setProperty('--header-h', `${height}px`);
        }
    }

    function closeActionMenus(except = null) {
        document.querySelectorAll('.action-popover:not([hidden])').forEach((node) => {
            if (node !== except) node.hidden = true;
        });
        document.querySelectorAll('[data-action="menu"][aria-expanded="true"]').forEach((node) => {
            const ownPopover = node.closest('.actions-menu')?.querySelector('.action-popover');
            if (ownPopover !== except) node.setAttribute('aria-expanded', 'false');
        });
        document.querySelectorAll('.inventory-row.is-menu-open, .item-card.is-menu-open').forEach((node) => {
            const ownPopover = node.querySelector('.action-popover:not([hidden])');
            if (ownPopover !== except) node.classList.remove('is-menu-open');
        });
    }

    function placeActionPopover(popover, button) {
        if (!popover || !button) return;
        popover.classList.remove('opens-up');
        const buttonRect = button.getBoundingClientRect();
        const popoverHeight = popover.getBoundingClientRect().height || 120;
        const roomBelow = window.innerHeight - buttonRect.bottom;
        const roomAbove = buttonRect.top;
        if (roomBelow < popoverHeight + 12 && roomAbove > roomBelow) {
            popover.classList.add('opens-up');
        }
    }

    function apiUrl(action, params = {}) {
        const url = new URL(config.apiUrl || 'api.php', window.location.href);
        url.searchParams.set('action', action);
        Object.entries(params).forEach(([key, value]) => {
            if (value !== null && value !== undefined && String(value) !== '') {
                url.searchParams.set(key, String(value));
            }
        });
        return url;
    }

    async function apiGet(action, params = {}, signal) {
        const response = await fetch(apiUrl(action, params), {
            method: 'GET',
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal,
        });
        const payload = await response.json().catch(() => null);
        if (!response.ok || !payload?.ok) {
            throw new Error(payload?.error || 'No fue posible cargar los datos.');
        }
        return payload.data;
    }

    async function apiPost(formData) {
        formData.set('csrf', config.csrf || '');
        const response = await fetch(config.apiUrl || 'api.php', {
            method: 'POST',
            body: formData,
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const payload = await response.json().catch(() => null);
        if (!response.ok || !payload?.ok) {
            throw new Error(payload?.error || 'No fue posible guardar los cambios.');
        }
        return payload;
    }

    function showGlobalMessage(message, kind = 'error') {
        if (!dom.globalMessage) return;
        dom.globalMessage.textContent = message;
        dom.globalMessage.className = `global-message global-message--${kind}`;
        dom.globalMessage.hidden = false;
    }

    function clearGlobalMessage() {
        if (dom.globalMessage) dom.globalMessage.hidden = true;
    }

    function toast(message, kind = 'success') {
        if (!dom.toastStack) return;
        const node = document.createElement('div');
        node.className = `toast toast--${kind}`;
        node.textContent = message;
        dom.toastStack.append(node);
        window.setTimeout(() => {
            node.classList.add('toast--leaving');
            window.setTimeout(() => node.remove(), 220);
        }, 3200);
    }

    function createImage(url, alt, { wrapperClass = '', fallback = '✦', eager = false } = {}) {
        const wrap = document.createElement('span');
        wrap.className = wrapperClass;
        const fallbackNode = document.createElement('span');
        fallbackNode.textContent = fallback;
        wrap.append(fallbackNode);

        if (url) {
            const img = document.createElement('img');
            img.src = url;
            img.alt = alt || '';
            img.decoding = 'async';
            img.loading = eager ? 'eager' : 'lazy';
            if (eager) img.fetchPriority = 'high';
            img.addEventListener('load', () => wrap.classList.add('has-image'), { once: true });
            img.addEventListener('error', () => img.remove(), { once: true });
            wrap.append(img);
        }
        return wrap;
    }

    function categoryVisual(category = '') {
        return categoryVisuals.find((entry) => entry.test.test(category)) || categoryVisuals[3];
    }

    function categoryCode(category = '') {
        return categoryVisual(category).code;
    }

    function canonicalCategory(category = '') {
        return categoryVisual(category).label;
    }

    function categoryAsset(category = '') {
        const key = categoryVisual(category).key;
        return config.visualAssets?.categories?.[key] || '';
    }

    function populatePlayers(players, selectedPlayerId) {
        dom.playerSelect.innerHTML = '';
        if (!players.length) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'Sin jugadores';
            dom.playerSelect.append(option);
            dom.playerSelect.disabled = true;
            return;
        }
        players.forEach((player) => {
            const option = document.createElement('option');
            option.value = player.id;
            option.textContent = player.name;
            option.selected = player.id === selectedPlayerId;
            dom.playerSelect.append(option);
        });
        dom.playerSelect.disabled = false;
    }

    function currentCharacters() {
        return state.charactersCache.get(state.currentPlayerId)?.data || [];
    }

    function findCharacter(characterId) {
        for (const entry of state.charactersCache.values()) {
            const match = entry.data.find((character) => character.id === characterId);
            if (match) return match;
        }
        return null;
    }

    // Marca el personaje activo sin reconstruir la lista (evita repetir su animación en cada clic).
    function markActiveCharacter() {
        dom.characterList.querySelectorAll('[data-character-id]').forEach((button) => {
            const active = button.dataset.characterId === state.currentCharacterId;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function renderCharacters() {
        const characters = currentCharacters();
        const query = (dom.characterSearch.value || '').trim().toLocaleLowerCase('es');
        const filtered = query
            ? characters.filter((character) => character.name.toLocaleLowerCase('es').includes(query))
            : characters;

        dom.characterList.replaceChildren();
        dom.characterSearch.disabled = characters.length === 0;

        if (!characters.length) {
            dom.characterState.hidden = false;
            dom.characterState.textContent = 'Este jugador no tiene personajes activos.';
            return;
        }
        if (!filtered.length) {
            dom.characterState.hidden = false;
            dom.characterState.textContent = 'No hay personajes que coincidan con la búsqueda.';
            return;
        }
        dom.characterState.hidden = true;

        const fragment = document.createDocumentFragment();
        filtered.forEach((character, index) => {
            const button = document.createElement('button');
            button.style.animationDelay = `${Math.min(index, 10) * 28}ms`;
            button.type = 'button';
            button.className = `character-card${character.id === state.currentCharacterId ? ' is-active' : ''}`;
            button.dataset.characterId = character.id;
            button.setAttribute('aria-pressed', character.id === state.currentCharacterId ? 'true' : 'false');

            const fallback = (character.name || '?').trim().charAt(0).toLocaleUpperCase('es') || '?';
            const image = createImage(character.portraitUrl, character.name, {
                wrapperClass: 'character-card__image',
                fallback,
            });
            const text = document.createElement('span');
            text.className = 'character-card__text';
            const name = document.createElement('strong');
            name.textContent = character.name;
            const status = document.createElement('small');
            status.textContent = 'Activo';
            text.append(name, status);
            button.append(image, text);
            fragment.append(button);
        });
        dom.characterList.append(fragment);
    }

    function resetFilters() {
        dom.itemSearch.value = '';
        dom.categoryFilter.value = '';
        dom.equipmentFilter.value = 'all';
        dom.sortFilter.value = 'default';
        state.page = 1;
    }

    function resetWorkspace() {
        state.currentCharacterId = '';
        state.currentInventory = null;
        state.pendingAdd = '';
        state.navigation += 1; // una apertura de personaje aún en curso ya no debe aplicarse
        state.page = 1;
        dom.characterWorkspace.hidden = true;
        dom.welcomeState.hidden = false;
        dom.itemRows.replaceChildren();
        dom.itemCards?.replaceChildren();
        dom.inventoryList.hidden = true;
        if (dom.inventoryCards) dom.inventoryCards.hidden = true;
        dom.inventoryFooter.hidden = true;
        dom.inventoryState.textContent = '';
        dom.inventorySummary.hidden = true;
        dom.inventoryFilters.hidden = true;
        markActiveCharacter();
    }

    function setInventoryState(message, kind = '') {
        dom.inventoryState.className = `inventory-state${kind ? ` inventory-state--${kind}` : ''}`;
        dom.inventoryState.textContent = message;
        dom.inventoryState.hidden = !message;
    }

    // Ficha provisional (el inventario aún no llega): «Añadir objeto» se ve en espera y, si se
    // pulsa, el clic se recuerda y el editor se abre en cuanto llegan los datos.
    function setAddWaiting(waiting) {
        dom.addItemBtn.classList.toggle('is-waiting', waiting);
        dom.addItemBtn.setAttribute('aria-disabled', waiting ? 'true' : 'false');
    }

    // reveal=false actualiza la ficha sin repetir su animación de entrada (datos que llegan
    // después del clic, revalidaciones y cambios guardados).
    function renderHero(payload, { reveal = true } = {}) {
        const character = payload.character;
        dom.welcomeState.hidden = true;
        dom.characterWorkspace.hidden = false;
        const characterSheet = dom.characterWorkspace.querySelector('.character-sheet');
        if (characterSheet && reveal) {
            characterSheet.classList.remove('is-revealing');
            void characterSheet.offsetWidth;
            characterSheet.classList.add('is-revealing');
        }
        // El retrato solo se vuelve a crear si cambia; así no parpadea al actualizar.
        const portraitKey = `${character.id}|${character.portraitUrl || ''}`;
        if (dom.heroPortrait.dataset.portraitKey !== portraitKey) {
            dom.heroPortrait.dataset.portraitKey = portraitKey;
            dom.heroPortrait.replaceChildren(createImage(character.portraitUrl, character.name, {
                wrapperClass: 'portrait__frame',
                fallback: (character.name || '?').charAt(0).toLocaleUpperCase('es') || '?',
                eager: true,
            }));
        }
        dom.heroPlayer.textContent = character.playerName || 'Personaje';
        dom.heroName.textContent = character.name;
        if (dom.heroMeta) dom.heroMeta.textContent = `${character.playerName || 'Aventurero'} · Inventario del personaje`;
        if (dom.heroQuote) dom.heroQuote.textContent = '“Un buen equipo abre más puertas.”';
        dom.heroStatus.replaceChildren();

        const mode = document.createElement('span');
        mode.className = `status-pill ${payload.canEdit ? 'status-pill--edit' : 'status-pill--read'}`;
        mode.textContent = payload.canEdit ? 'Puedes editar' : 'Modo lectura';
        dom.heroStatus.append(mode);
        if (!character.active) {
            const inactive = document.createElement('span');
            inactive.className = 'status-pill status-pill--inactive';
            inactive.textContent = 'Personaje inactivo';
            dom.heroStatus.append(inactive);
        }

        dom.bankLink.href = `${config.links?.bank || '#'}${encodeURIComponent(character.id)}`;
        dom.archiveLink.href = `${config.links?.archive || '#'}${encodeURIComponent(character.id)}`;
        dom.addItemBtn.hidden = !payload.canEdit;
        if (dom.importSheetLink) {
            dom.importSheetLink.href = `${FAVOR_BASE}archivosdelgremio/importar-ficha.php?character=${encodeURIComponent(character.id)}`;
            dom.importSheetLink.hidden = !payload.canEdit;
        }
    }

    function updateSummary(items) {
        const rows = items.length;
        const units = items.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
        const equipped = items.filter((item) => item.equipped).length;
        const categories = new Set(items.map((item) => canonicalCategory(item.category))).size;
        dom.summaryItems.textContent = String(rows);
        dom.summaryUnits.textContent = String(units);
        dom.summaryEquipped.textContent = String(equipped);
        dom.summaryCategories.textContent = String(categories);
        dom.inventorySummary.hidden = false;
    }

    function updateCategoryFilter(items) {
        const previous = dom.categoryFilter.value;
        const categories = [...new Set(items.map((item) => canonicalCategory(item.category)))];
        dom.categoryFilter.innerHTML = '<option value="">Todas las categorías</option>';
        ['Arma / Armadura', 'Objeto mágico', 'Vanidad', 'Utilidad'].forEach((category) => {
            if (!categories.includes(category)) return;
            const option = document.createElement('option');
            option.value = category;
            option.textContent = category;
            dom.categoryFilter.append(option);
        });
        dom.categoryFilter.value = [...dom.categoryFilter.options].some((option) => option.value === previous) ? previous : '';
    }

    function filteredItems(items) {
        const query = (dom.itemSearch.value || '').trim().toLocaleLowerCase('es');
        const category = dom.categoryFilter.value;
        const equipment = dom.equipmentFilter.value;
        const sort = dom.sortFilter.value;

        const filtered = items.filter((item) => {
            if (query) {
                const haystack = `${item.name} ${item.category} ${item.description} ${item.notes}`.toLocaleLowerCase('es');
                if (!haystack.includes(query)) return false;
            }
            if (category && canonicalCategory(item.category) !== category) return false;
            if (equipment === 'equipped' && !item.equipped) return false;
            if (equipment === 'unequipped' && item.equipped) return false;
            return true;
        });

        if (sort === 'name') {
            filtered.sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' }));
        } else if (sort === 'category') {
            filtered.sort((a, b) => canonicalCategory(a.category).localeCompare(canonicalCategory(b.category), 'es', { sensitivity: 'base' }) || a.name.localeCompare(b.name, 'es'));
        } else if (sort === 'quantity') {
            filtered.sort((a, b) => b.quantity - a.quantity || a.name.localeCompare(b.name, 'es'));
        } else {
            filtered.sort((a, b) => Number(b.equipped) - Number(a.equipped) || canonicalCategory(a.category).localeCompare(canonicalCategory(b.category), 'es') || a.name.localeCompare(b.name, 'es'));
        }
        return filtered;
    }

    function makeCell(className = '') {
        const cell = document.createElement('div');
        cell.className = `inventory-cell${className ? ` ${className}` : ''}`;
        return cell;
    }

    function createItemVisual(item) {
        const visual = document.createElement('span');
        const visualType = categoryVisual(item.category);
        visual.className = `item-visual item-visual--${visualType.key}`;

        const fallback = document.createElement('span');
        fallback.className = 'item-visual__fallback';
        fallback.textContent = visualType.code;
        visual.append(fallback);

        const sources = [];
        if (item.imageUrl) sources.push(item.imageUrl);
        const generic = categoryAsset(item.category);
        if (generic && !sources.includes(generic)) sources.push(generic);

        const trySource = (index) => {
            if (index >= sources.length) return;
            const img = document.createElement('img');
            img.src = sources[index];
            img.alt = '';
            img.loading = 'lazy';
            img.decoding = 'async';
            img.addEventListener('load', () => {
                visual.querySelectorAll('img').forEach((node) => {
                    if (node !== img) node.remove();
                });
                visual.classList.add('has-image');
            }, { once: true });
            img.addEventListener('error', () => {
                img.remove();
                trySource(index + 1);
            }, { once: true });
            visual.append(img);
        };
        trySource(0);
        return visual;
    }

    function createActions(item, canEdit) {
        const actions = document.createElement('div');
        actions.className = 'actions-menu';

        const menuButton = document.createElement('button');
        menuButton.type = 'button';
        menuButton.className = 'row-action';
        menuButton.dataset.action = 'menu';
        menuButton.dataset.itemId = String(item.id);
        menuButton.title = 'Acciones';
        menuButton.setAttribute('aria-label', `Acciones para ${item.name}`);
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.textContent = '•••';

        const popover = document.createElement('div');
        popover.className = 'action-popover';
        popover.hidden = true;

        const detailsButton = document.createElement('button');
        detailsButton.type = 'button';
        detailsButton.dataset.action = 'details';
        detailsButton.dataset.itemId = String(item.id);
        detailsButton.textContent = 'Ver detalles';
        popover.append(detailsButton);

        if (canEdit) {
            const edit = document.createElement('button');
            edit.type = 'button';
            edit.dataset.action = 'edit';
            edit.dataset.itemId = String(item.id);
            edit.textContent = 'Editar';

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.dataset.action = 'delete';
            remove.dataset.itemId = String(item.id);
            remove.textContent = 'Eliminar';
            popover.append(edit, remove);
        }

        actions.append(menuButton, popover);
        return actions;
    }

    function createDetails(item) {
        const details = document.createElement('div');
        details.className = 'row-details';
        const detailsInner = document.createElement('div');
        detailsInner.className = 'row-details__inner';

        const previewBlock = document.createElement('div');
        previewBlock.className = 'detail-preview';
        const previewVisual = createItemVisual(item);
        previewVisual.classList.add('detail-preview__visual');
        const previewMeta = document.createElement('div');
        previewMeta.className = 'detail-preview__meta';
        const previewCategory = document.createElement('span');
        previewCategory.className = `category-chip category-chip--${categoryVisual(item.category).key}`;
        previewCategory.textContent = canonicalCategory(item.category);
        const previewName = document.createElement('strong');
        previewName.textContent = item.name;
        const previewQuantity = document.createElement('small');
        previewQuantity.textContent = `Cantidad: ${item.quantity}${item.equipped ? ' · Equipado' : ''}`;
        previewMeta.append(previewCategory, previewName, previewQuantity);
        previewBlock.append(previewVisual, previewMeta);

        const textColumn = document.createElement('div');
        textColumn.className = 'detail-text-column';
        const publicBlock = document.createElement('div');
        const publicTitle = document.createElement('h5');
        publicTitle.textContent = 'Descripción';
        const publicText = document.createElement('p');
        publicText.textContent = item.description || 'Sin descripción registrada.';
        publicBlock.append(publicTitle, publicText);
        textColumn.append(publicBlock);

        if (item.notes) {
            const privateBlock = document.createElement('div');
            const privateTitle = document.createElement('h5');
            privateTitle.textContent = 'Notas privadas';
            const privateText = document.createElement('p');
            privateText.className = 'private-note';
            privateText.textContent = item.notes;
            privateBlock.append(privateTitle, privateText);
            textColumn.append(privateBlock);
        }

        detailsInner.append(previewBlock, textColumn);
        details.append(detailsInner);
        return details;
    }

    function createItemRow(item, canEdit) {
        const row = document.createElement('article');
        row.className = 'inventory-row';
        row.dataset.itemId = String(item.id);

        const main = makeCell('item-main');
        const visual = createItemVisual(item);
        const mainText = document.createElement('div');
        mainText.className = 'item-main__text';
        const name = document.createElement('strong');
        name.textContent = item.name;
        const categorySmall = document.createElement('small');
        categorySmall.textContent = item.imageUrl ? 'Imagen propia' : 'Visual de categoría';
        mainText.append(name, categorySmall);
        main.append(visual, mainText);

        const categoryCell = makeCell();
        const category = document.createElement('span');
        category.className = `category-chip category-chip--${categoryVisual(item.category).key}`;
        category.textContent = canonicalCategory(item.category);
        categoryCell.append(category);

        const quantityCell = makeCell();
        const quantity = document.createElement('span');
        quantity.className = 'quantity-value';
        quantity.textContent = `×${item.quantity}`;
        quantityCell.append(quantity);

        const equipmentCell = makeCell();
        if (canEdit) {
            const equip = document.createElement('button');
            equip.type = 'button';
            equip.className = `equip-button${item.equipped ? ' is-equipped' : ''}`;
            equip.dataset.action = 'equip';
            equip.dataset.itemId = String(item.id);
            equip.textContent = item.equipped ? '✓ Equipado' : 'Equipar';
            equipmentCell.append(equip);
        } else {
            const equip = document.createElement('span');
            equip.className = `equip-label ${item.equipped ? 'is-equipped' : 'is-unequipped'}`;
            equip.textContent = item.equipped ? '✓ Equipado' : '—';
            equipmentCell.append(equip);
        }

        const descriptionCell = makeCell();
        const description = document.createElement('span');
        description.className = 'description-preview';
        description.textContent = item.description || 'Sin descripción.';
        descriptionCell.append(description);

        const actionsCell = makeCell();
        actionsCell.append(createActions(item, canEdit));

        row.append(main, categoryCell, quantityCell, equipmentCell, descriptionCell, actionsCell, createDetails(item));
        return row;
    }

    function createItemCard(item, canEdit) {
        const card = document.createElement('article');
        card.className = 'item-card';
        card.dataset.itemId = String(item.id);

        const visualWrap = document.createElement('div');
        visualWrap.className = 'item-card__visual';
        const visual = createItemVisual(item);
        visualWrap.append(visual);
        if (item.equipped) {
            const badge = document.createElement('span');
            badge.className = 'item-card__badge';
            badge.textContent = '✓ Equipado';
            visualWrap.append(badge);
        }

        const body = document.createElement('div');
        body.className = 'item-card__body';
        const titleRow = document.createElement('div');
        titleRow.className = 'item-card__title-row';
        const title = document.createElement('strong');
        title.textContent = item.name;
        const qty = document.createElement('span');
        qty.textContent = `×${item.quantity}`;
        titleRow.append(title, qty);

        const desc = document.createElement('p');
        desc.textContent = item.description || 'Sin descripción.';

        const footer = document.createElement('div');
        footer.className = 'item-card__footer';
        const chip = document.createElement('span');
        chip.className = `category-chip category-chip--${categoryVisual(item.category).key}`;
        chip.textContent = canonicalCategory(item.category);
        footer.append(chip, createActions(item, canEdit));

        body.append(titleRow, desc, footer);
        card.append(visualWrap, body, createDetails(item));
        return card;
    }

    function renderPager(totalItems) {
        dom.itemPager.replaceChildren();
        const totalPages = Math.max(1, Math.ceil(totalItems / PAGE_SIZE));
        if (state.page > totalPages) state.page = totalPages;
        if (totalPages <= 1) return;

        const makeButton = (label, page, disabled = false, active = false) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `page-button${active ? ' is-active' : ''}`;
            button.textContent = label;
            button.dataset.page = String(page);
            button.disabled = disabled;
            return button;
        };

        dom.itemPager.append(makeButton('‹', state.page - 1, state.page <= 1));
        const start = Math.max(1, Math.min(state.page - 2, totalPages - 4));
        const end = Math.min(totalPages, start + 4);
        for (let page = start; page <= end; page += 1) {
            dom.itemPager.append(makeButton(String(page), page, false, page === state.page));
        }
        dom.itemPager.append(makeButton('›', state.page + 1, state.page >= totalPages));
    }

    function setView(view) {
        state.view = view === 'cards' ? 'cards' : 'list';
        dom.listViewBtn?.classList.toggle('is-active', state.view === 'list');
        dom.cardsViewBtn?.classList.toggle('is-active', state.view === 'cards');
        dom.listViewBtn?.setAttribute('aria-pressed', state.view === 'list' ? 'true' : 'false');
        dom.cardsViewBtn?.setAttribute('aria-pressed', state.view === 'cards' ? 'true' : 'false');
        renderItemsOnly();
    }

    // quiet=true repinta en el sitio (sin animación de entrada) conservando los detalles
    // abiertos y el scroll: se usa cuando cambian los datos, no cuando el usuario navega.
    // Si la lista aún está entrando (clic reciente), las filas nuevas continúan esa misma
    // animación desde donde iba en lugar de cortarla.
    function renderItemsOnly({ quiet = false } = {}) {
        const payload = state.currentInventory;
        if (!payload) return;
        const sourceItems = payload.items || [];
        const filtered = filteredItems(sourceItems);
        const expandedIds = quiet
            ? [...document.querySelectorAll('#itemRows .is-expanded, #itemCards .is-expanded')].map((node) => node.dataset.itemId)
            : [];
        const scrollY = window.scrollY;
        const now = performance.now();
        const played = quiet ? Math.max(0, now - state.enteredAt) : 0;
        const settled = quiet && played >= ENTRANCE_MS;
        const offset = quiet && !settled ? played : 0;
        if (!quiet) state.enteredAt = now;
        dom.inventoryList.classList.toggle('is-settled', settled);
        dom.inventoryCards?.classList.toggle('is-settled', settled);
        dom.itemRows.replaceChildren();
        dom.itemCards?.replaceChildren();

        if (!sourceItems.length) {
            dom.inventoryList.hidden = true;
            if (dom.inventoryCards) dom.inventoryCards.hidden = true;
            dom.inventoryFooter.hidden = true;
            setInventoryState(payload.canEdit ? 'El inventario está vacío. Añade el primer objeto para empezar.' : 'Este inventario todavía está vacío.', 'empty');
            return;
        }
        if (!filtered.length) {
            dom.inventoryList.hidden = true;
            if (dom.inventoryCards) dom.inventoryCards.hidden = true;
            dom.inventoryFooter.hidden = true;
            setInventoryState('No hay objetos que coincidan con los filtros.', 'empty');
            return;
        }

        const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
        if (state.page > totalPages) state.page = totalPages;
        const startIndex = (state.page - 1) * PAGE_SIZE;
        const pageItems = filtered.slice(startIndex, startIndex + PAGE_SIZE);

        if (state.view === 'cards') {
            const fragment = document.createDocumentFragment();
            pageItems.forEach((item, index) => { const card = createItemCard(item, payload.canEdit); card.style.animationDelay = `${Math.min(index, 12) * 24 - offset}ms`; fragment.append(card); });
            dom.itemCards.append(fragment);
            dom.inventoryList.hidden = true;
            dom.inventoryCards.hidden = false;
        } else {
            const fragment = document.createDocumentFragment();
            pageItems.forEach((item, index) => { const row = createItemRow(item, payload.canEdit); row.style.animationDelay = `${Math.min(index, 12) * 22 - offset}ms`; fragment.append(row); });
            dom.itemRows.append(fragment);
            dom.inventoryList.hidden = false;
            dom.inventoryCards.hidden = true;
        }

        dom.inventoryFooter.hidden = false;
        setInventoryState('');

        const first = startIndex + 1;
        const last = Math.min(startIndex + pageItems.length, filtered.length);
        dom.resultCount.textContent = filtered.length === sourceItems.length
            ? `Mostrando ${first}–${last} de ${filtered.length}`
            : `Mostrando ${first}–${last} de ${filtered.length} filtrados (${sourceItems.length} total)`;
        renderPager(filtered.length);

        if (quiet) {
            const container = state.view === 'cards' ? dom.itemCards : dom.itemRows;
            expandedIds.forEach((id) => container?.querySelector(`[data-item-id="${CSS.escape(id)}"]:not([data-action])`)?.classList.add('is-expanded'));
            if (window.scrollY !== scrollY) window.scrollTo(0, scrollY);
        }
    }

    function renderInventory(payload, { reveal = true, quiet = false } = {}) {
        state.currentInventory = payload;
        renderHero(payload, { reveal });
        setAddWaiting(false);
        const items = payload.items || [];
        updateSummary(items);
        updateCategoryFilter(items);
        dom.inventoryFilters.hidden = items.length === 0;
        renderItemsOnly({ quiet });
    }

    function renderInventoryLoading() {
        dom.inventorySummary.hidden = true;
        dom.inventoryFilters.hidden = true;
        dom.inventoryList.hidden = true;
        if (dom.inventoryCards) dom.inventoryCards.hidden = true;
        dom.inventoryFooter.hidden = true;
        dom.itemRows.replaceChildren();
        dom.itemCards?.replaceChildren();
        setInventoryState('Cargando inventario…', 'loading');
    }

    function syncUrl(characterId, { replace = false } = {}) {
        const url = new URL(window.location.href);
        if (characterId) url.searchParams.set('character', characterId);
        else url.searchParams.delete('character');
        const method = replace ? 'replaceState' : 'pushState';
        window.history[method]({ characterId: characterId || '' }, '', url);
    }

    async function loadCharacters(playerId, { force = false } = {}) {
        state.currentPlayerId = playerId;
        dom.playerSelect.value = playerId;
        dom.characterSearch.value = '';
        state.charactersAbort?.abort();

        const cached = state.charactersCache.get(playerId);
        if (cached && !force) {
            renderCharacters();
            return cached.data;
        }

        dom.characterState.hidden = false;
        dom.characterState.textContent = 'Cargando personajes activos…';
        dom.characterList.replaceChildren();
        dom.characterSearch.disabled = true;

        const controller = new AbortController();
        state.charactersAbort = controller;
        try {
            const data = await apiGet('characters', { playerId }, controller.signal);
            state.charactersCache.set(playerId, { data: data.characters || [], ts: Date.now() });
            if (state.currentPlayerId === playerId) renderCharacters();
            return data.characters || [];
        } catch (error) {
            if (error.name === 'AbortError') return [];
            dom.characterState.hidden = false;
            dom.characterState.textContent = 'No fue posible cargar los personajes.';
            showGlobalMessage(error.message);
            return [];
        } finally {
            if (state.charactersAbort === controller) state.charactersAbort = null;
        }
    }

    async function selectPlayer(playerId, { updateUrl = true, force = false } = {}) {
        if (!playerId) return;
        cancelInventoryRequest();
        state.currentPlayerId = playerId;
        resetWorkspace();
        if (updateUrl) syncUrl('', { replace: false });
        await loadCharacters(playerId, { force });
    }

    // Firma de los datos para saber si una revalidación trae algo nuevo (el orden de llegada no importa).
    function inventorySignature(data) {
        const items = [...(data?.items || [])].sort((a, b) => a.id - b.id);
        return JSON.stringify({ ...data, items });
    }

    // Guarda en caché y devuelve true si los datos cambiaron respecto a la copia anterior.
    function storeInventory(characterId, data) {
        const signature = inventorySignature(data);
        const previous = state.inventoryCache.get(characterId);
        state.inventoryCache.set(characterId, { data, ts: Date.now(), signature });
        return !previous || previous.signature !== signature;
    }

    function setRefreshing(isRefreshing) {
        dom.refreshInventoryBtn.classList.toggle('is-busy', isRefreshing);
        dom.refreshInventoryBtn.setAttribute('aria-busy', isRefreshing ? 'true' : 'false');
    }

    function cancelInventoryRequest() {
        state.inventoryAbort?.abort();
        state.inventoryAbort = null;
        setRefreshing(false);
    }

    // Permiso provisional para pintar la ficha mientras llega el inventario. Decide el servidor:
    // el editor no se abre hasta tener sus datos y cada guardado se vuelve a comprobar.
    function provisionalCanEdit(character) {
        const user = config.currentUser;
        return Boolean(user && (user.role === 'admin' || (user.playerId !== '' && user.playerId === character.playerId)));
    }

    // background=true revalida sin tocar la pantalla, salvo que los datos hayan cambiado.
    async function fetchInventory(characterId, { background = false } = {}) {
        cancelInventoryRequest();
        const controller = new AbortController();
        state.inventoryAbort = controller;
        if (background) setRefreshing(true);
        try {
            const data = await apiGet('inventory', { characterId }, controller.signal);
            const changed = storeInventory(characterId, data);
            if (state.currentCharacterId !== characterId) return;
            if (background) {
                if (changed) renderInventory(data, { reveal: false, quiet: true });
                return;
            }
            renderInventory(data, { reveal: false });
            if (state.pendingAdd === characterId) {
                state.pendingAdd = '';
                openEditor();
            }
        } catch (error) {
            if (error.name === 'AbortError' || state.currentCharacterId !== characterId) return;
            if (background) toast('No se pudo actualizar; se muestran los datos guardados en esta pestaña.', 'warning');
            else {
                state.pendingAdd = '';
                setInventoryState(error.message || 'No fue posible cargar el inventario.', 'error');
            }
        } finally {
            if (state.inventoryAbort === controller) {
                state.inventoryAbort = null;
                setRefreshing(false);
            }
        }
    }

    // Un clic = una petición como mucho y un único pintado animado. Con caché se pinta al
    // instante y, si está vieja, se revalida en segundo plano sin repintar si nada cambió.
    async function loadInventory(characterId, { force = false, updateUrl = true, replaceUrl = false } = {}) {
        if (!characterId) return;
        clearGlobalMessage();
        const cached = state.inventoryCache.get(characterId);
        const fresh = cached && Date.now() - cached.ts < CACHE_TTL;

        // Personaje ya abierto (o cargándose): no se vuelve a pintar.
        if (state.currentCharacterId === characterId && (state.currentInventory || state.inventoryAbort)) {
            if (state.currentInventory && !state.inventoryAbort && (force || !fresh)) {
                await fetchInventory(characterId, { background: true });
            }
            return;
        }

        state.currentCharacterId = characterId;
        state.currentInventory = null;
        state.pendingAdd = '';
        cancelInventoryRequest();
        resetFilters();
        markActiveCharacter();
        if (updateUrl) syncUrl(characterId, { replace: replaceUrl });

        if (cached) {
            renderInventory(cached.data);
            if (force || !fresh) await fetchInventory(characterId, { background: true });
            return;
        }

        const known = findCharacter(characterId);
        if (known) renderHero({ character: known, canEdit: provisionalCanEdit(known), items: [] });
        setAddWaiting(true);
        renderInventoryLoading();
        await fetchInventory(characterId);
    }

    // Cada apertura recibe un turno: si mientras espera a la red se abre otro personaje o se
    // vuelve a la portada (cambio de jugador, historial), deja de tener efecto.
    async function openCharacter(characterId, options = {}) {
        const ticket = ++state.navigation;
        const known = findCharacter(characterId);
        if (known) {
            if (state.currentPlayerId !== known.playerId) {
                state.currentPlayerId = known.playerId;
                dom.playerSelect.value = known.playerId;
                await loadCharacters(known.playerId);
                if (ticket !== state.navigation) return;
            }
            await loadInventory(characterId, options);
            return;
        }

        try {
            const context = await apiGet('context', { characterId });
            if (ticket !== state.navigation) return;
            const character = context.character;
            state.currentPlayerId = character.playerId;
            dom.playerSelect.value = character.playerId;
            await loadCharacters(character.playerId);
            if (ticket !== state.navigation) return;
            await loadInventory(characterId, options);
        } catch (error) {
            if (ticket !== state.navigation) return;
            showGlobalMessage(error.message);
            resetWorkspace();
        }
    }

    function openEditor(item = null) {
        const payload = state.currentInventory;
        if (!payload?.canEdit) return;
        dom.formError.hidden = true;
        dom.formError.textContent = '';
        dom.modalTitle.textContent = item ? 'Editar objeto' : 'Añadir objeto';
        dom.itemId.value = item?.id || 0;
        dom.itemName.value = item?.name || '';
        dom.itemCategory.value = item ? canonicalCategory(item.category) : 'Utilidad';
        dom.itemQuantity.value = item?.quantity || 1;
        dom.itemEquipped.checked = Boolean(item?.equipped);
        dom.itemDescription.value = item?.description || '';
        dom.itemNotes.value = item?.notes || '';
        dom.itemImage.value = item?.imageInput || '';
        dom.itemModal.hidden = false;
        document.body.classList.add('modal-open');
        window.setTimeout(() => dom.itemName.focus(), 0);
    }

    function closeEditor() {
        dom.itemModal.hidden = true;
        document.body.classList.remove('modal-open');
        dom.itemForm.reset();
        dom.itemId.value = '0';
        dom.itemCategory.value = 'Utilidad';
        dom.itemQuantity.value = '1';
        dom.formError.hidden = true;
    }

    // Copia más reciente del inventario de un personaje: la de pantalla si sigue abierto y,
    // si no, la de la caché. Sobre ella se aplica la respuesta de un guardado.
    function latestInventory(characterId, fallback) {
        if (state.currentCharacterId === characterId && state.currentInventory) return state.currentInventory;
        return state.inventoryCache.get(characterId)?.data || fallback;
    }

    // Tras guardar, equipar o eliminar: se repinta en el sitio y solo el objeto tocado vuelve a entrar.
    // Si mientras tanto se abrió otro personaje, el cambio solo se guarda en la caché del original:
    // no se toca la pantalla ni la carga en curso del personaje nuevo.
    function cacheInventory(characterId, payload, freshItemId = 0) {
        if (state.currentCharacterId !== characterId) {
            storeInventory(characterId, payload);
            return;
        }
        cancelInventoryRequest(); // una revalidación lanzada antes del cambio traería datos viejos
        storeInventory(characterId, payload);
        renderInventory(payload, { reveal: false, quiet: true });
        if (freshItemId) {
            const container = state.view === 'cards' ? dom.itemCards : dom.itemRows;
            container?.querySelector(`[data-item-id="${CSS.escape(String(freshItemId))}"]:not([data-action])`)?.classList.add('is-fresh');
        }
    }

    async function saveItem(event) {
        event.preventDefault();
        const characterId = state.currentCharacterId;
        const payload = state.currentInventory;
        if (!characterId || !payload?.canEdit) return;

        dom.saveItemBtn.disabled = true;
        dom.saveItemBtn.textContent = 'Guardando…';
        dom.formError.hidden = true;

        const form = new FormData();
        form.set('action', 'save');
        form.set('characterId', characterId);
        form.set('id', dom.itemId.value || '0');
        form.set('name', dom.itemName.value.trim());
        form.set('category', dom.itemCategory.value.trim() || 'Utilidad');
        form.set('quantity', dom.itemQuantity.value || '1');
        if (dom.itemEquipped.checked) form.set('equipped', '1');
        form.set('description', dom.itemDescription.value.trim());
        form.set('notes', dom.itemNotes.value.trim());
        form.set('imageUrl', dom.itemImage.value.trim());

        try {
            const response = await apiPost(form);
            const saved = response.data.item;
            const latest = latestInventory(characterId, payload);
            const items = [...(latest.items || [])];
            const index = items.findIndex((item) => item.id === saved.id);
            if (index >= 0) items[index] = saved;
            else items.push(saved);
            cacheInventory(characterId, { ...latest, items }, saved.id);
            closeEditor();
            toast(response.message || 'Objeto guardado.');
        } catch (error) {
            dom.formError.textContent = error.message;
            dom.formError.hidden = false;
        } finally {
            dom.saveItemBtn.disabled = false;
            dom.saveItemBtn.textContent = 'Guardar';
        }
    }

    async function deleteItem(itemId) {
        const payload = state.currentInventory;
        if (!payload?.canEdit) return;
        const item = payload.items.find((candidate) => candidate.id === itemId);
        if (!item) return;
        if (!window.confirm(`¿Eliminar “${item.name}” del inventario?`)) return;

        const characterId = state.currentCharacterId;
        const form = new FormData();
        form.set('action', 'delete');
        form.set('characterId', characterId);
        form.set('id', String(itemId));

        try {
            const response = await apiPost(form);
            const latest = latestInventory(characterId, payload);
            const items = (latest.items || []).filter((candidate) => candidate.id !== itemId);
            cacheInventory(characterId, { ...latest, items });
            toast(response.message || 'Objeto eliminado.');
        } catch (error) {
            toast(error.message, 'error');
        }
    }

    async function toggleEquipped(itemId) {
        const payload = state.currentInventory;
        if (!payload?.canEdit) return;
        const item = payload.items.find((candidate) => candidate.id === itemId);
        if (!item) return;

        const characterId = state.currentCharacterId;
        const form = new FormData();
        form.set('action', 'equip');
        form.set('characterId', characterId);
        form.set('id', String(itemId));
        form.set('equipped', item.equipped ? '0' : '1');

        try {
            const response = await apiPost(form);
            const saved = response.data.item;
            const latest = latestInventory(characterId, payload);
            const items = (latest.items || []).map((candidate) => candidate.id === saved.id ? saved : candidate);
            cacheInventory(characterId, { ...latest, items }, saved.id);
            toast(saved.equipped ? 'Objeto equipado.' : 'Objeto guardado en el inventario.');
        } catch (error) {
            toast(error.message, 'error');
        }
    }

    function toggleDetails(itemId) {
        const selector = `[data-item-id="${CSS.escape(String(itemId))}"]`;
        const node = (state.view === 'cards' ? dom.itemCards?.querySelector(selector) : dom.itemRows.querySelector(selector)) || dom.itemRows.querySelector(selector) || dom.itemCards?.querySelector(selector);
        if (!node) return;
        node.classList.toggle('is-expanded');
    }

    async function bootstrap() {
        setBusy(true);
        clearGlobalMessage();
        try {
            const data = await apiGet('bootstrap', { characterId: config.initialCharacter || '' });
            state.players = data.players || [];
            state.currentPlayerId = data.selectedPlayerId || '';
            populatePlayers(state.players, state.currentPlayerId);

            if (state.currentPlayerId) {
                state.charactersCache.set(state.currentPlayerId, { data: data.characters || [], ts: Date.now() });
                renderCharacters();
            } else {
                dom.characterState.hidden = false;
                dom.characterState.textContent = 'No hay jugadores disponibles.';
            }

            if (data.inventory?.character) {
                const characterId = data.inventory.character.id;
                state.currentCharacterId = characterId;
                storeInventory(characterId, data.inventory);
                renderInventory(data.inventory);
                markActiveCharacter();
                syncUrl(characterId, { replace: true });
            } else {
                resetWorkspace();
                if (config.initialCharacter) syncUrl('', { replace: true });
            }
            state.booted = true;
        } catch (error) {
            showGlobalMessage(error.message || 'No fue posible iniciar el inventario.');
            dom.characterState.hidden = false;
            dom.characterState.textContent = 'No fue posible cargar los personajes.';
        } finally {
            setBusy(false);
        }
    }

    dom.playerSelect.addEventListener('change', () => selectPlayer(dom.playerSelect.value));
    dom.characterSearch.addEventListener('input', renderCharacters);
    dom.characterList.addEventListener('click', (event) => {
        const button = event.target.closest('[data-character-id]');
        if (!button) return;
        openCharacter(button.dataset.characterId);
    });
    dom.refreshCharactersBtn.addEventListener('click', () => {
        if (state.currentPlayerId) loadCharacters(state.currentPlayerId, { force: true });
    });
    dom.refreshInventoryBtn.addEventListener('click', () => {
        if (state.currentCharacterId) loadInventory(state.currentCharacterId, { force: true, updateUrl: false });
    });
    dom.addItemBtn.addEventListener('click', () => {
        if (state.currentInventory) openEditor();
        else if (state.currentCharacterId) state.pendingAdd = state.currentCharacterId; // se abre al llegar los datos
    });

    [dom.itemSearch, dom.categoryFilter, dom.equipmentFilter, dom.sortFilter].forEach((control) => {
        const eventName = control === dom.itemSearch ? 'input' : 'change';
        control.addEventListener(eventName, () => {
            state.page = 1;
            renderItemsOnly();
        });
    });

    function handleItemAction(event) {
        const button = event.target.closest('[data-action][data-item-id]');
        if (!button || !state.currentInventory) return;
        const itemId = Number(button.dataset.itemId);
        const action = button.dataset.action;

        if (action === 'menu') {
            const actions = button.closest('.actions-menu');
            const popover = actions?.querySelector('.action-popover');
            const owner = button.closest('.inventory-row, .item-card');
            const willOpen = Boolean(popover?.hidden);

            closeActionMenus(popover || null);
            if (popover) {
                popover.hidden = !willOpen;
                if (willOpen) {
                    owner?.classList.add('is-menu-open');
                    // Espera un frame para medir el menú ya visible y decidir si abre arriba.
                    requestAnimationFrame(() => placeActionPopover(popover, button));
                } else {
                    owner?.classList.remove('is-menu-open');
                }
            }
            button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            event.stopPropagation();
            return;
        }

        const popover = button.closest('.action-popover');
        if (popover) {
            popover.hidden = true;
            const owner = button.closest('.inventory-row, .item-card');
            owner?.classList.remove('is-menu-open');
            owner?.querySelector('[data-action="menu"]')?.setAttribute('aria-expanded', 'false');
        }

        if (action === 'details') {
            toggleDetails(itemId);
        } else if (action === 'edit') {
            const item = state.currentInventory.items.find((candidate) => candidate.id === itemId);
            if (item) openEditor(item);
        } else if (action === 'delete') {
            deleteItem(itemId);
        } else if (action === 'equip') {
            toggleEquipped(itemId);
        }
    }

    dom.itemRows.addEventListener('click', handleItemAction);
    dom.itemCards?.addEventListener('click', handleItemAction);
    dom.cardsViewBtn?.addEventListener('click', () => setView('cards'));
    dom.listViewBtn?.addEventListener('click', () => setView('list'));
    document.addEventListener('click', (event) => {
        if (event.target.closest('.actions-menu')) return;
        closeActionMenus();
    });

    // Cierra menús flotantes al cambiar el viewport y sincroniza el layout.
    window.addEventListener('resize', () => {
        syncLayoutMetrics();
        closeActionMenus();
    }, { passive: true });
    window.addEventListener('scroll', closeActionMenus, { passive: true });

    syncLayoutMetrics();
    if (dom.topbar && 'ResizeObserver' in window) {
        new ResizeObserver(syncLayoutMetrics).observe(dom.topbar);
    }

    dom.itemPager.addEventListener('click', (event) => {
        const button = event.target.closest('[data-page]');
        if (!button || button.disabled) return;
        state.page = Math.max(1, Number(button.dataset.page) || 1);
        renderItemsOnly();
        dom.inventoryList.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    dom.itemForm.addEventListener('submit', saveItem);
    dom.itemModal.addEventListener('click', (event) => {
        if (event.target.closest('[data-close-modal]')) closeEditor();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !dom.itemModal.hidden) closeEditor();
    });

    window.addEventListener('popstate', () => {
        if (!state.booted) return;
        const characterId = new URL(window.location.href).searchParams.get('character') || '';
        if (characterId) openCharacter(characterId, { updateUrl: false });
        else resetWorkspace();
    });

    // Al volver a la pestaña con datos viejos se revalida en segundo plano: solo se repinta si algo cambió.
    window.addEventListener('focus', () => {
        if (!state.booted || !state.currentCharacterId || !state.currentInventory || state.inventoryAbort) return;
        const cached = state.inventoryCache.get(state.currentCharacterId);
        if (!cached || Date.now() - cached.ts >= CACHE_TTL) {
            fetchInventory(state.currentCharacterId, { background: true });
        }
    });

    bootstrap();
})();
