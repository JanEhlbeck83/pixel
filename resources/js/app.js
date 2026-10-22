import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

const canvas = document.getElementById('pixelCanvas');
if (!canvas) {
    throw new Error('Pixel canvas not found.');
}

const ctx = canvas.getContext('2d');
const canvasShell = canvas.closest('.canvas-shell');
if (!canvasShell) {
    throw new Error('Pixel canvas container not found.');
}
const canvasWorld = document.getElementById('canvasWorld');
const wallBackground = document.getElementById('wallBackground');
const cellSize = 1;
let wallWidth = canvas.width;
let wallHeight = canvas.height;
let wallTileSize = 300;
let wallDimensionsLoaded = false;
let initialViewportTilesLoaded = false;
let initialViewportTilesLoading = false;
let displayScale = 1;
const zoomLevels = [1, 1.5, 2, 3, 4];
let viewOffsetX = 0;
let viewOffsetY = 0;
let viewWidth = wallWidth;
let viewHeight = wallHeight;
const wallBackgroundTiles = new Map();
const wallTilePixelData = new Map();
const wallTileLoads = new Map();
const popupPalette = document.getElementById('popupColorPalette');
const selectionPreviewCanvas = document.getElementById('selectionPreviewCanvas');
const selectionOriginalCanvas = document.getElementById('selectionOriginalCanvas');
const pixelLockTooltip = document.getElementById('pixelLockTooltip');
const activeColorLabel = document.getElementById('activeColorLabel');
const statusText = document.getElementById('statusText');
const reservationLoadingOverlay = document.getElementById('reservationLoadingOverlay');
const reservationLoadingSpinner = document.getElementById('reservationLoadingSpinner');
const reservationLoadingMessage = document.getElementById('reservationLoadingMessage');
const reservationLoadingRetry = document.getElementById('reservationLoadingRetry');
const clearSelectionBtn = document.getElementById('clearSelectionBtn');
const applySelectionBtn = document.getElementById('applySelectionBtn');
const closeEditorBtn = document.getElementById('closeEditorBtn');
const editorPopup = document.getElementById('editorPopup');
const imageUpload = document.getElementById('imageUpload');
const rulesModal = document.getElementById('rulesModal');
const cookieBanner = document.getElementById('cookieBanner');
const acceptCookiesBtn = document.getElementById('acceptCookiesBtn');
const closeRulesBtn = document.getElementById('closeRulesBtn');
const openRulesBtn = document.getElementById('openRulesBtn');
const authFlag = document.getElementById('isAuthenticated');
const isAuthenticated = authFlag ? authFlag.dataset.auth === '1' : false;

let activeColor = '#ff6b6b';
let pixels = new Map();
let selectionPixels = new Map();
let selectionOriginalPixels = new Map();
let selection = null;
let selectionDrag = false;
let dragOrigin = null;
let hoverCell = null;
let selectionPopupTimer = null;
const selectionPopupDelay = 500;
let pixelLocks = new Map();
let activePixelLockArea = null;
let selectionLockHeartbeat = null;
const wallNavigationButtons = {
    up: document.getElementById('navigateUpBtn'),
    right: document.getElementById('navigateRightBtn'),
    down: document.getElementById('navigateDownBtn'),
    left: document.getElementById('navigateLeftBtn'),
};
const wallExtensionButtons = {
    x: document.getElementById('extendWallRightBtn'),
    y: document.getElementById('extendWallDownBtn'),
};
const zoomOutBtn = document.getElementById('zoomOutBtn');
const zoomResetBtn = document.getElementById('zoomResetBtn');
const zoomInBtn = document.getElementById('zoomInBtn');
const wallMenu = document.querySelector('.wall-menu');
const wallMenuToggle = document.getElementById('wallMenuToggle');
const wallMenuPanel = document.getElementById('wallMenuPanel');
const feedbackForm = document.getElementById('feedbackForm');
const feedbackStatus = document.getElementById('feedbackStatus');
const feedbackSubmitBtn = document.getElementById('feedbackSubmitBtn');

function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
}

function keyFor(x, y) {
    return `${x}:${y}`;
}

function setStatus(message) {
    if (statusText) {
        statusText.textContent = message;
    }
}

function getCellFromEvent(event) {
    const rect = canvas.getBoundingClientRect();
    const x = viewOffsetX + Math.floor((event.clientX - rect.left) / displayScale);
    const y = viewOffsetY + Math.floor((event.clientY - rect.top) / displayScale);

    if (x < 0 || y < 0 || x >= wallWidth || y >= wallHeight) {
        return null;
    }

    return { x, y };
}

function getReferencePixelColor(x, y) {
    if (x < 0 || y < 0 || x >= wallWidth || y >= wallHeight) {
        return null;
    }

    const tileX = Math.floor(x / wallTileSize) * wallTileSize;
    const tileY = Math.floor(y / wallTileSize) * wallTileSize;
    const tilePixels = wallTilePixelData.get(keyFor(tileX, tileY));
    const tile = wallBackgroundTiles.get(keyFor(tileX, tileY));
    if (!tilePixels || !tile) {
        return null;
    }

    const localX = x - tileX;
    const localY = y - tileY;
    if (localX >= tile.naturalWidth || localY >= tile.naturalHeight) {
        return null;
    }

    const index = (localY * tile.naturalWidth + localX) * 4;
    return `#${[0, 1, 2].map((channel) => tilePixels[index + channel].toString(16).padStart(2, '0')).join('')}`;
}

function getActivePixelLock(x, y) {
    const key = keyFor(x, y);
    const lock = pixelLocks.get(key);

    if (!lock || Date.parse(lock.locked_until) <= Date.now()) {
        pixelLocks.delete(key);
        return null;
    }

    return lock;
}

