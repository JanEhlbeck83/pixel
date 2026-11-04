import './bootstrap';
import './analytics';

import Alpine from 'alpinejs';
import { t, localizedUrl, apiFetch, locale } from './i18n';
import { initOnboarding } from './onboarding';
window.pixelI18n = { t, localizedUrl, apiFetch, locale };

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
const zoomLevels = [1, 1.5, 2, 3, 4, 6, 8];
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
const colorPicker = document.getElementById('colorPicker');
const colorHexInput = document.getElementById('colorHexInput');
const shareViewBtn = document.getElementById('shareViewBtn');
const shareFacebookLink = document.getElementById('shareFacebookLink');
const cursorCoords = document.getElementById('cursorCoords');
const cursorCoordsText = document.getElementById('cursorCoordsText');
const neighborhoodCanvas = document.getElementById('neighborhoodCanvas');
const neighborhoodLabel = document.getElementById('neighborhoodLabel');
const pixelCounter = document.getElementById('pixelCounter');
const pixelCounterValue = document.getElementById('pixelCounterValue');
const pixelCounterExtra = document.getElementById('pixelCounterExtra');
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
const wallReloadBtn = document.getElementById('wallReloadBtn');
let reportMode = false;
let reportOrigin = null;
let reportRect = null;
let knownTilesVersion = null;
let newTilesAvailable = false;
const wallMenu = document.querySelector('.wall-menu');
const wallMenuToggle = document.getElementById('wallMenuToggle');
const wallMenuPanel = document.getElementById('wallMenuPanel');
const feedbackForm = document.getElementById('feedbackForm');
const feedbackStatus = document.getElementById('feedbackStatus');
const feedbackSubmitBtn = document.getElementById('feedbackSubmitBtn');
const onboarding = initOnboarding();

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

function pixelLockMessage(x, y, lock) {
    const minutes = Math.max(1, Math.ceil((Date.parse(lock.locked_until) - Date.now()) / 60000));

    return t(minutes === 1 ? 'Pixel :x::y ist gesperrt. Bearbeitung in :minutes Minute wieder möglich.' : 'Pixel :x::y ist gesperrt. Bearbeitung in :minutes Minuten wieder möglich.', { x, y, minutes });
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
        const response = await apiFetch('/api/pixels/locks', { headers: { Accept: 'application/json' } });
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
    const divider = document.getElementById('cursorCoordsDivider');

    if (!lock || !pixelLockTooltip) {
        pixelLockTooltip?.classList.add('hidden');
        divider?.classList.add('hidden');
        return;
    }

    pixelLockTooltip.textContent = pixelLockMessage(cell.x, cell.y, lock);
    pixelLockTooltip.classList.remove('hidden');
    divider?.classList.remove('hidden');
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
        const response = await apiFetch('/api/pixels/lock', {
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
            if (payload.limit) {
                showLimitModal(payload);
                return false;
            }
            if (response.status === 409) {
                setStatus(t('Bitte bestätige zuerst deine E-Mail-Adresse.'));
                return false;
            }
            payload.locked_pixels?.forEach((pixel) => pixelLocks.set(keyFor(pixel.x, pixel.y), pixel));
            setStatus(payload.message || t('Der Bereich konnte nicht reserviert werden.'));
            setStatus(payload.message || t('Der Bereich konnte nicht reserviert werden.'));
            return false;
        }

        return true;
    } catch (error) {
        console.error('Failed to update pixel area lock:', error);
        setStatus(t('Die Flächensperre konnte nicht mit dem Server synchronisiert werden.'));
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

    selection.width = 1;
    selection.height = 1;

    selection.x = clamp(selection.x, 0, wallWidth - selection.width);
    selection.y = clamp(selection.y, 0, wallHeight - selection.height);
}

function updateSelection(x, y) {
    selection = {
        x: clamp(x, 0, wallWidth - 1),
        y: clamp(y, 0, wallHeight - 1),
        width: 1,
        height: 1,
    };

    ensureSelectionBounds();
    renderCanvas();
}

function renderCanvas() {
    renderNeighborhood();
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
        && hoverCell.x + 1 > viewOffsetX && hoverCell.y + 1 > viewOffsetY
        && hoverCell.x < viewOffsetX + viewWidth && hoverCell.y < viewOffsetY + viewHeight) {
        const lineWidth = 1 / displayScale;
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = lineWidth;
        ctx.strokeRect(
            (hoverCell.x - viewOffsetX) + lineWidth / 2,
            (hoverCell.y - viewOffsetY) + lineWidth / 2,
            1 - lineWidth,
            1 - lineWidth
        );
    }

    if (reportRect) {
        const reportX = (reportRect.x - viewOffsetX) * cellSize;
        const reportY = (reportRect.y - viewOffsetY) * cellSize;
        ctx.save();
        ctx.fillStyle = 'rgba(255, 45, 149, 0.18)';
        ctx.fillRect(reportX, reportY, reportRect.width * cellSize, reportRect.height * cellSize);
        ctx.strokeStyle = '#ff2d95';
        ctx.lineWidth = 2 / displayScale;
        ctx.setLineDash([6 / displayScale, 4 / displayScale]);
        ctx.strokeRect(reportX, reportY, reportRect.width * cellSize, reportRect.height * cellSize);
        ctx.restore();
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
        // Sind beide Reiter sichtbar, endet der rechte oberhalb des unteren; die konkave Rundung verbindet sie in der Ecke.
        const cornerTop = Math.max(0, Math.min(bottomEdge, viewportHeight) - 30);
        rightZone.style.height = `${atBottomEdge ? cornerTop : viewportHeight}px`;
        rightZone.classList.toggle('has-corner', atRightEdge && atBottomEdge);
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
    zoomResetBtn.textContent = `${Math.round(displayScale * 100)}%`;
    zoomResetBtn.setAttribute('aria-label', t('Zoom auf 100 Prozent zurücksetzen; aktuell :percent Prozent', { percent: Math.round(displayScale * 100).toLocaleString(locale) }));
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
                reject(new Error(t('Pixel-Daten für Bildkachel :x::y konnten nicht gelesen werden.', { x, y })));
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
            reject(new Error(t('Bildkachel :x::y konnte nicht geladen werden.', { x, y })));
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
            if (wallBackgroundTiles.has(keyFor(x, y))) {
                continue;
            }
            pending.push(loadWallTile(x, y));
        }
    }

    // Ring von einem Tile um den Viewport wird im Hintergrund vorgeladen.
    const prefetchLeft = Math.max(0, left - wallTileSize);
    const prefetchTop = Math.max(0, top - wallTileSize);
    const prefetchRight = Math.min(wallWidth, right + wallTileSize);
    const prefetchBottom = Math.min(wallHeight, bottom + wallTileSize);
    for (let y = prefetchTop; y < prefetchBottom; y += wallTileSize) {
        for (let x = prefetchLeft; x < prefetchRight; x += wallTileSize) {
            const inViewport = x >= left && x < right && y >= top && y < bottom;
            if (inViewport || wallBackgroundTiles.has(keyFor(x, y))) {
                continue;
            }
            loadWallTile(x, y).catch(() => {});
        }
    }

    try {
        await Promise.all(pending);
        // Beim Scrollen werden für neu sichtbare Kacheln die unverarbeiteten Pixel nachgeladen.
        loadRecentPixels();
    } catch (error) {
        console.error('Failed to load wall image tiles:', error);
        setStatus(t('Ein Bildausschnitt der Wall konnte nicht geladen werden.'));
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
    const response = await apiFetch('/api/wall', { headers: { Accept: 'application/json' } });
    if (!response.ok) {
        throw new Error(t('Die Größe der Pixelwand konnte nicht geladen werden.'));
    }

    const dimensions = await response.json();
    wallWidth = dimensions.width;
    wallHeight = dimensions.height;
    wallTileSize = dimensions.tile_size;
    knownTilesVersion = dimensions.tiles_version ?? null;
    wallDimensionsLoaded = true;
    updateCanvasDisplaySize();
    applyViewFromUrl();
    loadInitialViewportTiles();
    renderCanvas();
}

