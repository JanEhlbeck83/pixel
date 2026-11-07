const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

document.querySelectorAll('.community-crop').forEach((crop) => {
    const form = crop.closest('form');
    const file = form.querySelector('input[type="file"]');
    const source = crop.querySelector('.community-crop-source');
    const preview = crop.querySelector('.community-crop-preview');
    const status = crop.querySelector('.community-crop-status');
    const xField = crop.querySelector('[name="crop_x"]');
    const yField = crop.querySelector('[name="crop_y"]');
    const width = Number(crop.dataset.width);
    const height = Number(crop.dataset.height);
    const context = source.getContext('2d');
    const previewContext = preview.getContext('2d');
    let image;
    let loading = false;
    let imageError = false;
    let generation = 0;
    const redraw = () => {
        if (!image) return;
        const x = Number(xField.value);
        const y = Number(yField.value);
        const valid = Number.isInteger(x) && Number.isInteger(y) && x >= 0 && y >= 0
            && x + width <= image.width && y + height <= image.height;
        xField.setCustomValidity(valid ? '' : crop.dataset.sizeError);
        source.hidden = false;
        preview.hidden = !valid;
        context.clearRect(0, 0, source.width, source.height);
        context.drawImage(image, 0, 0);
        if (valid) {
            context.fillStyle = 'rgba(0, 0, 0, 0.5)';
            context.fillRect(0, 0, source.width, source.height);
            context.clearRect(x, y, width, height);
            context.drawImage(image, x, y, width, height, x, y, width, height);
            context.strokeStyle = '#ff2d95';
            context.lineWidth = Math.max(1, source.width / 600);
            context.strokeRect(x, y, width, height);
            previewContext.clearRect(0, 0, width, height);
            previewContext.imageSmoothingEnabled = false;
            previewContext.drawImage(image, x, y, width, height, 0, 0, width, height);
        }
        status.textContent = valid ? `${image.width} × ${image.height} px · X ${x}, Y ${y} · ${width} × ${height} px` : crop.dataset.sizeError;
    };
    const load = (url, reset = false, revoke = false) => {
        const currentGeneration = ++generation;
        loading = true;
        imageError = false;
        const next = new Image();
        next.onload = () => {
            if (revoke) URL.revokeObjectURL(url);
            if (currentGeneration !== generation) return;
            loading = false;
            image = next;
            if (image.width > 4096 || image.height > 4096) {
                imageError = true;
                status.textContent = crop.dataset.maxError;
                source.hidden = preview.hidden = true;
                return;
            }
            source.width = image.width;
            source.height = image.height;
            if (reset) xField.value = yField.value = '0';
            xField.max = String(Math.max(0, image.width - width));
            yField.max = String(Math.max(0, image.height - height));
            redraw();
        };
        next.onerror = () => {
            if (revoke) URL.revokeObjectURL(url);
            if (currentGeneration !== generation) return;
            loading = false;
            imageError = true;
            status.textContent = crop.dataset.loadError;
            source.hidden = preview.hidden = true;
        };
        next.src = url;
    };
    file?.addEventListener('change', () => {
        if (file.files[0]) {
            load(URL.createObjectURL(file.files[0]), true, true);
        } else if (crop.dataset.imageUrl) {
            load(crop.dataset.imageUrl);
        } else {
            generation++;
            image = null;
            loading = imageError = false;
            xField.value = yField.value = '0';
            xField.setCustomValidity('');
            source.hidden = preview.hidden = true;
            status.textContent = '';
        }
    });
    xField.addEventListener('input', redraw);
    yField.addEventListener('input', redraw);
    source.addEventListener('pointerdown', (event) => {
        if (!image || image.width < width || image.height < height) return;
        const bounds = source.getBoundingClientRect();
        xField.value = String(Math.max(0, Math.min(image.width - width, Math.floor((event.clientX - bounds.left) * image.width / bounds.width))));
        yField.value = String(Math.max(0, Math.min(image.height - height, Math.floor((event.clientY - bounds.top) * image.height / bounds.height))));
        redraw();
    });
    form.addEventListener('submit', (event) => {
        if (loading || imageError) {
            event.preventDefault();
            if (loading) status.textContent = crop.dataset.loadError;
        }
    });
    const initialUrl = crop.dataset.imageUrl;
    if (initialUrl) load(initialUrl);
});