async function refreshPixelLocks() {
    try {
        const response = await fetch('/api/pixels/locks', { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            return;
        }

        const payload = await response.json();
        pixelLocks = new Map((payload.locked_pixels || []).map((pixel) => [
            keyFor(pixel.x, pixel.y),
            pixel,
        ]));
    } catch (error) {
        console.error('Failed to refresh pixel locks:', error);
    }
}

function updatePixelLockTooltip(event) {
    const cell = getCellFromEvent(event);
    const lock = cell ? getActivePixelLock(cell.x, cell.y) : null;

    if (!lock || !pixelLockTooltip) {
        pixelLockTooltip?.classList.add('hidden');
        return;
    }

    const minutesRemaining = Math.max(1, Math.ceil((Date.parse(lock.locked_until) - Date.now()) / 60000));
    const minuteLabel = minutesRemaining === 1 ? 'Minute' : 'Minuten';
    pixelLockTooltip.textContent = `Bereits bearbeitet. Bearbeitung in ${minutesRemaining} ${minuteLabel} wieder möglich.`;

    const shellRect = canvas.parentElement.getBoundingClientRect();
    pixelLockTooltip.style.left = `${clamp(event.clientX - shellRect.left + 12, 8, shellRect.width - 288)}px`;
    pixelLockTooltip.style.top = `${clamp(event.clientY - shellRect.top + 12, 8, shellRect.height - 56)}px`;
    pixelLockTooltip.classList.remove('hidden');
}

function findLockedPixelInSelection() {
    if (!selection) {
        return null;
    }

    for (const [key] of pixelLocks) {
        const [x, y] = key.split(':').map(Number);
        if (x >= selection.x && x < selection.x + selection.width && y >= selection.y && y < selection.y + selection.height) {
            const lock = getActivePixelLock(x, y);
            if (lock) {
                return { x, y, lock };
            }
        }
    }

    return null;
}

async function requestPixelAreaLock(area, method = 'POST') {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    try {
        const response = await fetch('/api/pixels/lock', {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(area),
        });
        const payload = await response.json();

        if (!response.ok || !payload.success) {
            payload.locked_pixels?.forEach((pixel) => pixelLocks.set(keyFor(pixel.x, pixel.y), pixel));
            setStatus(payload.message || 'Der Bereich konnte nicht reserviert werden.');
            return false;
        }

        return true;
    } catch (error) {
        console.error('Failed to update pixel area lock:', error);
        setStatus('Die Flächensperre konnte nicht mit dem Server synchronisiert werden.');
        return false;
    }
}

async function releasePixelAreaLock() {
    if (selectionLockHeartbeat) {
        window.clearInterval(selectionLockHeartbeat);
        selectionLockHeartbeat = null;
    }

    const area = activePixelLockArea;
    activePixelLockArea = null;
    if (!area || !isAuthenticated) {
        return;
    }

    await requestPixelAreaLock(area, 'DELETE');
}

function ensureSelectionBounds() {
    if (!selection) {
        return;
    }

    selection.width = clamp(selection.width, 5, Math.min(wallWidth, 1024));
    selection.height = clamp(selection.height, 5, Math.min(wallHeight, 1024));

    selection.x = clamp(selection.x, 0, wallWidth - selection.width);
    selection.y = clamp(selection.y, 0, wallHeight - selection.height);
}

function snapToStep(value) {
    return Math.max(5, Math.round(value / 5) * 5);
}

function updateSelection(x, y, width, height) {
    const snappedWidth = clamp(snapToStep(width), 5, Math.min(wallWidth, 1024));
    const snappedHeight = clamp(snapToStep(height), 5, Math.min(wallHeight, 1024));

    selection = {
        x: clamp(x, 0, wallWidth - Math.max(5, snappedWidth)),
        y: clamp(y, 0, wallHeight - Math.max(5, snappedHeight)),
        width: snappedWidth,
        height: snappedHeight,
    };

    ensureSelectionBounds();
    renderCanvas();
}

function renderCanvas() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    ctx.save();
    ctx.scale(displayScale, displayScale);
    pixels.forEach((color, key) => {
        const [x, y] = key.split(':').map(Number);
        if (x < viewOffsetX || y < viewOffsetY || x >= viewOffsetX + viewWidth || y >= viewOffsetY + viewHeight) {
            return;
        }
        ctx.fillStyle = color;
        ctx.fillRect((x - viewOffsetX) * cellSize, (y - viewOffsetY) * cellSize, cellSize, cellSize);
    });

    if (!selection && hoverCell
        && hoverCell.x + 5 > viewOffsetX && hoverCell.y + 5 > viewOffsetY
        && hoverCell.x < viewOffsetX + viewWidth && hoverCell.y < viewOffsetY + viewHeight) {
        const lineWidth = 1 / displayScale;
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = lineWidth;
        ctx.strokeRect(
            (hoverCell.x - viewOffsetX) + lineWidth / 2,
            (hoverCell.y - viewOffsetY) + lineWidth / 2,
            5 - lineWidth,
            5 - lineWidth
        );
    }

    if (!selection) {
        ctx.restore();
        return;
    }

    const startX = (selection.x - viewOffsetX) * cellSize;
    const startY = (selection.y - viewOffsetY) * cellSize;
    const widthPx = selection.width * cellSize;
    const heightPx = selection.height * cellSize;

    ctx.save();
    ctx.strokeStyle = '#111820';
    ctx.lineWidth = 3 / displayScale;
    ctx.setLineDash([]);
    ctx.strokeRect(startX, startY, widthPx, heightPx);
    ctx.strokeStyle = '#ffffff';
    ctx.lineWidth = 1 / displayScale;
    ctx.strokeRect(startX, startY, widthPx, heightPx);

    ctx.restore();
    ctx.restore();
}