// Teilen: ?x=<Pixel>&y=<Pixel>[&z=<Zoom>] zentriert die Ansicht; zu große Werte stoppen am Wandende.
function applyViewFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const x = Number.parseInt(params.get('x'), 10);
    const y = Number.parseInt(params.get('y'), 10);
    if (!Number.isFinite(x) || !Number.isFinite(y)) {
        return;
    }

    const zoomIndex = zoomLevels.indexOf(Number(params.get('z')));
    if (zoomIndex !== -1) {
        changeZoom(zoomIndex);
    }

    const targetX = clamp(x, 0, wallWidth - 1);
    const targetY = clamp(y, 0, wallHeight - 1);
    viewOffsetX = clamp(targetX - Math.floor(viewWidth / 2), 0, Math.max(0, wallWidth - viewWidth));
    viewOffsetY = clamp(targetY - Math.floor(viewHeight / 2), 0, Math.max(0, wallHeight - viewHeight));
    hoverCell = { x: targetX, y: targetY };
    positionWallBackgroundImages();
}

function buildShareUrl() {
    const centerX = selection
        ? selection.x + Math.floor(selection.width / 2)
        : Math.floor(viewOffsetX + viewWidth / 2);
    const centerY = selection
        ? selection.y + Math.floor(selection.height / 2)
        : Math.floor(viewOffsetY + viewHeight / 2);
    const url = new URL(window.location.origin + window.location.pathname);
    url.searchParams.set('x', clamp(centerX, 0, wallWidth - 1));
    url.searchParams.set('y', clamp(centerY, 0, wallHeight - 1));
    url.searchParams.set('z', displayScale);

    return url.toString();
}

