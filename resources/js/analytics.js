const endpoint = document.querySelector('meta[name="analytics-heartbeat-url"]')?.content;
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

if (endpoint && csrfToken) {
    let activeSince = document.visibilityState === 'visible' ? performance.now() : null;

    const send = (seconds, keepalive = false) => {
        if (seconds < 1) return;

        fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ seconds: Math.min(30, seconds) }),
        }).catch((error) => console.error('Could not record anonymous analytics heartbeat.', error));
    };

    const flush = (keepalive = false) => {
        if (activeSince === null) return;

        const now = performance.now();
        const seconds = Math.min(30, Math.floor((now - activeSince) / 1000));
        activeSince = now;
        send(seconds, keepalive);
    };

    window.setInterval(() => {
        if (document.visibilityState === 'visible') flush();
    }, 15000);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            activeSince = performance.now();
        } else {
            flush(true);
            activeSince = null;
        }
    });

    window.addEventListener('pagehide', () => flush(true));
}