function updateCanvasDisplaySize() {
    const availableWidth = Math.max(1, canvasShell.clientWidth);
    const availableHeight = Math.max(1, canvasShell.clientHeight);
    viewWidth = Math.ceil(availableWidth / displayScale);
    viewHeight = Math.ceil(availableHeight / displayScale);
    viewOffsetX = clamp(viewOffsetX, 0, Math.max(0, wallWidth - viewWidth));
    viewOffsetY = clamp(viewOffsetY, 0, Math.max(0, wallHeight - viewHeight));
    canvasWorld.style.width = `${availableWidth}px`;
    canvasWorld.style.height = `${availableHeight}px`;
    if (canvas.width !== availableWidth) {
        canvas.width = availableWidth;
    }
    if (canvas.height !== availableHeight) {
        canvas.height = availableHeight;
    }
    canvas.style.width = `${availableWidth}px`;
    canvas.style.height = `${availableHeight}px`;
    positionWallBackgroundImages();
}

function updateWallExtensionZones() {
    const viewportWidth = canvasShell.clientWidth;
    const viewportHeight = canvasShell.clientHeight;
    const rightEdge = (wallWidth - viewOffsetX) * displayScale;
    const bottomEdge = (wallHeight - viewOffsetY) * displayScale;
    const rightZone = wallExtensionButtons.x;
    const bottomZone = wallExtensionButtons.y;
    const rightNav = wallNavigationButtons.right;
    const downNav = wallNavigationButtons.down;
    const atRightEdge = wallWidth - viewOffsetX <= viewWidth;
    const atBottomEdge = wallHeight - viewOffsetY <= viewHeight;

    if (rightZone) {
        rightZone.classList.toggle('hidden', !atRightEdge);
        rightZone.style.left = `${Math.max(0, Math.min(rightEdge, viewportWidth) - 30)}px`;
        rightZone.style.height = `${viewportHeight}px`;
    }

    if (bottomZone) {
        bottomZone.classList.toggle('hidden', !atBottomEdge);
        bottomZone.style.top = `${Math.max(0, Math.min(bottomEdge, viewportHeight) - 30)}px`;
        bottomZone.style.width = `${Math.max(0, Math.min(rightEdge, viewportWidth))}px`;
    }

    rightNav?.classList.toggle('hidden', atRightEdge);
    downNav?.classList.toggle('hidden', atBottomEdge);
}

function positionWallBackgroundImages() {
    wallBackgroundTiles.forEach((tile, key) => {
        const [x, y] = key.split(':').map(Number);
        tile.style.left = `${(x - viewOffsetX) * displayScale}px`;
        tile.style.top = `${(y - viewOffsetY) * displayScale}px`;
        tile.style.width = `${tile.naturalWidth * displayScale}px`;
        tile.style.height = `${tile.naturalHeight * displayScale}px`;
    });
    updateWallExtensionZones();
}

function changeZoom(zoom) {
    const nextScale = zoomLevels[zoom];
    const centerX = viewOffsetX + viewWidth / 2;
    const centerY = viewOffsetY + viewHeight / 2;
    displayScale = nextScale;
    updateCanvasDisplaySize();
    viewOffsetX = clamp(Math.floor(centerX - viewWidth / 2), 0, Math.max(0, wallWidth - viewWidth));
    viewOffsetY = clamp(Math.floor(centerY - viewHeight / 2), 0, Math.max(0, wallHeight - viewHeight));
    positionWallBackgroundImages();
    renderCanvas();

    zoomOutBtn.disabled = zoomLevels.indexOf(displayScale) === 0;
    zoomInBtn.disabled = zoomLevels.indexOf(displayScale) === zoomLevels.length - 1;
    zoomResetBtn.setAttribute('aria-label', `Zoom auf 100 Prozent zurücksetzen; aktuell ${Math.round(displayScale * 100)} Prozent`);
}

function loadWallTile(x, y) {
    const key = keyFor(x, y);
    if (wallTileLoads.has(key)) {
        return wallTileLoads.get(key);
    }

    const loading = new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => {
            const pixelCanvas = document.createElement('canvas');
            pixelCanvas.width = image.naturalWidth;
            pixelCanvas.height = image.naturalHeight;
            const pixelContext = pixelCanvas.getContext('2d');
            if (!pixelContext) {
                reject(new Error(`Pixel-Daten für Bildkachel ${x}:${y} konnten nicht gelesen werden.`));
                return;
            }
            pixelContext.drawImage(image, 0, 0);
            wallTilePixelData.set(key, pixelContext.getImageData(0, 0, image.naturalWidth, image.naturalHeight).data);
            image.className = 'wall-background-tile';
            image.alt = '';
            image.draggable = false;
            wallBackgroundTiles.get(key)?.remove();
            wallBackgroundTiles.set(key, image);
            wallTileLoads.delete(key);
            wallBackground.appendChild(image);
            positionWallBackgroundImages();
            resolve();
        };
        image.onerror = () => {
            wallTileLoads.delete(key);
            reject(new Error(`Bildkachel ${x}:${y} konnte nicht geladen werden.`));
        };
        image.src = `/images/wall-tiles/${x}-${y}.png?v=${Date.now()}`;
    });
    wallTileLoads.set(key, loading);

    return loading;
}

async function loadWallTiles() {
    const pending = [];
    const left = Math.floor(viewOffsetX / wallTileSize) * wallTileSize;
    const top = Math.floor(viewOffsetY / wallTileSize) * wallTileSize;
    const right = Math.min(wallWidth, Math.ceil((viewOffsetX + viewWidth) / wallTileSize) * wallTileSize);
    const bottom = Math.min(wallHeight, Math.ceil((viewOffsetY + viewHeight) / wallTileSize) * wallTileSize);

    for (let y = top; y < bottom; y += wallTileSize) {
        for (let x = left; x < right; x += wallTileSize) {
            const key = keyFor(x, y);
            if (wallBackgroundTiles.has(key)) {
                continue;
            }
            pending.push(loadWallTile(x, y));
        }
    }

    try {
        await Promise.all(pending);
    } catch (error) {
        console.error('Failed to load wall image tiles:', error);
        setStatus('Ein Bildausschnitt der Wall konnte nicht geladen werden.');
        throw error;
    }
}