shareFacebookLink?.addEventListener('click', () => {
    shareFacebookLink.href = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(buildShareUrl())}`;
});

shareViewBtn?.addEventListener('click', async () => {
    if (shareViewBtn.disabled) return;
    const url = buildShareUrl();
    const feedback = document.getElementById('shareFeedback');
    const message = document.getElementById('shareFeedbackText');
    const input = document.getElementById('shareUrlInput');
    feedback.classList.add('hidden');
    input.value = url;
    shareViewBtn.disabled = true;
    try {
        if (typeof navigator.share === 'function') {
            try {
                await navigator.share({ title: 'PixRiot', text: t('Schau dir diesen Ausschnitt an!'), url });
                message.textContent = t('Ausschnitt geteilt.');
                feedback.classList.remove('hidden');
                return;
            } catch (error) {
                if (error?.name === 'AbortError') {
                    return;
                }
                console.warn('Native sharing failed; trying clipboard:', error);
            }
        }
        try {
            if (typeof navigator.clipboard?.writeText !== 'function') {
                throw new Error('Clipboard API is unavailable.');
            }
            await navigator.clipboard.writeText(url);
            message.textContent = t('Link kopiert!');
            feedback.classList.remove('hidden');
        } catch (error) {
            console.warn('Share link could not be copied:', error);
            message.textContent = t('Automatisches Kopieren nicht möglich. Bitte kopiere den Link aus dem Feld.');
            feedback.classList.remove('hidden');
            input.focus();
            input.select();
        }
    } finally {
        shareViewBtn.disabled = false;
    }
});
document.getElementById('shareUrlInput')?.addEventListener('click', (event) => event.currentTarget.select());

function moveCursorCoords(event) {
    if (!cursorCoords) {
        return;
    }
    const cell = getCellFromEvent(event);
    if (!cell) {
        cursorCoords.classList.add('hidden');
        return;
    }
    cursorCoordsText.textContent = `${cell.x} : ${cell.y}`;
    cursorCoords.style.transform = `translate(${event.clientX + 16}px, ${event.clientY + 18}px)`;
    cursorCoords.classList.remove('hidden');
}

canvas.addEventListener('pointermove', moveCursorCoords);
canvas.addEventListener('pointerleave', () => cursorCoords?.classList.add('hidden'));

let recentCursor = null;
let recentSyncing = false;
const recentActiveTiles = new Set();

// Sichtbare Kacheln plus 1 Kachel Rand; nur dafür werden unverarbeitete Pixel nachgeladen.
function recentTileArea() {
    const left = Math.max(0, Math.floor(viewOffsetX / wallTileSize) * wallTileSize - wallTileSize);
    const top = Math.max(0, Math.floor(viewOffsetY / wallTileSize) * wallTileSize - wallTileSize);
    const right = Math.min(wallWidth, Math.ceil((viewOffsetX + viewWidth) / wallTileSize) * wallTileSize + wallTileSize);
    const bottom = Math.min(wallHeight, Math.ceil((viewOffsetY + viewHeight) / wallTileSize) * wallTileSize + wallTileSize);
    const tiles = [];
    for (let y = top; y < bottom; y += wallTileSize) {
        for (let x = left; x < right; x += wallTileSize) {
            tiles.push({ x, y, key: keyFor(x, y) });
        }
    }

    return tiles;
}

async function fetchRecentPixels(tiles, since) {
    const minX = Math.min(...tiles.map((tile) => tile.x));
    const minY = Math.min(...tiles.map((tile) => tile.y));
    const maxX = Math.max(...tiles.map((tile) => tile.x)) + wallTileSize;
    const maxY = Math.max(...tiles.map((tile) => tile.y)) + wallTileSize;
    const params = new URLSearchParams({ x: minX, y: minY, width: maxX - minX, height: maxY - minY });
    if (since) {
        params.set('since', since);
    }
    const response = await apiFetch(`/api/pixels/recent?${params}`, { headers: { Accept: 'application/json' } });
    if (!response.ok) {
        return null;
    }
    const payload = await response.json();
    (payload.pixels || []).forEach((pixel) => {
        pixels.set(keyFor(pixel.x, pixel.y), pixel.color);
    });

    return payload;
}

// Neu sichtbare Kacheln: einmal alle unverarbeiteten Pixel holen. Bekannte Kacheln: nur Änderungen seit dem letzten Abruf.
async function loadRecentPixels({ full = false } = {}) {
    if (recentSyncing && full) {
        await new Promise((resolve) => window.setTimeout(resolve, 200));
        return loadRecentPixels({ full });
    }
    if (recentSyncing || !wallDimensionsLoaded) {
        return;
    }
    recentSyncing = true;
    try {
        if (full) {
            recentCursor = null;
            recentActiveTiles.clear();
        }
        const tiles = recentTileArea();
        const visible = new Set(tiles.map((tile) => tile.key));
        recentActiveTiles.forEach((key) => {
            if (!visible.has(key)) {
                recentActiveTiles.delete(key);
            }
        });
        const known = tiles.filter((tile) => recentActiveTiles.has(tile.key));
        const entering = tiles.filter((tile) => !recentActiveTiles.has(tile.key));
        let changed = false;

        if (known.length > 0 && recentCursor) {
            const payload = await fetchRecentPixels(known, recentCursor);
            if (payload) {
                changed = changed || (payload.pixels || []).length > 0;
                recentCursor = new Date(Date.parse(payload.server_time) - 3000).toISOString();
            }
        }
        if (entering.length > 0) {
            const payload = await fetchRecentPixels(entering, null);
            if (payload) {
                changed = changed || (payload.pixels || []).length > 0;
                entering.forEach((tile) => recentActiveTiles.add(tile.key));
                if (!recentCursor) {
                    recentCursor = new Date(Date.parse(payload.server_time) - 3000).toISOString();
                }
            }
        }
        if (changed) {
            renderCanvas();
        }
    } catch (error) {
        console.error('Failed to load recent pixels:', error);
    } finally {
        recentSyncing = false;
    }
}

window.setInterval(() => {
    if (!document.hidden && initialViewportTilesLoaded) {
        loadRecentPixels();
    }
}, 5000);

function loadInitialViewportTiles() {
    if (initialViewportTilesLoaded || initialViewportTilesLoading
        || !wallDimensionsLoaded) {
        return;
    }

    initialViewportTilesLoading = true;
    loadWallTiles().then(() => {
        initialViewportTilesLoaded = true;
        reservationLoadingOverlay?.classList.add('hidden');
        loadRecentPixels();
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
        reservationLoadingMessage.textContent = t('Der Viewport konnte nicht vollständig geladen werden.');
    }
    reservationLoadingRetry?.classList.remove('hidden');
    reservationLoadingOverlay?.setAttribute('aria-label', t('Viewport konnte nicht geladen werden'));
}

async function refreshWallDimensions() {
    try {
        const response = await apiFetch('/api/wall', { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            throw new Error(t('Die Größe der Pixelwand konnte nicht aktualisiert werden.'));
        }

        const dimensions = await response.json();
        if (knownTilesVersion !== null && dimensions.tiles_version > knownTilesVersion) {
            setNewTilesAvailable(true);
        }
        if (dimensions.width === wallWidth && dimensions.height === wallHeight) {
            return;
        }
        // Neue Leinwandgröße: Hinweis am Reload-Button, die Kacheln werden erst beim Klick erneuert.
        setNewTilesAvailable(true);

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
        setStatus(error.message || t('Die Größe der Pixelwand konnte nicht aktualisiert werden.'));
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
        setStatus(t('300 px nach :direction navigiert.', { direction: t(({ left: 'links', right: 'rechts', up: 'oben', down: 'unten' })[direction]) }));
    } catch (error) {
        console.error(error);
        setStatus(error.message || t('Die Leinwand konnte nicht erweitert werden.'));
    } finally {
        button.disabled = false;
        wallNavigationInProgress = false;
    }
}

function extendWall(axis) {
    openShop(axis === 'x' ? 'extend_x' : 'extend_y');
}

async function loadPixelsForSelection(area = selection) {
    if (!area) {
        return;
    }

    try {
        const response = await apiFetch(`/api/pixels?x=${area.x}&y=${area.y}&width=${area.width}&height=${area.height}`);
        if (!response.ok) {
            throw new Error(t('Die Pixelwerte konnten nicht geladen werden.'));
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

        // Angrenzende Pixel (1 Pixel Rand) für die Umgebungsansicht laden.
        const nx = Math.max(0, area.x - 1);
        const ny = Math.max(0, area.y - 1);
        const nw = Math.min(wallWidth, area.x + area.width + 1) - nx;
        const nh = Math.min(wallHeight, area.y + area.height + 1) - ny;
        const neighborResponse = await apiFetch(`/api/pixels?x=${nx}&y=${ny}&width=${nw}&height=${nh}`);
        if (neighborResponse.ok) {
            ((await neighborResponse.json()).pixels || []).forEach((pixel) => {
                pixels.set(keyFor(pixel.x, pixel.y), pixel.color);
            });
        }

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
        setStatus(error.message || t('Die Pixelwerte konnten nicht geladen werden.'));
    }
}

function savePixel(x, y, color) {
    if (onboarding.isLocked()) {
        setStatus(document.getElementById('welcomeTitle').textContent);
        return Promise.resolve(false);
    }
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    return apiFetch('/api/pixels', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify({ x, y, color }),
    }).then((response) => response.json().then((payload) => {
        if (!response.ok || !payload.success) {
            const error = new Error(payload.message || t('Pixel konnte nicht gespeichert werden.'));
            error.payload = payload;
            throw error;
        }

        return payload;
    })).then((payload) => {

        const key = keyFor(x, y);
        pixels.set(key, color);
        renderCanvas();
        fetchPixelStatus().then((status) => setStatus(t('Pixel :x::y gespeichert. :quota', { x, y, quota: describeQuota(status) })));
    }).catch((error) => {
        console.error(error);
        if (error.payload?.limit) {
            showLimitModal(error.payload);
            return false;
        }
        if (error.payload?.locked_pixels?.length) {
            error.payload.locked_pixels.forEach((pixel) => pixelLocks.set(keyFor(pixel.x, pixel.y), pixel));
            const firstLocked = error.payload.locked_pixels[0];
            setStatus(pixelLockMessage(firstLocked.x, firstLocked.y, firstLocked));
            return false;
        }

        setStatus(error.message || t('Das Pixel konnte nicht gespeichert werden.'));
        return false;
    });
}

function savePixels(changedPixels) {
    if (onboarding.isLocked()) {
        setStatus(document.getElementById('rulesTitle').textContent);
        return Promise.resolve(false);
    }
    if (changedPixels.length === 0) {
        return Promise.resolve();
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    return apiFetch('/api/pixels', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify({ pixels: changedPixels }),
    }).then((response) => response.json().then((payload) => {
        if (!response.ok || !payload.success) {
            const error = new Error(payload.message || t('Pixel konnten nicht gespeichert werden.'));
            error.payload = payload;
            throw error;
        }

        return payload;
    })).then((payload) => {

        changedPixels.forEach((pixel) => {
            const key = keyFor(pixel.x, pixel.y);
            pixels.set(key, pixel.color);
        });
        renderCanvas();
        fetchPixelStatus().then((status) => setStatus(t('Pixel gespeichert: :x::y. :quota', { x: changedPixels[0].x, y: changedPixels[0].y, quota: describeQuota(status) })));
        return payload;
    }).catch((error) => {
        console.error(error);
        if (error.payload?.limit) {
            showLimitModal(error.payload);
            return false;
        }
        if (error.payload?.locked_pixels) {
            error.payload.locked_pixels.forEach((pixel) => pixelLocks.set(keyFor(pixel.x, pixel.y), pixel));
            const firstLock = error.payload.locked_pixels[0];
            setStatus(pixelLockMessage(firstLock.x, firstLock.y, firstLock));
            refreshPixelLocks();
            return false;
        }

        setStatus(error.message || t('Speichern fehlgeschlagen. Die Wand wurde nicht geändert.'));
        return false;
    });
}

function paintCell(x, y) {
    if (!selection) {
        setStatus(t('Wähle zuerst einen Pixel aus.'));
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

    savePixel(x, y, activeColor);
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
        setStatus(pixelLockMessage(globalX, globalY, lock));
        return;
    }

    const key = keyFor(globalX, globalY);
    selectionPixels.set(key, activeColor);
    renderSelectionPreview();
    setStatus(t('Pixel in der Vorschau geändert. Klicke auf „Übernehmen“, um ihn zu speichern.'));
}

function createSelectionAtCell(x, y) {
    selection = {
        x: clamp(x, 0, wallWidth - 1),
        y: clamp(y, 0, wallHeight - 1),
        width: 1,
        height: 1,
    };

    renderCanvas();
    setStatus(t('Pixel :x::y ausgewählt.', { x: selection.x, y: selection.y }));
}

const pendingPaints = new Set();

// Klick = Pixel sofort setzen (optimistisch), bei Fehler wird zurückgerollt.
async function paintInstant(x, y) {
    const key = keyFor(x, y);
    if (pendingPaints.has(key)) {
        return;
    }
    pendingPaints.add(key);

    const area = { x, y, width: 1, height: 1 };
    const previous = pixels.get(key);
    const color = activeColor;
    pixels.set(key, color);
    renderCanvas();

    let ok = true;
    try {
        if (isAuthenticated && !(await requestPixelAreaLock(area))) {
            ok = false;
            refreshPixelLocks();
        } else {
            ok = (await savePixel(x, y, color)) !== false;
            if (isAuthenticated) {
                requestPixelAreaLock(area, 'DELETE');
            }
        }
    } finally {
        pendingPaints.delete(key);
    }

    if (!ok) {
        if (previous === undefined) {
            pixels.delete(key);
        } else {
            pixels.set(key, previous);
        }
        renderCanvas();
    }
}

function handlePointerDown(event) {
    if (reportMode) {
        return;
    }
    const cell = getCellFromEvent(event);
    if (!cell) {
        return;
    }

    if (getActivePixelLock(cell.x, cell.y)) {
        updatePixelLockTooltip(event);
        setStatus(t('Bereits bearbeitet. Bearbeitung in 10 Minuten wieder möglich.'));
        return;
    }

    paintInstant(cell.x, cell.y);
}

function handleCanvasHover(event) {
    if (selectionDrag || reportMode) {
        return;
    }

    const nextCell = getCellFromEvent(event);
    if (hoverCell?.x === nextCell?.x && hoverCell?.y === nextCell?.y) {
        return;
    }

    hoverCell = nextCell;
    renderNeighborhood();
    renderCanvas();
}

function handlePointerMove(event) {
    if (reportMode || !selectionDrag || !dragOrigin) {
        return;
    }

    const cell = getCellFromEvent(event);
    if (!cell) {
        return;
    }

    updateSelection(cell.x, cell.y);
    setStatus(t('Pixel :x::y ausgewählt.', { x: selection.x, y: selection.y }));
}

function handlePointerUp() {
    if (reportMode || !selectionDrag) {
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
            setStatus(t('Dieser Pixel wurde gerade bearbeitet. Wähle einen anderen Pixel.'));
            return;
        }

        setStatus(t('Pixel :x::y ausgewählt. Editor öffnet gleich.', { x: selection.x, y: selection.y }));
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
    if (reportMode || !selectionDrag) {
        return;
    }

    selectionDrag = false;
    dragOrigin = null;
    selection = null;
    renderCanvas();
    setStatus(t('Pixel-Auswahl abgebrochen. Klicke erneut auf einen Pixel.'));
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
    const cols = selection.width + 2;
    const rows = selection.height + 2;
    const scale = Math.max(1, Math.floor(240 / Math.max(cols, rows)));

    selectionOriginalCanvas.width = cols * scale;
    selectionOriginalCanvas.height = rows * scale;
    previewCtx.imageSmoothingEnabled = false;
    previewCtx.fillStyle = '#0b0f16';
    previewCtx.fillRect(0, 0, cols * scale, rows * scale);

    for (let y = 0; y < rows; y++) {
        for (let x = 0; x < cols; x++) {
            const absoluteX = selection.x - 1 + x;
            const absoluteY = selection.y - 1 + y;
            if (absoluteX < 0 || absoluteY < 0 || absoluteX >= wallWidth || absoluteY >= wallHeight) {
                continue;
            }
            const absoluteKey = keyFor(absoluteX, absoluteY);
            const inside = x >= 1 && y >= 1 && x <= selection.width && y <= selection.height;
            const color = (inside ? selectionOriginalPixels.get(absoluteKey) : null)
                || pixels.get(absoluteKey)
                || getReferencePixelColor(absoluteX, absoluteY)
                || '#f8fafc';
            previewCtx.fillStyle = color;
            previewCtx.fillRect(x * scale, y * scale, scale, scale);
        }
    }

    previewCtx.lineWidth = 2;
    previewCtx.strokeStyle = '#ffffff';
    previewCtx.strokeRect(scale + 1, scale + 1, selection.width * scale - 2, selection.height * scale - 2);
    previewCtx.lineWidth = 1;
    previewCtx.strokeStyle = '#000000';
    previewCtx.strokeRect(scale + 3, scale + 3, selection.width * scale - 6, selection.height * scale - 6);
}

async function openEditorPopup() {
    if (!selection) {
        return;
    }

    if (!isAuthenticated) {
        const status = await fetchPixelStatus();
        if (status && !status.can_edit) {
            selection = null;
            renderCanvas();
            showLimitModal(status);
            return;
        }
        renderSelectionPreview();
        renderOriginalSelectionPreview();
        editorPopup?.classList.remove('hidden');
        setStatus(t('Wähle eine Farbe und übernimm deinen Pixel. :quota', { quota: describeQuota(status) }));
        return;
    }

    const area = { ...selection };
    if (reservationLoadingMessage) {
        reservationLoadingMessage.textContent = t('Bereich wird reserviert …');
    }
    if (reservationLoadingSpinner) {
        reservationLoadingSpinner.hidden = false;
    }
    reservationLoadingRetry?.classList.add('hidden');
    reservationLoadingOverlay?.setAttribute('aria-label', t('Bereich wird reserviert'));
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
    setStatus(t('Pixel-Bearbeitung beendet. Klicke erneut auf einen Pixel.'));
}

function clearSelection() {
    selection = null;
    closeEditorPopup();
    renderCanvas();
    setStatus(t('Pixel-Auswahl gelöscht. Klicke erneut auf einen Pixel.'));
}

function rgbToHex(r, g, b) {
    return `#${[r, g, b].map((value) => value.toString(16).padStart(2, '0')).join('')}`;
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

    setStatus(t('Pixel :x::y wird übernommen.', { x: changedPixels[0].x, y: changedPixels[0].y }));

    return savePixels(changedPixels);
}

