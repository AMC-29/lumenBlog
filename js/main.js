document.addEventListener('DOMContentLoaded', () => {
    initNavToggle();
    initToasts();
    initScrollReveal();
    clearSavedEditorDraft();
    initLogoutConfirm();
    initEngagement();
    initAccountDrawer();
});

function initAccountDrawer() {
    const trigger = document.getElementById('accountMenuTrigger');
    const drawer = document.getElementById('accountDrawer');
    const backdrop = document.getElementById('accountDrawerBackdrop');
    const closeButton = document.getElementById('accountDrawerClose');
    if (!trigger || !drawer || !backdrop || !closeButton) return;

    let previousOverflow = '';
    let closeTimer;
    const open = () => {
        window.clearTimeout(closeTimer);
        previousOverflow = document.body.style.overflow;
        backdrop.hidden = false;
        drawer.hidden = false;
        document.body.style.overflow = 'hidden';
        trigger.setAttribute('aria-expanded', 'true');
        requestAnimationFrame(() => {
            backdrop.classList.add('visible');
            drawer.classList.add('open');
            closeButton.focus();
        });
    };
    const close = () => {
        backdrop.classList.remove('visible');
        drawer.classList.remove('open');
        document.body.style.overflow = previousOverflow;
        trigger.setAttribute('aria-expanded', 'false');
        closeTimer = window.setTimeout(() => {
            backdrop.hidden = true;
            drawer.hidden = true;
            trigger.focus();
        }, 240);
    };

    trigger.addEventListener('click', open);
    closeButton.addEventListener('click', close);
    backdrop.addEventListener('click', close);
    drawer.querySelectorAll('a').forEach(link => link.addEventListener('click', close));
    document.addEventListener('keydown', event => {
        if (drawer.hidden) return;
        if (event.key === 'Escape') {
            close();
            return;
        }
        if (event.key === 'Tab') {
            const focusable = [...drawer.querySelectorAll('a[href], button:not([disabled])')];
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });
}

function clearSavedEditorDraft() {
    if (typeof window.__clearEditorDraft !== 'string') return;
    try {
        localStorage.removeItem('lumen-draft-' + window.__clearEditorDraft);
    } catch (e) { /* localStorage unavailable — nothing to clear */ }
}

function initEngagement() {
    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-api], .report-comment');
        if (!button) return;
        if (!window.__loggedIn) {
            window.location.href = 'login.php';
            return;
        }

        if (button.classList.contains('report-comment')) {
            const reason = prompt('Why are you reporting this comment?');
            if (!reason) return;
            const body = new URLSearchParams({ comment_id: button.dataset.commentId, reason });
            try {
                const response = await fetch('api/report-comment.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrfToken(), 'Content-Type': 'application/x-www-form-urlencoded' },
                    body,
                    credentials: 'same-origin'
                });
                const result = await response.json();
                if (!response.ok || !result.ok) throw new Error(result.error || 'Could not submit report.');
                showToast('Report submitted.', 'success');
                button.disabled = true;
                button.textContent = 'Reported';
            } catch (error) {
                showToast(error.message || 'Could not submit report.', 'error');
            }
            return;
        }

        if (button.dataset.busy === 'true') return;
        const action = button.dataset.api;
        const postId = button.dataset.postId;
        const authorId = button.dataset.authorId;
        const field = action === 'follow' ? 'authorId' : 'postId';
        const value = action === 'follow' ? authorId : postId;
        const group = document.querySelectorAll(`[data-api="${action}"][data-${field.replace(/[A-Z]/g, m => '-' + m.toLowerCase())}="${CSS.escape(value)}"]`);
        const controls = group.length ? [...group] : [button];
        const oldActive = button.getAttribute('aria-pressed') === 'true';
        const nextActive = !oldActive;
        const countEls = action === 'like'
            ? controls.flatMap(control => [...control.querySelectorAll('[data-count]')])
            : action === 'follow' ? [document.getElementById('followerCount')].filter(Boolean) : [];
        const oldCount = countEls.length ? Number(countEls[0].textContent) : null;

        controls.forEach(control => {
            control.dataset.busy = 'true';
            control.setAttribute('aria-pressed', String(nextActive));
            control.classList.toggle('active', nextActive);
            if (action === 'bookmark') control.textContent = nextActive ? 'Bookmarked' : 'Bookmark';
            if (action === 'follow') control.textContent = nextActive ? 'Unfollow' : 'Follow';
        });
        if (oldCount !== null) countEls.forEach(el => { el.textContent = String(Math.max(0, oldCount + (nextActive ? 1 : -1))); });

        try {
            const body = new URLSearchParams({ [field === 'authorId' ? 'author_id' : 'post_id']: value });
            const response = await fetch(`api/${action}.php`, {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken(), 'Content-Type': 'application/x-www-form-urlencoded' },
                body,
                credentials: 'same-origin'
            });
            if (response.status === 401) {
                window.location.href = 'login.php';
                return;
            }
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.error || 'Could not update this item.');
            controls.forEach(control => {
                control.setAttribute('aria-pressed', String(result.active));
                control.classList.toggle('active', result.active);
                if (action === 'bookmark') control.textContent = result.active ? 'Bookmarked' : 'Bookmark';
                if (action === 'follow') control.textContent = result.active ? 'Unfollow' : 'Follow';
            });
            if (action === 'like') {
                document.querySelectorAll(`[data-api="like"][data-post-id="${CSS.escape(postId)}"] [data-count]`).forEach(el => { el.textContent = String(result.count); });
            } else if (action === 'follow' && countEls.length) {
                countEls.forEach(el => { el.textContent = String(result.count); });
            }
        } catch (error) {
            controls.forEach(control => {
                control.setAttribute('aria-pressed', String(oldActive));
                control.classList.toggle('active', oldActive);
                if (action === 'bookmark') control.textContent = oldActive ? 'Bookmarked' : 'Bookmark';
                if (action === 'follow') control.textContent = oldActive ? 'Unfollow' : 'Follow';
            });
            if (oldCount !== null) countEls.forEach(el => { el.textContent = String(oldCount); });
            showToast(error.message || 'Could not update this item.', 'error');
        } finally {
            controls.forEach(control => { delete control.dataset.busy; });
        }
    });
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