function invalidateExpandedEdgeTiles(axis, previousWidth, previousHeight) {
    if (axis === 'x' && previousWidth % wallTileSize !== 0) {
        const tileX = Math.floor((previousWidth - 1) / wallTileSize) * wallTileSize;
        for (let y = 0; y < previousHeight; y += wallTileSize) {
            const key = keyFor(tileX, y);
            wallBackgroundTiles.get(key)?.remove();
            wallBackgroundTiles.delete(key);
            wallTilePixelData.delete(key);
        }
    } else if (axis === 'y' && previousHeight % wallTileSize !== 0) {
        const tileY = Math.floor((previousHeight - 1) / wallTileSize) * wallTileSize;
        for (let x = 0; x < previousWidth; x += wallTileSize) {
            const key = keyFor(x, tileY);
            wallBackgroundTiles.get(key)?.remove();
            wallBackgroundTiles.delete(key);
            wallTilePixelData.delete(key);
        }
    }
}

async function loadWallDimensions() {
    const response = await fetch('/api/wall', { headers: { Accept: 'application/json' } });
    if (!response.ok) {
        throw new Error('Die Größe der Pixelwand konnte nicht geladen werden.');
    }

    const dimensions = await response.json();
    wallWidth = dimensions.width;
    wallHeight = dimensions.height;
    wallTileSize = dimensions.tile_size;
    wallDimensionsLoaded = true;
    updateCanvasDisplaySize();
    loadInitialViewportTiles();
    renderCanvas();
}

function loadInitialViewportTiles() {
    if (initialViewportTilesLoaded || initialViewportTilesLoading
        || !wallDimensionsLoaded) {
        return;
    }

    initialViewportTilesLoading = true;
    loadWallTiles().then(() => {
        initialViewportTilesLoaded = true;
        reservationLoadingOverlay?.classList.add('hidden');
    }).catch((error) => {
        console.error('Failed to load initial wall viewport:', error);
        showInitialViewportLoadFailure();
    }).finally(() => {
        initialViewportTilesLoading = false;
    });
}

function showInitialViewportLoadFailure() {
    if (reservationLoadingSpinner) {
        reservationLoadingSpinner.hidden = true;
    }
    if (reservationLoadingMessage) {
        reservationLoadingMessage.textContent = 'Der Viewport konnte nicht vollständig geladen werden.';
    }
    reservationLoadingRetry?.classList.remove('hidden');
    reservationLoadingOverlay?.setAttribute('aria-label', 'Viewport konnte nicht geladen werden');
}

async function refreshWallDimensions() {
    try {
        const response = await fetch('/api/wall', { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            throw new Error('Die Größe der Pixelwand konnte nicht aktualisiert werden.');
        }

        const dimensions = await response.json();
        if (dimensions.width === wallWidth && dimensions.height === wallHeight) {
            return;
        }

        const previousWidth = wallWidth;
        const previousHeight = wallHeight;
        const wasAtRightEdge = viewOffsetX >= Math.max(0, previousWidth - viewWidth);
        const wasAtBottomEdge = viewOffsetY >= Math.max(0, previousHeight - viewHeight);
        wallWidth = dimensions.width;
        wallHeight = dimensions.height;
        if (dimensions.width !== previousWidth) {
            invalidateExpandedEdgeTiles('x', previousWidth, previousHeight);
        }
        if (dimensions.height !== previousHeight) {
            invalidateExpandedEdgeTiles('y', previousWidth, previousHeight);
        }
        updateCanvasDisplaySize();
        if ((dimensions.width > previousWidth && wasAtRightEdge)
            || (dimensions.height > previousHeight && wasAtBottomEdge)) {
            await loadWallTiles();
        }
        renderCanvas();
    } catch (error) {
        console.error('Failed to refresh wall dimensions:', error);
        setStatus(error.message || 'Die Größe der Pixelwand konnte nicht aktualisiert werden.');
    }
}

let wallNavigationInProgress = false;

async function navigateWall(direction) {
    const button = wallNavigationButtons[direction];
    if (!button || button.disabled) {
        return;
    }

    if (wallNavigationInProgress) {
        return;
    }

    wallNavigationInProgress = true;
    button.disabled = true;
    try {
        const axis = direction === 'left' || direction === 'right' ? 'x' : 'y';
        const step = direction === 'left' || direction === 'up' ? -wallTileSize : wallTileSize;
        const currentOffset = axis === 'x' ? viewOffsetX : viewOffsetY;
        let nextOffset;
        const maxOffset = Math.max(0, axis === 'x' ? wallWidth - viewWidth : wallHeight - viewHeight);
        if (step < 0) {
            nextOffset = Math.max(0, currentOffset + step);
        } else if (currentOffset < maxOffset) {
            nextOffset = Math.min(currentOffset + step, maxOffset);
        } else {
            return;
        }

        if (axis === 'x') {
            viewOffsetX = nextOffset;
        } else {
            viewOffsetY = nextOffset;
        }
        updateCanvasDisplaySize();
        await loadWallTiles();
        renderCanvas();
        setStatus(`300 px nach ${direction === 'left' ? 'links' : direction === 'right' ? 'rechts' : direction === 'up' ? 'oben' : 'unten'} navigiert.`);
    } catch (error) {
        console.error(error);
        setStatus(error.message || 'Die Wall konnte nicht erweitert werden.');
    } finally {
        button.disabled = false;
        wallNavigationInProgress = false;
    }
}

let wallExpansionInProgress = false;

async function extendWall(axis) {
    if (wallExpansionInProgress) {
        return;
    }
    if (!isAuthenticated) {
        setStatus('Bitte melde dich an, um die Pixelwand zu erweitern.');
        window.location.href = '/login';
        return;
    }

    wallExpansionInProgress = true;
    const button = wallExtensionButtons[axis];
    if (button) {
        button.disabled = true;
    }
    try {
        const previousWidth = wallWidth;
        const previousHeight = wallHeight;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const response = await fetch('/api/wall/extend', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ axis }),
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) {
            throw new Error(payload.message || 'Die Wall konnte nicht erweitert werden.');
        }

        wallWidth = payload.width;
        wallHeight = payload.height;
        invalidateExpandedEdgeTiles(axis, previousWidth, previousHeight);
        updateCanvasDisplaySize();
        await loadWallTiles();
        renderCanvas();
        setStatus(`Die Pixelwand wurde nach ${axis === 'x' ? 'rechts' : 'unten'} erweitert.`);
    } catch (error) {
        console.error(error);
        setStatus(error.message || 'Die Wall konnte nicht erweitert werden.');
    } finally {
        wallExpansionInProgress = false;
        if (button) {
            button.disabled = false;
        }
    }
}