function setActiveColor(color) {
    activeColor = color.toLowerCase();
    activeColorLabel.textContent = activeColor;
    activeColorLabel.style.background = activeColor;
    if (colorPicker) {
        colorPicker.value = activeColor;
    }
    if (colorHexInput && document.activeElement !== colorHexInput) {
        colorHexInput.value = activeColor;
    }
    setStatus(t('Farbe ausgewählt: :color', { color: activeColor }));
}

colorPicker?.addEventListener('input', () => setActiveColor(colorPicker.value));
colorHexInput?.addEventListener('input', () => {
    let value = colorHexInput.value.trim();
    if (value && !value.startsWith('#')) {
        value = `#${value}`;
    }
    if (/^#[0-9a-fA-F]{6}$/.test(value)) {
        setActiveColor(value);
    }
});

if (popupPalette) {
    popupPalette.addEventListener('click', (event) => {
        const button = event.target.closest('[data-color]');
        if (!button) {
            return;
        }

        setActiveColor(button.dataset.color);
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
    feedbackStatus.textContent = t('Feedback wird gesendet ...');

    try {
        const formData = new FormData(feedbackForm);
        const response = await apiFetch('/feedback', {
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
                : payload.message || t('Feedback konnte nicht gesendet werden.');
            throw new Error(message);
        }

        feedbackForm.reset();
        feedbackStatus.textContent = t('Vielen Dank! Dein Feedback wurde gespeichert.');
    } catch (error) {
        console.error('Failed to submit feedback:', error);
        feedbackStatus.textContent = error.message || t('Feedback konnte nicht gesendet werden.');
    } finally {
        feedbackSubmitBtn.disabled = false;
    }
});

const reportAreaBtn = document.getElementById('reportAreaBtn');
const reportBar = document.getElementById('reportBar');
const reportModal = document.getElementById('reportModal');
const reportForm = document.getElementById('reportForm');
const reportStatus = document.getElementById('reportStatus');
const reportSubmitBtn = document.getElementById('reportSubmitBtn');
const reportMaxSize = 300;

function setReportMode(active) {
    reportMode = active;
    reportOrigin = null;
    reportRect = null;
    reportBar?.classList.toggle('hidden', !active);
    canvas.classList.toggle('report-mode', active);
    if (active) {
        selection = null;
        wallMenuPanel?.classList.add('hidden');
        wallMenuToggle?.setAttribute('aria-expanded', 'false');
    }
    renderCanvas();
}

function updateReportRect(cell) {
    const dx = clamp(cell.x - reportOrigin.x, -(reportMaxSize - 1), reportMaxSize - 1);
    const dy = clamp(cell.y - reportOrigin.y, -(reportMaxSize - 1), reportMaxSize - 1);
    reportRect = {
        x: Math.min(reportOrigin.x, reportOrigin.x + dx),
        y: Math.min(reportOrigin.y, reportOrigin.y + dy),
        width: Math.abs(dx) + 1,
        height: Math.abs(dy) + 1,
    };
}

function closeReportModal() {
    reportModal?.classList.add('hidden');
    reportForm?.reset();
    if (reportStatus) {
        reportStatus.textContent = '';
    }
}

reportAreaBtn?.addEventListener('click', () => {
    setReportMode(true);
    setStatus(t('Ziehe ein Rechteck über den Bereich, den du melden möchtest.'));
});
document.getElementById('reportCancelBar')?.addEventListener('click', () => setReportMode(false));
document.getElementById('reportCancelBtn')?.addEventListener('click', () => {
    closeReportModal();
    setReportMode(false);
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && reportMode) {
        closeReportModal();
        setReportMode(false);
    }
});

canvas.addEventListener('pointerdown', (event) => {
    if (!reportMode) {
        return;
    }
    const cell = getCellFromEvent(event);
    if (!cell) {
        return;
    }
    canvas.setPointerCapture?.(event.pointerId);
    reportOrigin = cell;
    updateReportRect(cell);
    renderCanvas();
});

canvas.addEventListener('pointermove', (event) => {
    if (!reportMode || !reportOrigin) {
        return;
    }
    const cell = getCellFromEvent(event);
    if (cell) {
        updateReportRect(cell);
        renderCanvas();
    }
});

canvas.addEventListener('pointerup', () => {
    if (!reportMode || !reportOrigin || !reportRect) {
        return;
    }
    reportOrigin = null;
    document.getElementById('reportArea').textContent = `${t('Bereich')} ${reportRect.x} : ${reportRect.y} · ${reportRect.width} × ${reportRect.height}`;
    reportModal.classList.remove('hidden');
    document.getElementById('reportReason')?.focus();
});

reportForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!reportRect) {
        return;
    }
    reportSubmitBtn.disabled = true;
    reportStatus.textContent = t('Meldung wird gesendet ...');

    try {
        const formData = new FormData(reportForm);
        const response = await apiFetch('/report', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': formData.get('_token'),
            },
            body: JSON.stringify({
                ...reportRect,
                reason: formData.get('reason'),
                message: formData.get('message'),
                email: formData.get('email') || null,
            }),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const message = payload.errors
                ? Object.values(payload.errors).flat()[0]
                : payload.message || (response.status === 429 ? t('Zu viele Meldungen. Bitte versuche es später erneut.') : t('Meldung konnte nicht gesendet werden.'));
            throw new Error(message);
        }

        closeReportModal();
        setReportMode(false);
        setStatus(t('Danke! Deine Meldung wurde gespeichert und wird geprüft.'));
    } catch (error) {
        reportStatus.textContent = error.message || t('Meldung konnte nicht gesendet werden.');
    } finally {
        reportSubmitBtn.disabled = false;
    }
});
canvas.addEventListener('pointerdown', handlePointerDown);
canvas.addEventListener('pointermove', handleCanvasHover);