document.querySelectorAll('[data-community-zoom]').forEach((container) => {
    const canvas = container.querySelector('canvas');
    const viewport = container.querySelector('.community-canvas-viewport');
    const zoomOut = container.querySelector('[data-zoom-out]');
    const zoomReset = container.querySelector('[data-zoom-reset]');
    const zoomIn = container.querySelector('[data-zoom-in]');
    const levels = [1, 1.5, 2, 3, 4, 6, 8, 10, 12, 14, 16];
    let index = levels.length - 1;
    const updateZoom = (nextIndex) => {
        const previousZoom = levels[index];
        const centerX = (viewport.scrollLeft + viewport.clientWidth / 2) / previousZoom;
        const centerY = (viewport.scrollTop + viewport.clientHeight / 2) / previousZoom;
        index = Math.max(0, Math.min(levels.length - 1, nextIndex));
        const zoom = levels[index];
        canvas.style.width = `${canvas.width * zoom}px`;
        canvas.style.height = `${canvas.height * zoom}px`;
        viewport.scrollLeft = centerX * zoom - viewport.clientWidth / 2;
        viewport.scrollTop = centerY * zoom - viewport.clientHeight / 2;
        zoomOut.disabled = index === 0;
        zoomIn.disabled = index === levels.length - 1;
        zoomReset.textContent = `${zoom * 100}%`;
        zoomReset.setAttribute('aria-label', zoomReset.dataset.label.replace(':percent', zoom * 100));
    };
    zoomOut.addEventListener('click', () => updateZoom(index - 1));
    zoomReset.addEventListener('click', () => updateZoom(0));
    zoomIn.addEventListener('click', () => updateZoom(index + 1));
    viewport.addEventListener('wheel', (event) => {
        if (!event.ctrlKey) return;
        event.preventDefault();
        updateZoom(index + (event.deltaY < 0 ? 1 : -1));
    }, { passive: false });
    updateZoom(index);
});

function communityCanvasPoint(canvas, event) {
    const bounds = canvas.getBoundingClientRect();
    const x = Math.floor((event.clientX - bounds.left) * canvas.width / bounds.width);
    const y = Math.floor((event.clientY - bounds.top) * canvas.height / bounds.height);
    return x >= 0 && y >= 0 && x < canvas.width && y < canvas.height ? { x, y } : null;
}

function communityCanvasCursor(canvas, inspect) {
    const tooltip = document.createElement('span');
    tooltip.className = 'community-canvas-cursor';
    tooltip.hidden = true;
    document.body.append(tooltip);
    const hide = () => { tooltip.hidden = true; };
    canvas.addEventListener('pointermove', (event) => {
        const point = communityCanvasPoint(canvas, event);
        const sample = point && inspect(point.x, point.y);
        if (!sample || event.pointerType === 'touch') {
            hide();
            return;
        }
        tooltip.textContent = `X ${point.x}, Y ${point.y} · ${sample}`;
        tooltip.hidden = false;
        tooltip.style.left = `${Math.max(4, Math.min(event.clientX + 16, document.documentElement.clientWidth - tooltip.offsetWidth - 8))}px`;
        tooltip.style.top = `${Math.max(4, Math.min(event.clientY + 16, window.innerHeight - tooltip.offsetHeight - 80))}px`;
    });
    canvas.addEventListener('pointerleave', hide);
    canvas.addEventListener('pointerdown', hide);
    document.addEventListener('scroll', hide, true);
    window.addEventListener('blur', hide);
}