async function loadPixelsForSelection(area = selection) {
    if (!area) {
        return;
    }

    try {
        const response = await fetch(`/api/pixels?x=${area.x}&y=${area.y}&width=${area.width}&height=${area.height}`);
        if (!response.ok) {
            throw new Error('Die Pixelwerte konnten nicht geladen werden.');
        }
        const payload = await response.json();
        const nextPixels = new Map();

        (payload.pixels || []).forEach((pixel) => {
            const key = keyFor(pixel.x, pixel.y);
            nextPixels.set(key, pixel.color);
            pixels.set(key, pixel.color);
            if (pixel.locked_until && Date.parse(pixel.locked_until) > Date.now()) {
                pixelLocks.set(key, pixel);
            }
        });

        if (selection && selection.x === area.x && selection.y === area.y
            && selection.width === area.width && selection.height === area.height) {
            selectionOriginalPixels = new Map(nextPixels);
            selectionPixels = new Map(nextPixels);
        }
        renderCanvas();
        if (selection && selection.x === area.x && selection.y === area.y
            && selection.width === area.width && selection.height === area.height) {
            renderSelectionPreview();
            renderOriginalSelectionPreview();
        }
    } catch (error) {
        console.error('Failed to load pixels for selection:', error);
        setStatus(error.message || 'Die Pixelwerte konnten nicht geladen werden.');
    }
}

function savePixel(x, y, color) {
    if (!isAuthenticated) {
        pixels.set(keyFor(x, y), color);
        renderCanvas();
        setStatus(`Lokale Vorschau gespeichert: Pixel ${x}:${y}. Melde dich an, um dauerhaft zu sichern.`);
        return Promise.resolve();
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    return fetch('/api/pixels', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify({ x, y, color }),
    }).then((response) => response.json().then((payload) => {
        if (!response.ok || !payload.success) {
            const error = new Error(payload.message || 'Could not save pixel');
            error.payload = payload;
            throw error;
        }

        return payload;
    })).then((payload) => {

        const key = keyFor(x, y);
        pixels.set(key, color);
        if (payload.locked_until) {
            pixelLocks.set(key, { x, y, locked_until: payload.locked_until });
        }
        renderCanvas();
        setStatus(`Pixel ${x}:${y} gespeichert.`);
    }).catch((error) => {
        console.error(error);
        if (error.payload?.locked_pixels?.length) {
            error.payload.locked_pixels.forEach((pixel) => pixelLocks.set(keyFor(pixel.x, pixel.y), pixel));
            setStatus('Bereits bearbeitet. Bearbeitung in 10 Minuten wieder möglich.');
            return false;
        }

        setStatus('Das Pixel konnte nicht gespeichert werden.');
        return false;
    });
}

function savePixels(changedPixels) {
    if (changedPixels.length === 0) {
        return Promise.resolve();
    }

    if (!isAuthenticated) {
        changedPixels.forEach((pixel) => pixels.set(keyFor(pixel.x, pixel.y), pixel.color));
        renderCanvas();
        setStatus('Änderungen lokal übernommen. Zum Sperren und Speichern bitte anmelden.');
        return Promise.resolve({ local: true });
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    return fetch('/api/pixels', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify({ pixels: changedPixels }),
    }).then((response) => response.json().then((payload) => {
        if (!response.ok || !payload.success) {
            const error = new Error(payload.message || 'Could not save batch of pixels');
            error.payload = payload;
            throw error;
        }

        return payload;
    })).then((payload) => {

        changedPixels.forEach((pixel) => {
            const key = keyFor(pixel.x, pixel.y);
            pixels.set(key, pixel.color);
            if (payload.locked_until) {
                pixelLocks.set(key, { x: pixel.x, y: pixel.y, locked_until: payload.locked_until });
            }
        });
        renderCanvas();
        setStatus(`Batch übernommen: ${changedPixels.length} Pixel gespeichert.`);
        return payload;
    }).catch((error) => {
        console.error(error);
        if (error.payload?.locked_pixels) {
            error.payload.locked_pixels.forEach((pixel) => pixelLocks.set(keyFor(pixel.x, pixel.y), pixel));
            const firstLock = error.payload.locked_pixels[0];
            const minutesRemaining = Math.max(1, Math.ceil((Date.parse(firstLock.locked_until) - Date.now()) / 60000));
            setStatus(`Bereits bearbeitet. Bearbeitung in ${minutesRemaining} Minuten wieder möglich.`);
            refreshPixelLocks();
            return false;
        }

        setStatus('Speichern fehlgeschlagen. Die Wand wurde nicht geändert.');
        return false;
    });
}