let wheelPanX = 0;
let wheelPanY = 0;
let wheelPanFrame = null;
let wheelZoomLockedUntil = 0;

async function applyWheelPan() {
    wheelPanFrame = null;
    const stepX = Math.trunc(wheelPanX / displayScale);
    const stepY = Math.trunc(wheelPanY / displayScale);
    if (stepX === 0 && stepY === 0) {
        return;
    }
    wheelPanX -= stepX * displayScale;
    wheelPanY -= stepY * displayScale;

    viewOffsetX = clamp(viewOffsetX + stepX, 0, Math.max(0, wallWidth - viewWidth));
    viewOffsetY = clamp(viewOffsetY + stepY, 0, Math.max(0, wallHeight - viewHeight));
    positionWallBackgroundImages();
    renderCanvas();
    try {
        await loadWallTiles();
        positionWallBackgroundImages();
        renderCanvas();
    } catch (error) {
        console.error(error);
    }
}

// Zwei-Finger-Wischen auf dem Touchpad (und das Mausrad) verschiebt die Ansicht;
// Strg + Wheel (Pinch-Geste) zoomt in den vorhandenen Stufen.
canvas.addEventListener('wheel', (event) => {
    event.preventDefault();

    if (event.ctrlKey) {
        const now = performance.now();
        if (now < wheelZoomLockedUntil || event.deltaY === 0) {
            return;
        }
        wheelZoomLockedUntil = now + 180;
        const index = zoomLevels.indexOf(displayScale);
        const nextIndex = clamp(index + (event.deltaY < 0 ? 1 : -1), 0, zoomLevels.length - 1);
        if (nextIndex !== index) {
            changeZoom(nextIndex);
            loadWallTiles().then(() => {
                positionWallBackgroundImages();
                renderCanvas();
            }).catch((error) => console.error(error));
        }
        return;
    }

    const unit = event.deltaMode === 1 ? 16 : event.deltaMode === 2 ? canvasShell.clientHeight : 1;
    wheelPanX += (event.shiftKey && event.deltaX === 0 ? event.deltaY : event.deltaX) * unit;
    wheelPanY += (event.shiftKey && event.deltaX === 0 ? 0 : event.deltaY) * unit;
    wheelPanFrame ??= requestAnimationFrame(applyWheelPan);
}, { passive: false });

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
function setNewTilesAvailable(available) {
    newTilesAvailable = available;
    if (!wallReloadBtn) {
        return;
    }
    wallReloadBtn.classList.toggle('has-update', available);
    wallReloadBtn.querySelector('.wall-reload-badge')?.classList.toggle('hidden', !available);
    const label = available ? wallReloadBtn.dataset.labelNew : wallReloadBtn.dataset.label;
    wallReloadBtn.setAttribute('aria-label', label);
    const tip = wallReloadBtn.querySelector('.wall-reload-tip');
    if (tip) {
        tip.textContent = label;
    }
}

