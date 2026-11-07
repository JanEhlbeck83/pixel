const configElement = document.getElementById('projectPhaseNoticeConfig');

if (configElement) {
    const config = JSON.parse(configElement.textContent);
    const storageKey = `pixriot-phase-notices-${config.userId}`;
    let state = null;
    try {
        const stored = JSON.parse(sessionStorage.getItem(storageKey));
        if (stored && typeof stored.statuses === 'object' && stored.statuses !== null
            && Array.isArray(stored.pending)) {
            state = stored;
        }
    } catch (error) {
        console.warn('Project phase notification state could not be restored.', error);
    }
    if (state) {
        state.recruitmentSeen = Array.isArray(state.recruitmentSeen) ? state.recruitmentSeen : [];
        state.recruitmentPending = Array.isArray(state.recruitmentPending) ? state.recruitmentPending : [];
    }
    const save = () => {
        try {
            sessionStorage.setItem(storageKey, JSON.stringify(state));
        } catch (error) {
            console.warn('Project phase notification state could not be saved.', error);
        }
    };
    const region = document.createElement('aside');
    region.className = 'project-phase-notices';
    region.setAttribute('aria-label', config.heading);
    const announcements = document.createElement('div');
    announcements.setAttribute('role', 'status');
    announcements.setAttribute('aria-live', 'polite');
    const errorStatus = document.createElement('p');
    errorStatus.className = 'project-phase-notice';
    errorStatus.hidden = true;
    errorStatus.setAttribute('role', 'status');
    region.append(announcements, errorStatus);
    document.body.append(region);

    let projects = [];
    let previousProjects = '';
    const render = () => {
        announcements.replaceChildren();
        for (const project of projects.filter((item) => state.pending.includes(item.id) || state.recruitmentPending.includes(item.id))) {
            const recruitment = state.recruitmentPending.includes(project.id);
            const notice = document.createElement('section');
            notice.className = 'project-phase-notice';
            const heading = document.createElement('strong');
            heading.textContent = config.heading;
            const message = document.createElement('p');
            message.textContent = recruitment ? project.recruitment_message : project.message;
            const actions = document.createElement('div');
            actions.className = 'project-phase-notice-actions';
            for (const [label, url] of [[project.join_url ? config.joinLink : config.projectLink, project.join_url || project.url], [config.groupLink, project.group_url]]) {
                const link = document.createElement('a');
                link.textContent = label;
                link.href = url;
                actions.append(link);
            }
            const dismiss = document.createElement('button');
            dismiss.type = 'button';
            dismiss.className = 'secondary-button';
            dismiss.textContent = config.dismiss;
            dismiss.addEventListener('click', () => {
                state.pending = state.pending.filter((id) => id !== project.id);
                state.recruitmentPending = state.recruitmentPending.filter((id) => id !== project.id);
                save();
                render();
            });
            actions.append(dismiss);
            notice.append(heading, message, actions);
            announcements.append(notice);
        }
    };
    let running = false;
    let stopped = false;
    const poll = async () => {
        if (running || stopped || document.hidden) return;
        running = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);
        try {
            const response = await fetch(config.url, {
                headers: { Accept: 'application/json', 'X-App-Locale': config.locale },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: controller.signal,
            });
            if ([401, 403, 419].includes(response.status)) {
                stopped = true;
                state = { statuses: {}, pending: [], recruitmentSeen: [], recruitmentPending: [] };
                save();
                region.remove();
                return;
            }
            if (!response.ok) throw new Error(`Project phase updates: HTTP ${response.status}`);
            const data = await response.json();
            if (data.user_id !== config.userId) {
                stopped = true;
                state = { statuses: {}, pending: [], recruitmentSeen: [], recruitmentPending: [] };
                save();
                region.remove();
                return;
            }
            projects = data.projects;
            const statuses = Object.fromEntries(projects.map((project) => [project.id, project.status]));
            const pending = state ? state.pending.filter((id) => Object.hasOwn(statuses, id) && statuses[id] !== 'recruiting') : [];
            const recruitmentSeen = state ? state.recruitmentSeen.filter((id) => Object.hasOwn(statuses, id)) : [];
            const recruitmentPending = state ? state.recruitmentPending.filter((id) =>
                projects.some((project) => project.id === id && project.recruitment_reminder)) : [];
            for (const project of projects) {
                if (project.recruitment_reminder && !recruitmentSeen.includes(project.id)) {
                    recruitmentSeen.push(project.id);
                    recruitmentPending.push(project.id);
                }
            }
            if (state) {
                for (const project of projects) {
                    if (project.status !== 'recruiting' && state.statuses[project.id] !== project.status && !pending.includes(project.id)) {
                        pending.push(project.id);
                    }
                }
            }
            const changed = !state || JSON.stringify(state.statuses) !== JSON.stringify(statuses)
                || JSON.stringify(state.pending) !== JSON.stringify(pending)
                || JSON.stringify(state.recruitmentPending) !== JSON.stringify(recruitmentPending);
            const messagesChanged = JSON.stringify(projects) !== previousProjects;
            previousProjects = JSON.stringify(projects);
            state = { statuses, pending, recruitmentSeen, recruitmentPending };
            save();
            errorStatus.hidden = true;
            if (changed || messagesChanged || !announcements.childElementCount) render();
        } catch (error) {
            console.warn('Project phase updates could not be loaded.', error);
            errorStatus.textContent = config.error;
            errorStatus.hidden = false;
        } finally {
            clearTimeout(timeout);
            running = false;
        }
    };
    poll();
    setInterval(poll, 30000);
    document.addEventListener('visibilitychange', poll);
}