document.querySelectorAll('.community-reference').forEach((reference) => {
    const canvas = reference.querySelector('canvas');
    const context = canvas.getContext('2d', { willReadFrequently: true });
    const status = reference.querySelector('[data-reference-status]');
    const pick = reference.querySelector('[data-reference-pick]');
    let sample;
    let ready = false;
    let picking = true;
    const updatePicking = () => {
        pick.setAttribute('aria-pressed', String(picking));
        canvas.classList.toggle('is-picking', picking);
    };
    pick.addEventListener('click', () => {
        picking = !picking;
        updatePicking();
    });
    updatePicking();
    const load = () => {
        const image = new Image();
        image.onload = () => {
            context.clearRect(0, 0, canvas.width, canvas.height);
            context.drawImage(image, 0, 0);
            ready = true;
            sample = null;
            pick.disabled = false;
            status.textContent = '';
        };
        image.onerror = () => {
            ready = false;
            pick.disabled = true;
            status.textContent = reference.dataset.loadError;
        };
        image.src = reference.dataset.imageUrl;
    };
    const inspect = (x, y) => {
        if (!ready) return null;
        x = Math.max(0, Math.min(canvas.width - 1, x));
        y = Math.max(0, Math.min(canvas.height - 1, y));
        const [r, g, b, alpha] = context.getImageData(x, y, 1, 1).data;
        const color = `#${[r, g, b].map((value) => value.toString(16).padStart(2, '0')).join('')}`;
        sample = alpha === 0 ? null : { x, y, color };
        status.textContent = `X ${x}, Y ${y} · ${alpha === 0 ? reference.dataset.transparent : color}`;
        return alpha === 0 ? reference.dataset.transparent : color;
    };
    const inspectPointer = (event) => {
        const point = communityCanvasPoint(canvas, event);
        if (!point) return;
        inspect(point.x, point.y);
    };
    communityCanvasCursor(canvas, inspect);
    const selectSample = () => {
        if (!picking || !sample || !ready) return;
        const selected = { ...sample };
        picking = false;
        updatePicking();
        document.dispatchEvent(new CustomEvent('community-reference-pick', { detail: selected }));
        status.textContent = `X ${selected.x}, Y ${selected.y} · ${selected.color}`;
    };
    canvas.addEventListener('click', (event) => {
        inspectPointer(event);
        selectSample();
    });
    canvas.addEventListener('keydown', (event) => {
        const directions = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };
        if (directions[event.key]) {
            event.preventDefault();
            const [dx, dy] = directions[event.key];
            inspect((sample?.x ?? 0) + dx, (sample?.y ?? 0) + dy);
        } else if (event.key === 'Enter' && !pick.disabled) {
            selectSample();
        }
    });
    load();
    window.setInterval(load, 30000);
});

document.querySelectorAll('.community-project-preview').forEach((preview) => {
    const canvas = preview.querySelector('canvas');
    const context = canvas.getContext('2d');
    const status = preview.querySelector('.community-project-preview-status');
    context.imageSmoothingEnabled = false;

    fetch(preview.dataset.pixelsUrl, { headers: { Accept: 'application/json' } })
        .then((response) => {
            if (!response.ok) throw new Error('Der Projektstand konnte nicht geladen werden.');
            return response.json();
        })
        .then((data) => {
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, data.width, data.height);
            data.pixels.forEach((pixel) => {
                context.fillStyle = pixel.color;
                context.fillRect(pixel.x, pixel.y, 1, 1);
            });
        })
        .catch((error) => {
            status.textContent = error.message;
            console.error(error);
        });
});