async function reloadWallBackground() {
    wallReloadBtn.disabled = true;
    try {
        const response = await apiFetch('/api/wall', { headers: { Accept: 'application/json' } });
        if (response.ok) {
            const dimensions = await response.json();
            knownTilesVersion = dimensions.tiles_version ?? knownTilesVersion;
            if (dimensions.width !== wallWidth || dimensions.height !== wallHeight) {
                wallWidth = dimensions.width;
                wallHeight = dimensions.height;
                updateCanvasDisplaySize();
            }
        }
        await Promise.all([...wallBackgroundTiles.keys()].map((key) => {
            const [x, y] = key.split(':').map(Number);
            return loadWallTile(x, y).catch(() => {});
        }));
        await loadWallTiles();
        // Die neuen Kacheln enthalten alte Overlay-Pixel bereits; nur noch nicht verarbeitete Pixel bleiben darüber.
        pixels.clear();
        await loadRecentPixels({ full: true });
        if (selection) {
            await loadPixelsForSelection(selection);
        }
        setNewTilesAvailable(false);
        renderCanvas();
    } catch (error) {
        console.error('Failed to reload wall background:', error);
    } finally {
        wallReloadBtn.disabled = false;
    }
}

wallReloadBtn?.addEventListener('click', reloadWallBackground);

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
setStatus(t('Klicke auf einen Pixel, um ihn auszuwählen und zu bearbeiten.'));
loadWallDimensions().catch((error) => {
    console.error(error);
    setStatus(error.message || t('Die Pixelwand konnte nicht geladen werden.'));
    showInitialViewportLoadFailure();
});
window.setInterval(refreshWallDimensions, 15000);
refreshPixelLocks();
window.setInterval(refreshPixelLocks, 15000);

// ---------------------------------------------------------------------------
// Limits (Popup bei zu früher Änderung) und Shop (Pixel kaufen)
// ---------------------------------------------------------------------------
const limitModal = document.getElementById('limitModal');
const limitText = document.getElementById('limitText');
const limitTitle = document.getElementById('limitTitle');
const limitCountdown = document.getElementById('limitCountdown');
const limitGuestActions = document.getElementById('limitGuestActions');
const limitUserActions = document.getElementById('limitUserActions');
const limitBuyBtn = document.getElementById('limitBuyBtn');
const shopModal = document.getElementById('shopModal');
const shopProductsEl = document.getElementById('shopProducts');
const shopExtensionsEl = document.getElementById('shopExtensions');
const shopProvidersEl = document.getElementById('shopProviders');
const shopCheckoutEl = document.getElementById('shopCheckout');
const shopGuestNotice = document.getElementById('shopGuestNotice');
const shopCreditsEl = document.getElementById('shopCredits');
const shopErrorEl = document.getElementById('shopError');
let limitTimer = null;
let shopData = null;
let shopSelected = null;

function formatDuration(totalSeconds) {
    const seconds = Math.max(0, Math.ceil(totalSeconds));
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    const pad = (value) => String(value).padStart(2, '0');

    return h > 0 ? `${h}:${pad(m)}:${pad(s)} ${t('Std.')}` : `${pad(m)}:${pad(s)} ${t('Min.')}`;
}