function paintCell(x, y) {
    if (!selection) {
        setStatus('Wähle zuerst eine Gestaltungsfläche aus.');
        return;
    }

    const insideSelection = x >= selection.x && x < selection.x + selection.width && y >= selection.y && y < selection.y + selection.height;
    if (!insideSelection) {
        return;
    }

    const key = keyFor(x, y);
    pixels.set(key, activeColor);
    selectionPixels.set(key, activeColor);
    renderCanvas();
    renderSelectionPreview();

    if (isAuthenticated) {
        savePixel(x, y, activeColor);
    } else {
        setStatus('Lokale Vorschau: Pixel gesetzt. Bitte anmelden, um dauerhaft zu speichern.');
    }
}

function paintPopupCell(event) {
    if (!selection || !selectionPreviewCanvas) {
        return;
    }

    const rect = selectionPreviewCanvas.getBoundingClientRect();
    const localX = Math.floor(((event.clientX - rect.left) / rect.width) * selection.width);
    const localY = Math.floor(((event.clientY - rect.top) / rect.height) * selection.height);

    if (localX < 0 || localY < 0 || localX >= selection.width || localY >= selection.height) {
        return;
    }

    const globalX = selection.x + localX;
    const globalY = selection.y + localY;
    const lock = getActivePixelLock(globalX, globalY);
    if (lock) {
        const minutesRemaining = Math.max(1, Math.ceil((Date.parse(lock.locked_until) - Date.now()) / 60000));
        setStatus(`Bereits bearbeitet. Bearbeitung in ${minutesRemaining} Minuten wieder möglich.`);
        return;
    }

    const key = keyFor(globalX, globalY);
    selectionPixels.set(key, activeColor);
    renderSelectionPreview();
    setStatus('Pixel in der Vorschau geändert. Klicke auf „Übernehmen“, um die Gestaltungsfläche zu speichern.');
}

function createSelectionAtCell(x, y) {
    selection = {
        x: clamp(x, 0, wallWidth - 5),
        y: clamp(y, 0, wallHeight - 5),
        width: 5,
        height: 5,
    };

    renderCanvas();
    setStatus('Gestaltungsfläche markiert. Du kannst sie vergrößern oder mit Farbe füllen.');
}

function handlePointerDown(event) {
    const cell = getCellFromEvent(event);
    if (!cell) {
        return;
    }

    if (getActivePixelLock(cell.x, cell.y)) {
        updatePixelLockTooltip(event);
        setStatus('Bereits bearbeitet. Bearbeitung in 10 Minuten wieder möglich.');
        return;
    }

    if (selection && cell.x >= selection.x && cell.x < selection.x + selection.width && cell.y >= selection.y && cell.y < selection.y + selection.height) {
        setStatus('Bearbeitung läuft nur im Popup. Wähle die Fläche neu oder bearbeite sie im Editor.');
        return;
    }

    window.clearTimeout(selectionPopupTimer);
    selectionPopupTimer = null;
    dragOrigin = cell;
    selectionDrag = true;
    canvas.setPointerCapture(event.pointerId);
    createSelectionAtCell(cell.x, cell.y);
}

function handleCanvasHover(event) {
    if (selectionDrag) {
        return;
    }

    const nextCell = getCellFromEvent(event);
    if (hoverCell?.x === nextCell?.x && hoverCell?.y === nextCell?.y) {
        return;
    }

    hoverCell = nextCell;
    renderCanvas();
}

function handlePointerMove(event) {
    if (!selectionDrag || !dragOrigin) {
        return;
    }

    const cell = getCellFromEvent(event);
    if (!cell) {
        return;
    }

    const minX = Math.min(dragOrigin.x, cell.x);
    const minY = Math.min(dragOrigin.y, cell.y);
    const width = Math.max(5, Math.abs(dragOrigin.x - cell.x) + 1);
    const height = Math.max(5, Math.abs(dragOrigin.y - cell.y) + 1);

    updateSelection(minX, minY, width, height);
    setStatus(`Auswahl: ${selection.width} × ${selection.height} px`);
}

function handlePointerUp() {
    if (!selectionDrag) {
        return;
    }

    selectionDrag = false;
    dragOrigin = null;

    if (selection) {
        ensureSelectionBounds();
        const lockedPixel = findLockedPixelInSelection();
        if (lockedPixel) {
            selection = null;
            renderCanvas();
            setStatus('Die Auswahl enthält bereits bearbeitete Pixel. Wähle eine freie Fläche.');
            return;
        }

        setStatus(`Ausgewählte Fläche: ${selection.width} × ${selection.height} px. Editor öffnet gleich.`);
        loadPixelsForSelection({ ...selection });
        selectionPopupTimer = window.setTimeout(() => {
            selectionPopupTimer = null;
            if (selection && !selectionDrag) {
                openEditorPopup();
            }
        }, selectionPopupDelay);
    }
}

function handlePointerCancel() {
    if (!selectionDrag) {
        return;
    }

    selectionDrag = false;
    dragOrigin = null;
    selection = null;
    renderCanvas();
    setStatus('Auswahl abgebrochen. Markiere erneut eine Gestaltungsfläche.');
}

function renderSelectionPreview() {
    if (!selection || !selectionPreviewCanvas) {
        return;
    }

    const previewCtx = selectionPreviewCanvas.getContext('2d');
    const width = selection.width;
    const height = selection.height;

    selectionPreviewCanvas.width = width;
    selectionPreviewCanvas.height = height;
    previewCtx.clearRect(0, 0, width, height);
    previewCtx.fillStyle = '#f8fafc';
    previewCtx.fillRect(0, 0, width, height);

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const absoluteX = selection.x + x;
            const absoluteY = selection.y + y;
            const absoluteKey = keyFor(absoluteX, absoluteY);
            const color = selectionPixels.get(absoluteKey)
                || pixels.get(absoluteKey)
                || getReferencePixelColor(absoluteX, absoluteY)
                || '#f8fafc';
            previewCtx.fillStyle = color;
            previewCtx.fillRect(x, y, 1, 1);
        }
    }
}