let phaseReloadRequested = false;
document.querySelectorAll('.community-phase-countdown').forEach((countdown) => {
    const deadline = new Date(countdown.dataset.countdownUntil).getTime();
    if (!Number.isFinite(deadline)) {
        throw new Error('Invalid community project phase deadline.');
    }
    const serverTime = countdown.dataset.countdownNow
        ? new Date(countdown.dataset.countdownNow).getTime()
        : Date.now();
    if (!Number.isFinite(serverTime)) {
        throw new Error('Invalid community project server time.');
    }
    const clockOffset = Date.now() - serverTime;

    let timer;
    const updateCountdown = () => {
        const remaining = Math.max(0, deadline - (Date.now() - clockOffset));
        const totalSeconds = Math.ceil(remaining / 1000);
        const days = Math.floor(totalSeconds / 86400);
        const hours = Math.floor((totalSeconds % 86400) / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;
        countdown.textContent = remaining === 0
            ? countdown.dataset.countdownExpired
            : countdown.dataset.countdownTemplate
            .replace(':days', String(days))
            .replace(':hours', String(hours))
            .replace(':minutes', countdown.hasAttribute('data-countdown-reload') ? String(minutes).padStart(2, '0') : String(minutes))
            .replace(':seconds', countdown.hasAttribute('data-countdown-reload') ? String(seconds).padStart(2, '0') : String(seconds));
        if (remaining === 0 && timer) {
            window.clearInterval(timer);
        }
        if (remaining === 0 && countdown.hasAttribute('data-countdown-reload') && !phaseReloadRequested) {
            phaseReloadRequested = true;
            window.location.reload();
        }
    };
    timer = window.setInterval(updateCountdown, 1000);
    updateCountdown();
});

const chat = document.querySelector('.community-chat');
if (chat) {
    const messages = chat.querySelector('.community-chat-messages');
    const olderButton = chat.querySelector('.community-chat-older');
    const status = chat.querySelector('.community-chat-status');
    let newestId = 0;
    let oldestId = 0;
    let loading = false;

    const renderMessage = (message) => {
        const item = document.createElement('li');
        const heading = document.createElement('strong');
        const body = document.createElement('p');
        const time = document.createElement('time');
        heading.textContent = message.user;
        body.textContent = message.message;
        time.dateTime = message.created_at;
        time.textContent = new Intl.DateTimeFormat(undefined, { dateStyle: 'short', timeStyle: 'short' }).format(new Date(message.created_at));
        item.append(heading, time, body);
        return item;
    };

    const loadMessages = async ({ beforeId = 0, afterId = 0, prepend = false } = {}) => {
        if (loading) return;
        loading = true;
        try {
            const url = new URL(chat.dataset.chatUrl, window.location.origin);
            if (beforeId) url.searchParams.set('before_id', String(beforeId));
            if (afterId) url.searchParams.set('after_id', String(afterId));
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Der Projektchat konnte nicht geladen werden.');
            const data = await response.json();
            const fragment = document.createDocumentFragment();
            data.messages.forEach((message) => fragment.append(renderMessage(message)));
            if (prepend) {
                messages.prepend(fragment);
            } else {
                messages.append(fragment);
            }
            if (data.messages.length) {
                oldestId = oldestId ? Math.min(oldestId, data.messages[0].id) : data.messages[0].id;
                newestId = Math.max(newestId, data.messages[data.messages.length - 1].id);
            }
            if (!afterId) olderButton.hidden = !data.has_older;
            if (!prepend) messages.scrollTop = messages.scrollHeight;
            status.textContent = '';
        } catch (error) {
            status.textContent = error.message;
            console.error(error);
        } finally {
            loading = false;
        }
    };

    olderButton.addEventListener('click', () => loadMessages({ beforeId: oldestId, prepend: true }));
    loadMessages();
    window.setInterval(() => {
        if (newestId) loadMessages({ afterId: newestId });
    }, 5000);

    const form = chat.querySelector('.community-chat-form');
    if (form) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submit = form.querySelector('button[type="submit"]');
            const field = form.elements.message;
            submit.disabled = true;
            try {
                const response = await fetch(chat.dataset.sendUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ message: field.value }),
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || data.errors?.message?.[0] || 'Die Nachricht konnte nicht gesendet werden.');
                field.value = '';
                if (data.message.id > newestId) {
                    messages.append(renderMessage(data.message));
                    newestId = data.message.id;
                    if (!oldestId) oldestId = data.message.id;
                    messages.scrollTop = messages.scrollHeight;
                }
                status.textContent = '';
            } catch (error) {
                status.textContent = error.message;
            } finally {
                submit.disabled = false;
            }
        });
    }
}