let latestPixelStatus = null;

function updatePixelCounter(status) {
    if (!status || !pixelCounter) {
        return;
    }
    latestPixelStatus = status;
    const total = status.free + (status.credits || 0);
    pixelCounterValue.textContent = `${status.free.toLocaleString(locale)}/${status.max_free.toLocaleString(locale)}`;
    if (pixelCounterExtra) {
        pixelCounterExtra.textContent = status.credits > 0 ? `+${status.credits.toLocaleString(locale)}` : '';
        pixelCounterExtra.classList.toggle('hidden', !(status.credits > 0));
    }
    pixelCounter.classList.toggle('empty', total === 0);
}

async function fetchPixelStatus() {
    try {
        const response = await apiFetch('/api/pixels/status', { headers: { Accept: 'application/json' } });
        const status = response.ok ? await response.json() : null;
        updatePixelCounter(status);

        return status;
    } catch (error) {
        console.error('Failed to load pixel status:', error);
        return null;
    }
}

window.setInterval(fetchPixelStatus, 10000);
fetchPixelStatus();

function renderNeighborhood() {
    if (!neighborhoodCanvas) {
        return;
    }
    const nctx = neighborhoodCanvas.getContext('2d');
    const center = hoverCell;
    nctx.imageSmoothingEnabled = false;
    nctx.fillStyle = '#0b0f16';
    nctx.fillRect(0, 0, neighborhoodCanvas.width, neighborhoodCanvas.height);
    if (!center) {
        return;
    }

    const size = neighborhoodCanvas.width / 3;
    for (let dy = -1; dy <= 1; dy++) {
        for (let dx = -1; dx <= 1; dx++) {
            const x = center.x + dx;
            const y = center.y + dy;
            if (x < 0 || y < 0 || x >= wallWidth || y >= wallHeight) {
                continue;
            }
            nctx.fillStyle = pixels.get(keyFor(x, y)) || getReferencePixelColor(x, y) || '#f8fafc';
            nctx.fillRect((dx + 1) * size, (dy + 1) * size, size, size);
        }
    }
    nctx.lineWidth = 2;
    nctx.strokeStyle = '#ffffff';
    nctx.strokeRect(size + 1, size + 1, size - 2, size - 2);
    nctx.strokeStyle = '#000000';
    nctx.lineWidth = 1;
    nctx.strokeRect(size + 3, size + 3, size - 6, size - 6);
    neighborhoodLabel.textContent = `${center.x} : ${center.y}`;
}

function describeQuota(status) {
    if (!status) {
        return '';
    }
    if (!status.authenticated) {
        return t('Gast-Vorrat: noch :free von :max Pixeln – danach mit kostenlosem Konto weiter.', { free: status.free.toLocaleString(locale), max: status.max_free.toLocaleString(locale) });
    }
    const extra = status.credits > 0 ? ` + ${t(':credits Extra-Pixel', { credits: status.credits.toLocaleString(locale) })}` : '';

    return t('Vorrat: :free / :max:extra.', { free: status.free.toLocaleString(locale), max: status.max_free.toLocaleString(locale), extra });
}

function hideLimitModal() {
    window.clearInterval(limitTimer);
    limitTimer = null;
    limitModal?.classList.add('hidden');
}

function showLimitModal(payload) {
    if (!limitModal) {
        return;
    }

    const isGuest = payload.limit === 'guest';
    limitTitle.textContent = isGuest ? t('Dein Gast-Vorrat von :max Pixeln ist aufgebraucht', { max: payload.max_free.toLocaleString(locale) }) : t('Vorrat leer');
    limitText.textContent = isGuest
        ? t('Melde dich kostenlos an oder registriere dich. Dann bekommst du alle 30 Sekunden ein neues Pixel (bis zu 50 im Vorrat).')
        : t('Alle 30 Sekunden wächst ein Pixel nach (maximal 50 im Vorrat). Das nächste Pixel ist verfügbar in:');
    limitGuestActions.classList.toggle('hidden', !isGuest);
    limitUserActions.classList.toggle('hidden', isGuest);
    limitCountdown.classList.toggle('hidden', isGuest);

    if (isGuest) {
        const note = document.createElement('p');
        note.className = 'limit-note';
        note.textContent = t('Mehr als 50 Pixel im Vorrat gibt es nur als Extra-Pixel im Shop.');
        limitText.appendChild(note);
        window.clearInterval(limitTimer);
        limitModal.classList.remove('hidden');
        return;
    }
    const endsAt = Date.now() + (payload.retry_after || 0) * 1000;
    const tick = () => {
        const remaining = (endsAt - Date.now()) / 1000;
        limitCountdown.textContent = remaining > 0 ? formatDuration(remaining) : t('Jetzt wieder möglich – klicke erneut auf einen Pixel.');
        if (remaining <= 0) {
            window.clearInterval(limitTimer);
        }
    };
    window.clearInterval(limitTimer);
    tick();
    limitTimer = window.setInterval(tick, 1000);
    limitModal.classList.remove('hidden');
}

function shopProductCopy(product) {
    if (product.type === 'extend') {
        const direction = product.axis === 'x' ? 'rechts' : 'unten';
        return {
            title: product.amount > 1
                ? t('Leinwand um :amount Pixel nach :direction erweitern', { amount: product.amount.toLocaleString(locale), direction: t(direction) })
                : t('Leinwand nach :direction erweitern', { direction: t(direction) }),
            badge: t('🌍 Für die ganze Community'),
            text: t('Die Leinwand wächst um :amount Pixel nach :direction. Der neue Platz steht ab sofort allen Spieler:innen zur Verfügung (im Hintergrundbild erscheint er nach bis zu 10 Minuten) – dein Beitrag für die Community.', { amount: product.amount.toLocaleString(locale), direction: t(direction) }),
        };
    }

    if (product.amount >= 1000) {
        return {
            title: t(':amount Extra-Pixel', { amount: product.amount.toLocaleString(locale) }),
            badge: t('🏆 Bestpreis'),
            text: t(':amount Pixel, die du sofort setzen kannst – ohne Wartezeit und über dem Vorrat von 50 hinaus. Der günstigste Preis pro Pixel.', { amount: product.amount.toLocaleString(locale) }),
        };
    }

    if (product.amount >= 600) {
        return {
            title: t(':amount Extra-Pixel', { amount: product.amount.toLocaleString(locale) }),
            badge: t('🔥 Beliebt'),
            text: t(':amount Pixel, die du sofort setzen kannst – ohne Wartezeit und über dem Vorrat von 50 hinaus. Das beste Verhältnis aus Preis und Menge.', { amount: product.amount.toLocaleString(locale) }),
        };
    }

    return {
        title: t(':amount Extra-Pixel', { amount: product.amount.toLocaleString(locale) }),
        badge: t('⚡ Starter'),
        text: t(':amount Pixel, die du sofort setzen kannst – ohne Wartezeit und über dem Vorrat von 50 hinaus. Perfekt zum Vergleichen und Ausprobieren.', { amount: product.amount.toLocaleString(locale) }),
    };
}