function renderOriginalSelectionPreview() {
    if (!selection || !selectionOriginalCanvas) {
        return;
    }

    const previewCtx = selectionOriginalCanvas.getContext('2d');
    const width = selection.width;
    const height = selection.height;

    selectionOriginalCanvas.width = width;
    selectionOriginalCanvas.height = height;
    previewCtx.clearRect(0, 0, width, height);
    previewCtx.fillStyle = '#f8fafc';
    previewCtx.fillRect(0, 0, width, height);

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const absoluteX = selection.x + x;
            const absoluteY = selection.y + y;
            const absoluteKey = keyFor(absoluteX, absoluteY);
            const color = selectionOriginalPixels.get(absoluteKey)
                || getReferencePixelColor(absoluteX, absoluteY)
                || '#f8fafc';
            previewCtx.fillStyle = color;
            previewCtx.fillRect(x, y, 1, 1);
        }
    }
}

async function openEditorPopup() {
    if (!selection) {
        return;
    }

    if (!isAuthenticated) {
        selection = null;
        renderCanvas();
        setStatus('Bitte anmelden, um den Bereich während der Bearbeitung zu sperren.');
        return;
    }

    const area = { ...selection };
    if (reservationLoadingMessage) {
        reservationLoadingMessage.textContent = 'Bereich wird reserviert …';
    }
    if (reservationLoadingSpinner) {
        reservationLoadingSpinner.hidden = false;
    }
    reservationLoadingRetry?.classList.add('hidden');
    reservationLoadingOverlay?.setAttribute('aria-label', 'Bereich wird reserviert');
    reservationLoadingOverlay?.classList.remove('hidden');
    let acquired;
    try {
        acquired = await requestPixelAreaLock(area);
    } finally {
        reservationLoadingOverlay?.classList.add('hidden');
    }
    if (!acquired) {
        selection = null;
        renderCanvas();
        refreshPixelLocks();
        return;
    }

    activePixelLockArea = area;
    selectionLockHeartbeat = window.setInterval(() => {
        requestPixelAreaLock(activePixelLockArea || area);
    }, 30000);

    renderSelectionPreview();
    renderOriginalSelectionPreview();
    editorPopup?.classList.remove('hidden');
}

function closeEditorPopup() {
    releasePixelAreaLock();
    editorPopup?.classList.add('hidden');
    selection = null;
    selectionPixels.clear();
    selectionOriginalPixels.clear();
    renderCanvas();
    setStatus('Auswahl beendet. Markiere erneut eine Gestaltungsfläche.');
}

function clearSelection() {
    selection = null;
    closeEditorPopup();
    renderCanvas();
    setStatus('Auswahl gelöscht. Markiere erneut eine Gestaltungsfläche.');
}

function rgbToHex(r, g, b) {
    return `#${[r, g, b].map((value) => value.toString(16).padStart(2, '0')).join('')}`;
}

function applyImageToSelection(file) {
    if (!file || !selection) {
        setStatus('Wähle zuerst eine Gestaltungsfläche aus und lade danach ein Bild hoch.');
        return Promise.resolve(false);
    }

    const lockedPixel = findLockedPixelInSelection();
    if (lockedPixel) {
        setStatus('Die Auswahl enthält bereits bearbeitete Pixel und ist noch gesperrt.');
        return Promise.resolve(false);
    }

    return new Promise((resolve) => {
        const bounds = { ...selection };
        const reader = new FileReader();
        reader.onerror = () => {
            setStatus('Das Bild konnte nicht gelesen werden.');
            resolve(false);
        };
        reader.onload = () => {
            const img = new Image();
            img.onerror = () => {
                setStatus('Das Bild konnte nicht geladen werden.');
                resolve(false);
            };
            img.onload = async () => {
            const offscreen = document.createElement('canvas');
            offscreen.width = bounds.width;
            offscreen.height = bounds.height;
            const offCtx = offscreen.getContext('2d');
            offCtx.drawImage(img, 0, 0, bounds.width, bounds.height);

            const changedPixels = [];
            for (let y = 0; y < bounds.height; y++) {
                for (let x = 0; x < bounds.width; x++) {
                    const data = offCtx.getImageData(x, y, 1, 1).data;
                    const alpha = data[3];
                    if (alpha < 10) {
                        continue;
                    }

                    const targetX = bounds.x + x;
                    const targetY = bounds.y + y;
                    const color = rgbToHex(data[0], data[1], data[2]);
                    const key = keyFor(targetX, targetY);
                    selectionPixels.set(key, color);
                    changedPixels.push({ x: targetX, y: targetY, color });
                }
            }

            const saved = await savePixels(changedPixels);
            if (saved === false) {
                resolve(false);
                return;
            }

            renderCanvas();
            renderSelectionPreview();
            setStatus('Bild in die Gestaltungsfläche übertragen.');
            resolve(true);
            };

            img.src = reader.result;
        };

        reader.readAsDataURL(file);
    });
}

function persistSelectionToDatabase() {
    if (!selection) {
        return Promise.resolve();
    }

    const changedPixels = [];
    for (let y = selection.y; y < selection.y + selection.height; y++) {
        for (let x = selection.x; x < selection.x + selection.width; x++) {
            const key = keyFor(x, y);
            const currentColor = selectionPixels.get(key);
            const originalColor = selectionOriginalPixels.get(key);

            if (currentColor && currentColor !== originalColor) {
                changedPixels.push({ x, y, color: currentColor });
            }
        }
    }

    if (changedPixels.length === 0) {
        return Promise.resolve();
    }

    const firstPixels = changedPixels.slice(0, 5).map((pixel) => `${pixel.x}:${pixel.y}`).join(', ');
    setStatus(`Pixelkoordinaten übernommen: ${firstPixels}${changedPixels.length > 5 ? ' ...' : ''}`);

    return savePixels(changedPixels);
}