/* ---------- Logout confirmation modal ---------- */
function initLogoutConfirm() {
    const link = document.getElementById('logoutLink');
    const modal = document.getElementById('logoutModal');
    const cancelBtn = document.getElementById('logoutCancel');
    if (!link || !modal || !cancelBtn) return;

    const open = (e) => {
        e.preventDefault();
        modal.hidden = false;
        cancelBtn.focus();
    };
    const close = () => {
        modal.hidden = true;
        link.focus();
    };

    link.addEventListener('click', open);
    cancelBtn.addEventListener('click', close);
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.hidden) close();
    });
}

/* ---------- Mobile nav toggle ---------- */
function initNavToggle() {
    const toggle = document.getElementById('navToggle');
    const nav = document.getElementById('siteNav');
    if (!toggle || !nav) return;

    toggle.addEventListener('click', () => {
        const isOpen = nav.classList.toggle('open');
        toggle.setAttribute('aria-expanded', String(isOpen));
    });

    // Close the menu when a link inside it is clicked (mobile UX nicety)
    nav.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            nav.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        });
    });
}

/* ---------- Flash message toasts ---------- */
function initToasts() {
    const stack = document.getElementById('toastStack');
    if (!stack || !Array.isArray(window.__flashes)) return;

    window.__flashes.forEach((f, i) => {
        setTimeout(() => showToast(f.message, f.type), i * 150);
    });
}

function showToast(message, type = 'success') {
    const stack = document.getElementById('toastStack');
    if (!stack) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    stack.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('leaving');
        setTimeout(() => toast.remove(), 300);
    }, 4200);
}

/* ---------- Scroll-reveal for post cards ---------- */
function initScrollReveal() {
    const cards = document.querySelectorAll('.reveal');
    if (!cards.length) return;

    if (!('IntersectionObserver' in window)) {
        cards.forEach(c => c.classList.add('revealed'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('revealed');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

    cards.forEach(card => observer.observe(card));
}