function shopSavingsPercent(product) {
    const pixelProducts = shopData.products.filter((item) => item.type === 'pixels');
    const base = Math.max(...pixelProducts.map((item) => item.price_cents / item.amount));
    const percent = Math.round((1 - product.price_cents / product.amount / base) * 100);
    return product.type === 'pixels' && percent > 0 ? percent : 0;
}

function renderShop() {
    shopProductsEl.replaceChildren();
    shopExtensionsEl.replaceChildren();
    shopProvidersEl.replaceChildren();
    shopErrorEl.classList.add('hidden');

    const order = { pixels: 0, extend: 1 };
    const sortedProducts = [...shopData.products].sort((a, b) => order[a.type] - order[b.type]);
    const isOverLimit = (product) => product.type === 'pixels' && shopData.authenticated && shopData.credits + product.amount > shopData.max_credits;
    const selectable = sortedProducts.find((product) => product.id === shopSelected && !isOverLimit(product))
        ?? sortedProducts.find((product) => !isOverLimit(product));
    shopSelected = selectable ? selectable.id : null;

    sortedProducts.forEach((product) => {
        const copy = shopProductCopy(product);
        const card = document.createElement('button');
        card.type = 'button';
        card.className = `shop-product shop-product-${product.type}`;
        const overLimit = isOverLimit(product);
        card.disabled = overLimit;
        card.dataset.product = product.id;
        card.setAttribute('aria-pressed', String(product.id === shopSelected));

        const badge = document.createElement('span');
        badge.className = 'shop-badge';
        badge.textContent = copy.badge;
        const title = document.createElement('strong');
        title.textContent = copy.title;
        const text = document.createElement('span');
        text.className = 'shop-product-text';
        text.textContent = copy.text;
        const price = document.createElement('span');
        price.className = 'shop-price';
        price.textContent = product.price;
        card.append(badge, title, text, price);
        if (overLimit) {
            const limitNote = document.createElement('span');
            limitNote.className = 'shop-limit-note';
            limitNote.textContent = t('Nicht möglich: Limit von :max Extra-Pixeln würde überschritten.', { max: shopData.max_credits.toLocaleString(locale) });
            card.appendChild(limitNote);
        }
        const savings = shopSavingsPercent(product);
        if (savings > 0) {
            const tag = document.createElement('span');
            tag.className = 'shop-tag';
            tag.textContent = `−${savings} %`;
            card.appendChild(tag);
        }
        card.addEventListener('click', () => {
            shopSelected = product.id;
            renderShop();
        });
        (product.type === 'extend' ? shopExtensionsEl : shopProductsEl).appendChild(card);
    });

    shopCreditsEl.textContent = shopData.authenticated ? t('Dein Guthaben: :credits von maximal :max Extra-Pixeln', { credits: shopData.credits.toLocaleString(locale), max: shopData.max_credits.toLocaleString(locale) }) : '';
    shopCheckoutEl.classList.toggle('hidden', !(shopSelected && shopData.authenticated));
    shopGuestNotice.classList.toggle('hidden', !(shopSelected && !shopData.authenticated));

    shopData.providers.forEach((provider) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `shop-provider shop-provider-${provider.key}`;
        button.setAttribute('aria-label', provider.label);
        if (provider.key === 'paypal' || provider.key === 'stripe') {
            const logo = document.createElement('img');
            logo.className = 'shop-provider-logo';
            logo.src = `/images/payments/${provider.key}.svg`;
            logo.alt = '';
            button.appendChild(logo);
        } else {
            const name = document.createElement('strong');
            name.textContent = provider.label;
            button.appendChild(name);
        }
        const hint = provider.key === 'stripe' ? provider.label : '';
        const mode = String(provider.mode || '').toLowerCase() === 'live' ? '' : provider.mode;
        const small = document.createElement('small');
        small.textContent = [hint, mode].filter(Boolean).join(' · ');
        if (small.textContent) {
            button.appendChild(small);
        }
        button.addEventListener('click', () => startCheckout(provider.key, button));
        shopProvidersEl.appendChild(button);
    });

    if (shopData.providers.length === 0) {
        shopErrorEl.textContent = t('Zurzeit ist kein Zahlungsanbieter eingerichtet.');
        shopErrorEl.classList.remove('hidden');
    }
}

async function openShop(productId = null) {
    if (!shopModal) {
        return;
    }

    hideLimitModal();
    shopSelected = productId;
    shopModal.classList.remove('hidden');
    try {
        const response = await apiFetch('/api/shop', { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            throw new Error(t('Shop konnte nicht geladen werden.'));
        }
        shopData = await response.json();
        renderShop();
    } catch (error) {
        console.error(error);
        shopErrorEl.textContent = error.message;
        shopErrorEl.classList.remove('hidden');
    }
}

async function startCheckout(provider, button) {
    shopErrorEl.classList.add('hidden');
    shopProvidersEl.querySelectorAll('button').forEach((item) => { item.disabled = true; });
    button.classList.add('is-loading');
    try {
        const response = await apiFetch('/shop/checkout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({ product: shopSelected, provider }),
        });
        const payload = await response.json();
        if (!response.ok || !payload.redirect) {
            throw new Error(payload.message || t('Die Zahlung konnte nicht gestartet werden.'));
        }
        window.location.href = payload.redirect;
    } catch (error) {
        shopErrorEl.textContent = error.message;
        shopErrorEl.classList.remove('hidden');
        shopProvidersEl.querySelectorAll('button').forEach((item) => { item.disabled = false; });
        button.classList.remove('is-loading');
    }
}

document.getElementById('openShopBtn')?.addEventListener('click', () => openShop());
document.getElementById('openExtensionShopBtn')?.addEventListener('click', async () => {
    await openShop();
    document.getElementById('shopExtensionTitle')?.scrollIntoView({ block: 'start' });
});
document.getElementById('closeShopBtn')?.addEventListener('click', () => shopModal.classList.add('hidden'));
document.getElementById('limitCloseBtn')?.addEventListener('click', hideLimitModal);
limitBuyBtn?.addEventListener('click', () => openShop('pixels_100'));
document.getElementById('purchaseBannerClose')?.addEventListener('click', () => {
    document.getElementById('purchaseBanner')?.remove();
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        hideLimitModal();
        shopModal?.classList.add('hidden');
    }
});