function initCookieAndRules() {
    const cookieKey = 'pixel-chaos-cookie-accepted';
    const rulesKey = 'pixel-chaos-rules-seen';

    if (!localStorage.getItem(cookieKey)) {
        cookieBanner.classList.add('visible');
    }

    if (!localStorage.getItem(rulesKey)) {
        rulesModal.classList.add('visible');
    }

    acceptCookiesBtn?.addEventListener('click', () => {
        localStorage.setItem(cookieKey, '1');
        cookieBanner.classList.remove('visible');
    });

    openRulesBtn?.addEventListener('click', () => {
        wallMenuPanel?.classList.add('hidden');
        wallMenuToggle?.setAttribute('aria-expanded', 'false');
        rulesModal.classList.add('visible');
    });

    closeRulesBtn?.addEventListener('click', () => {
        localStorage.setItem(rulesKey, '1');
        rulesModal.classList.remove('visible');
    });
}

if (popupPalette) {
    popupPalette.addEventListener('click', (event) => {
        const button = event.target.closest('[data-color]');
        if (!button) {
            return;
        }

        activeColor = button.dataset.color;
        activeColorLabel.textContent = activeColor;
        activeColorLabel.style.background = activeColor;
        setStatus(`Farbe ausgewählt: ${activeColor}`);
    });
}

wallMenuToggle?.addEventListener('click', () => {
    const isExpanded = wallMenuToggle.getAttribute('aria-expanded') === 'true';
    wallMenuToggle.setAttribute('aria-expanded', String(!isExpanded));
    wallMenuPanel?.classList.toggle('hidden', isExpanded);
});

document.addEventListener('click', (event) => {
    if (!wallMenu?.contains(event.target)) {
        wallMenuPanel?.classList.add('hidden');
        wallMenuToggle?.setAttribute('aria-expanded', 'false');
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        wallMenuPanel?.classList.add('hidden');
        wallMenuToggle?.setAttribute('aria-expanded', 'false');
    }
});

feedbackForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    feedbackSubmitBtn.disabled = true;
    feedbackStatus.textContent = 'Feedback wird gesendet ...';

    try {
        const formData = new FormData(feedbackForm);
        const response = await fetch('/feedback', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': formData.get('_token'),
            },
            body: JSON.stringify({
                email: formData.get('email'),
                message: formData.get('message'),
                website: formData.get('website'),
            }),
        });
        const payload = await response.json();
        if (!response.ok) {
            const message = payload.errors
                ? Object.values(payload.errors).flat()[0]
                : payload.message || 'Feedback konnte nicht gesendet werden.';
            throw new Error(message);
        }

        feedbackForm.reset();
        feedbackStatus.textContent = 'Vielen Dank! Dein Feedback wurde gespeichert.';
    } catch (error) {
        console.error('Failed to submit feedback:', error);
        feedbackStatus.textContent = error.message || 'Feedback konnte nicht gesendet werden.';
    } finally {
        feedbackSubmitBtn.disabled = false;
    }
});

canvas.addEventListener('pointerdown', handlePointerDown);
canvas.addEventListener('pointermove', handleCanvasHover);
canvas.addEventListener('pointermove', handlePointerMove);
canvas.addEventListener('pointermove', updatePixelLockTooltip);
canvas.addEventListener('pointerup', handlePointerUp);
canvas.addEventListener('pointercancel', handlePointerCancel);
canvas.addEventListener('pointerleave', () => {
    hoverCell = null;
    pixelLockTooltip?.classList.add('hidden');
    renderCanvas();
});
clearSelectionBtn?.addEventListener('click', clearSelection);
applySelectionBtn?.addEventListener('click', async () => {
    const saved = await persistSelectionToDatabase();
    if (saved === false) {
        return;
    }
    closeEditorPopup();
});
closeEditorBtn?.addEventListener('click', closeEditorPopup);
selectionPreviewCanvas?.addEventListener('click', paintPopupCell);
imageUpload?.addEventListener('change', async (event) => {
    const applied = await applyImageToSelection(event.target.files[0]);
    if (!applied) {
        return;
    }

    closeEditorPopup();
});

initCookieAndRules();
reservationLoadingRetry?.addEventListener('click', () => window.location.reload());
Object.entries(wallNavigationButtons).forEach(([direction, button]) => {
    button?.addEventListener('click', () => navigateWall(direction));
});
wallExtensionButtons.x?.addEventListener('click', () => extendWall('x'));
wallExtensionButtons.y?.addEventListener('click', () => extendWall('y'));
zoomOutBtn?.addEventListener('click', () => {
    const currentZoom = zoomLevels.indexOf(displayScale);
    if (currentZoom > 0) {
        changeZoom(currentZoom - 1);
    }
});
zoomResetBtn?.addEventListener('click', () => changeZoom(0));
zoomInBtn?.addEventListener('click', () => {
    const currentZoom = zoomLevels.indexOf(displayScale);
    if (currentZoom < zoomLevels.length - 1) {
        changeZoom(currentZoom + 1);
    }
});
window.addEventListener('resize', () => {
    updateCanvasDisplaySize();
    renderCanvas();
});
renderCanvas();
setStatus('Klicke und ziehe auf der Leinwand, um eine Gestaltungsfläche zu markieren. Mindestgröße: 5×5 px.');
loadWallDimensions().catch((error) => {
    console.error(error);
    setStatus(error.message || 'Die Pixelwand konnte nicht geladen werden.');
    showInitialViewportLoadFailure();
});
window.setInterval(refreshWallDimensions, 15000);