const editor = document.querySelector('.community-project-editor');
if (editor) {
    const canvas = editor.querySelector('#communityProjectCanvas');
    const context = canvas.getContext('2d');
    const status = editor.querySelector('#communityProjectStatus');
    const width = Number(editor.dataset.width);
    const height = Number(editor.dataset.height);
    const editable = editor.dataset.editable === '1';
    const pixels = new Map();
    let activeColor = '#ff6b6b';
    let painting = false;
    let target;
    const colorInput = editor.querySelector('[data-paint-color]');
    const hexInput = editor.querySelector('[data-paint-hex]');
    const activeColorLabel = editor.querySelector('[data-paint-active]');
    const coordinateStatus = editor.querySelector('[data-paint-coordinates]');
    const picks = editor.querySelectorAll('[data-paint-pick]');
    const selection = editor.querySelector('[data-paint-selection]');
    const viewport = canvas.closest('.community-canvas-viewport');
    const paintMode = editor.querySelector('[data-paint-mode]');
    const balance = editor.querySelector('[data-paint-balance]');
    const colorSwatch = editor.querySelector('[data-game-color-swatch]');
    const mobileControls = window.matchMedia('(max-width: 760px), (max-width: 1024px) and (max-height: 500px)');
    let mobilePainting = false;
    let picking = false;
    const updateTouchMode = () => {
        painting = false;
        const canPaint = !mobileControls.matches || mobilePainting;
        canvas.classList.toggle('is-navigating', !canPaint && !picking);
        paintMode.setAttribute('aria-pressed', String(mobilePainting));
        paintMode.textContent = mobilePainting ? paintMode.dataset.panLabel : paintMode.dataset.paintLabel;
    };
    const updatePicking = () => {
        painting = false;
        picks.forEach((button) => button.setAttribute('aria-pressed', String(picking)));
        canvas.classList.toggle('is-picking', picking);
        updateTouchMode();
    };
    paintMode.addEventListener('click', () => {
        if (!editable) return;
        mobilePainting = !mobilePainting;
        picking = false;
        updatePicking();
        status.textContent = mobilePainting ? paintMode.dataset.paintHelp : paintMode.dataset.panHelp;
    });
    mobileControls.addEventListener('change', () => {
        mobilePainting = false;
        picking = false;
        updatePicking();
    });
    updateTouchMode();
    if (mobileControls.matches && editable) status.textContent = paintMode.dataset.panHelp;
    picks.forEach((button) => button.addEventListener('click', () => {
        picking = !picking;
        updatePicking();
    }));

    context.imageSmoothingEnabled = false;

    const render = () => {
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, width, height);
        pixels.forEach((color, key) => {
            const [x, y] = key.split(':').map(Number);
            context.fillStyle = color;
            context.fillRect(x, y, 1, 1);
        });
        if (width <= 200 && height <= 200) {
            context.strokeStyle = 'rgba(0, 0, 0, 0.12)';
            context.lineWidth = 0.025;
            for (let x = 0; x <= width; x++) {
                context.beginPath();
                context.moveTo(x, 0);
                context.lineTo(x, height);
                context.stroke();
            }
            for (let y = 0; y <= height; y++) {
                context.beginPath();
                context.moveTo(0, y);
                context.lineTo(width, y);
                context.stroke();
            }
        }
        if (target) {
            context.strokeStyle = '#ff2d95';
            context.lineWidth = 0.2;
            context.strokeRect(target.x + 0.1, target.y + 0.1, 0.8, 0.8);
        }
    };

    const setColor = (color) => {
        activeColor = color.toLowerCase();
        colorInput.value = activeColor;
        if (document.activeElement !== hexInput) hexInput.value = activeColor;
        activeColorLabel.textContent = activeColor;
        activeColorLabel.style.background = activeColor;
        colorSwatch.style.background = activeColor;
        editor.querySelectorAll('.community-color').forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.color === activeColor));
        });
    };
    const quotaStatus = editor.querySelector('[data-paint-quota]');
    let quotaAvailable = false;
    let pixelsLoaded = false;
    let quotaLoading = false;
    let saving = false;
    const showQuota = (quota) => {
        quotaAvailable = quota.can_edit;
        balance.textContent = `${quota.free}/${quota.max_free}${quota.credits ? ` +${quota.credits}` : ''}`;
        quotaStatus.textContent = editor.dataset.quotaTemplate
            .replace(':free', quota.free).replace(':max', quota.max_free).replace(':credits', quota.credits);
    };
    const loadQuota = async () => {
        if (quotaLoading || saving || document.hidden) return;
        quotaLoading = true;
        try {
            const response = await fetch(editor.dataset.quotaUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) throw new Error(editor.dataset.quotaError);
            showQuota(await response.json());
        } catch (error) {
            quotaAvailable = false;
            quotaStatus.textContent = editor.dataset.quotaError;
            console.error(error);
        } finally {
            quotaLoading = false;
        }
    };
    loadQuota();
    window.setInterval(loadQuota, 5000);
    document.addEventListener('visibilitychange', loadQuota);
    colorInput.addEventListener('input', () => setColor(colorInput.value));
    hexInput.addEventListener('input', () => {
        const value = hexInput.value.trim().replace(/^#?/, '#');
        if (/^#[0-9a-f]{6}$/i.test(value)) setColor(value);
    });
    hexInput.addEventListener('blur', () => { hexInput.value = activeColor; });
    setColor(activeColor);
    const rememberSelection = (pixel, jump = false) => {
        target = pixel;
        setColor(target.color);
        selection.hidden = false;
        const label = jump ? selection.dataset.referenceLabel : selection.dataset.canvasLabel;
        selection.textContent = `${label}: X ${target.x}, Y ${target.y} · ${target.color}`;
        selection.style.setProperty('--picked-color', target.color);
        coordinateStatus.textContent = selection.textContent;
        picking = false;
        mobilePainting = true;
        updatePicking();
        render();
        if (jump) {
            const scaleX = canvas.getBoundingClientRect().width / width;
            const scaleY = canvas.getBoundingClientRect().height / height;
            viewport.scrollLeft = (target.x + 0.5) * scaleX - viewport.clientWidth / 2;
            viewport.scrollTop = (target.y + 0.5) * scaleY - viewport.clientHeight / 2;
            editor.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };
    document.addEventListener('community-reference-pick', (event) => {
        rememberSelection(event.detail, true);
    });
    communityCanvasCursor(canvas, (x, y) => pixelsLoaded ? pixels.get(`${x}:${y}`) || '#ffffff' : null);
    canvas.addEventListener('pointermove', (event) => {
        const bounds = canvas.getBoundingClientRect();
        const x = Math.floor((event.clientX - bounds.left) * width / bounds.width);
        const y = Math.floor((event.clientY - bounds.top) * height / bounds.height);
        coordinateStatus.textContent = `X ${x}, Y ${y} · ${activeColor}`;
    });
    render();
    fetch(editor.dataset.pixelsUrl, { headers: { Accept: 'application/json' } })
        .then(async (response) => {
            if (!response.ok) {
                throw new Error((await response.json()).message || 'Projektbild konnte nicht geladen werden.');
            }
            return response.json();
        })
        .then((data) => {
            data.pixels.forEach((pixel) => pixels.set(`${pixel.x}:${pixel.y}`, pixel.color));
            pixelsLoaded = true;
            picks.forEach((button) => { button.disabled = false; });
            render();
        })
        .catch((error) => {
            status.textContent = error.message;
            console.error(error);
        });

    editor.querySelectorAll('.community-color').forEach((button) => {
        button.addEventListener('click', () => {
            setColor(button.dataset.color);
        });
    });

    const paint = async (event) => {
        if (!editable || !painting || picking || saving || !quotaAvailable || !pixelsLoaded) {
            return;
        }
        const bounds = canvas.getBoundingClientRect();
        const x = Math.floor(((event.clientX - bounds.left) / bounds.width) * width);
        const y = Math.floor(((event.clientY - bounds.top) / bounds.height) * height);
        if (x < 0 || y < 0 || x >= width || y >= height) {
            return;
        }
        const key = `${x}:${y}`;
        const previous = pixels.get(key);
        if (previous === activeColor) return;
        saving = true;
        pixels.set(key, activeColor);
        render();
        try {
            const response = await fetch(editor.dataset.paintUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ x, y, color: activeColor }),
            });
            const data = await response.json();
            if (!response.ok) {
                if (response.status === 429) showQuota(data);
                throw new Error(data.message || data.errors?.project?.[0] || 'Pixel konnte nicht gespeichert werden.');
            }
            showQuota(data.quota);
            status.textContent = '';
        } catch (error) {
            if (previous === undefined) {
                pixels.delete(key);
            } else {
                pixels.set(key, previous);
            }
            render();
            status.textContent = error.message;
        } finally {
            saving = false;
        }
    };

    canvas.addEventListener('pointerdown', (event) => {
        if (event.button !== undefined && event.button !== 0) return;
        if (picking) {
            const point = communityCanvasPoint(canvas, event);
            if (point && pixelsLoaded && !saving) {
                rememberSelection({ ...point, color: pixels.get(`${point.x}:${point.y}`) || '#ffffff' });
            }
            return;
        }
        if (editable && (!mobileControls.matches || mobilePainting)) {
            painting = true;
            canvas.setPointerCapture(event.pointerId);
            paint(event);
        }
    });
    if (editable) {
        canvas.addEventListener('pointermove', (event) => {
            if (painting) {
                paint(event);
            }
        });
        canvas.addEventListener('pointerup', () => { painting = false; });
        canvas.addEventListener('pointercancel', () => { painting = false; });
    } else {
        canvas.classList.add('is-readonly');
    }
}
