// ==========================================================
// Lumen — editor page behaviors
// ==========================================================
document.addEventListener('DOMContentLoaded', () => {
    initTabs();
    initLivePreview();
    initDraftAutosave();
});

/* ---------- Write / Preview tabs (shown on narrow screens) ---------- */
function initTabs() {
    const tabs = document.querySelectorAll('.tab-btn');
    const panes = document.querySelectorAll('.pane');
    if (!tabs.length) return;

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => { t.classList.remove('active'); t.setAttribute('aria-selected', 'false'); });
            panes.forEach(p => p.classList.remove('active'));

            tab.classList.add('active');
            tab.setAttribute('aria-selected', 'true');
            document.querySelector(`.pane-${tab.dataset.tab}`).classList.add('active');
        });
    });
}

/* ---------- Live Markdown preview + word count ---------- */
function initLivePreview() {
    const textarea = document.getElementById('contentInput');
    const preview = document.getElementById('previewOutput');
    const wordCount = document.getElementById('wordCount');
    if (!textarea || !preview) return;

    const render = () => {
        const value = textarea.value.trim();
        preview.innerHTML = value
            ? markdownToHtml(textarea.value)
            : '<p class="preview-placeholder">Your preview will appear here as you type…</p>';

        const words = value ? value.split(/\s+/).filter(Boolean).length : 0;
        if (wordCount) wordCount.textContent = `${words} word${words === 1 ? '' : 's'} · ~${Math.max(1, Math.ceil(words / 200))} min read`;
    };

    let debounceTimer;
    textarea.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(render, 150);
    });

    render();
}

/* ---------- Draft autosave to localStorage (per post id / "new") ---------- */
function initDraftAutosave() {
    const form = document.getElementById('editorForm');
    const titleInput = document.getElementById('titleInput');
    const contentInput = document.getElementById('contentInput');
    const status = document.getElementById('draftStatus');
    if (!form || !titleInput || !contentInput) return;

    const key = 'lumen-draft-' + (new URLSearchParams(window.location.search).get('id') || 'new');

    // Offer to restore a saved draft if the fields are otherwise empty.
    try {
        const saved = JSON.parse(localStorage.getItem(key) || 'null');
        if (saved && !titleInput.value && !contentInput.value.trim()) {
            if (confirm('You have an unsaved draft for this post. Restore it?')) {
                titleInput.value = saved.title || '';
                contentInput.value = saved.content || '';
                contentInput.dispatchEvent(new Event('input'));
            }
        }
    } catch (e) { /* ignore corrupted draft data */ }

    let saveTimer;
    const saveDraft = () => {
        try {
            localStorage.setItem(key, JSON.stringify({ title: titleInput.value, content: contentInput.value }));
            if (status) {
                status.textContent = 'Draft saved';
                status.style.opacity = '1';
                clearTimeout(saveTimer);
                saveTimer = setTimeout(() => { status.style.opacity = '0'; }, 1500);
            }
        } catch (e) { /* localStorage unavailable — silently skip autosave */ }
    };

    let debounceTimer;
    [titleInput, contentInput].forEach(field => {
        field.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(saveDraft, 600);
        });
    });

}

/* ---------- Small client-side Markdown renderer (mirrors includes/markdown.php) ---------- */
function markdownToHtml(md) {
    const escapeHtml = (s) => s
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');

    const lines = md.replace(/\r\n/g, '\n').split('\n');
    const out = [];
    let inCode = false, codeBuf = [];
    let listType = null;
    let para = [];
    let quote = [];

    const flushPara = () => { if (para.length) { out.push('<p>' + para.join('<br>') + '</p>'); para = []; } };
    const flushList = () => { if (listType) { out.push(`</${listType}>`); listType = null; } };
    const flushQuote = () => { if (quote.length) { out.push('<blockquote><p>' + quote.join('<br>') + '</p></blockquote>'); quote = []; } };

    const inlineFmt = (text) => {
        text = escapeHtml(text);
        text = text.replace(/[\u0001\u0002]/g, ' ');
        const protectedHtml = [];
        const protect = (html) => {
            const token = `\u0001${protectedHtml.length}\u0002`;
            protectedHtml.push([token, html]);
            return token;
        };
        const safeUrl = (url, allowMailto = false) => {
            if (/[\u0000-\u0020\u007f]/.test(url)) return false;
            const scheme = url.match(/^([a-z][a-z0-9+.-]*):/i);
            return !scheme || ['http', 'https', ...(allowMailto ? ['mailto'] : [])].includes(scheme[1].toLowerCase());
        };

        text = text.replace(/`(.+?)`/g, (_, code) => protect(`<code>${code}</code>`));
        text = text.replace(/!\[(.*?)\]\((\S+)\)/g, (_, alt, url) =>
            safeUrl(url) ? protect(`<img src="${url}" alt="${alt}" loading="lazy">`) : alt
        );
        text = text.replace(/\[(.*?)\]\((\S+)\)/g, (_, label, url) =>
            safeUrl(url, true) ? protect(`<a href="${url}" target="_blank" rel="noopener noreferrer">${label}</a>`) : label
        );
        text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        text = text.replace(/(?<!\*)\*(?!\*)(.+?)\*(?!\*)/g, '<em>$1</em>');
        text = text.replace(/(?<![\p{L}\p{N}])_(?!_)(.+?)(?<!_)_(?![\p{L}\p{N}_])/gu, '<em>$1</em>');
        protectedHtml.forEach(([token, html]) => { text = text.replace(token, () => html); });
        return text;
    };

    for (const raw of lines) {
        if (/^\s*```/.test(raw)) {
            if (inCode) {
                out.push('<pre><code>' + codeBuf.join('\n') + '</code></pre>');
                codeBuf = []; inCode = false;
            } else {
                flushPara(); flushList(); flushQuote(); inCode = true;
            }
            continue;
        }
        if (inCode) { codeBuf.push(escapeHtml(raw)); continue; }

        const line = raw.trim();
        if (line === '') { flushPara(); flushList(); flushQuote(); continue; }

        if (/^(-{3,}|\*{3,})$/.test(line)) { flushPara(); flushList(); flushQuote(); out.push('<hr>'); continue; }

        let m;
        if ((m = line.match(/^(#{1,6})\s+(.*)$/))) {
            flushPara(); flushList(); flushQuote();
            out.push(`<h${m[1].length}>${inlineFmt(m[2])}</h${m[1].length}>`);
            continue;
        }
        if ((m = line.match(/^>\s?(.*)$/))) { flushPara(); flushList(); quote.push(inlineFmt(m[1])); continue; }
        if ((m = line.match(/^[-*]\s+(.*)$/))) {
            flushPara(); flushQuote();
            if (listType !== 'ul') { flushList(); out.push('<ul>'); listType = 'ul'; }
            out.push('<li>' + inlineFmt(m[1]) + '</li>');
            continue;
        }
        if ((m = line.match(/^\d+\.\s+(.*)$/))) {
            flushPara(); flushQuote();
            if (listType !== 'ol') { flushList(); out.push('<ol>'); listType = 'ol'; }
            out.push('<li>' + inlineFmt(m[1]) + '</li>');
            continue;
        }

        flushList(); flushQuote();
        para.push(inlineFmt(line));
    }

    if (inCode && codeBuf.length) out.push('<pre><code>' + codeBuf.join('\n') + '</code></pre>');
    flushPara(); flushList(); flushQuote();

    return out.join('\n');
}
