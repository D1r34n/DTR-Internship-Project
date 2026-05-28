/* Shared utilities for all report pages */

function parseDateString(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr + 'T00:00:00')
        .toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function parseTimeString(timeStr) {
    if (!timeStr) return '—';
    const parts = timeStr.split(':');
    if (parts.length < 2) return '—';
    let hrs = parseInt(parts[0], 10);
    const mins = parts[1];
    const ampm = hrs >= 12 ? 'PM' : 'AM';
    hrs = hrs % 12 || 12;
    return `${hrs}:${mins} ${ampm}`;
}

function _fmtRange(start, end) {
    if (!start || !end) return '';
    const s = new Date(start + 'T00:00:00');
    const e = new Date(end   + 'T00:00:00');
    return s.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + ' – ' +
           e.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

/*
 * Shared pagination renderer.
 * Each page wraps this with its own currentPageIndex and fetch function:
 *   function renderPaginationControls(totalPages) {
 *       _renderPaginationControls(totalPages, currentPageIndex, fetchXxxReport, n => { currentPageIndex = n; });
 *   }
 */
function _renderPaginationControls(totalPages, pageIndex, fetchFn, setPageFn) {
    const list        = document.getElementById('paginationList');
    const jumpInput   = document.getElementById('page-jump-input');
    const jumpWrapper = document.getElementById('page-jump-wrapper');

    list.innerHTML = '';

    if (totalPages <= 1) {
        jumpWrapper?.style.setProperty('display', 'none', 'important');
        return;
    }
    jumpWrapper?.setAttribute('style', 'display:flex !important');

    if (jumpInput) { jumpInput.max = totalPages; jumpInput.value = pageIndex; }

    const prevLi = document.createElement('li');
    prevLi.className = `page-item ${pageIndex === 1 ? 'disabled' : ''}`;
    prevLi.innerHTML = `<a class="page-link" href="#">&laquo;</a>`;
    if (pageIndex > 1) {
        prevLi.addEventListener('click', e => { e.preventDefault(); setPageFn(pageIndex - 1); fetchFn(); });
    }
    list.appendChild(prevLi);

    const maxVisible = 5;
    let startPage = Math.max(1, pageIndex - 2);
    let endPage   = Math.min(totalPages, pageIndex + 2);
    if (pageIndex <= 3)              endPage   = Math.min(totalPages, maxVisible);
    if (pageIndex > totalPages - 3)  startPage = Math.max(1, totalPages - maxVisible + 1);

    if (startPage > 1) { appendPage(1); if (startPage > 2) appendEllipsis(); }
    for (let i = startPage; i <= endPage; i++) appendPage(i);
    if (endPage < totalPages) { if (endPage < totalPages - 1) appendEllipsis(); appendPage(totalPages); }

    const nextLi = document.createElement('li');
    nextLi.className = `page-item ${pageIndex === totalPages ? 'disabled' : ''}`;
    nextLi.innerHTML = `<a class="page-link" href="#">&raquo;</a>`;
    if (pageIndex < totalPages) {
        nextLi.addEventListener('click', e => { e.preventDefault(); setPageFn(pageIndex + 1); fetchFn(); });
    }
    list.appendChild(nextLi);

    function appendPage(n) {
        const li = document.createElement('li');
        li.className = `page-item ${pageIndex === n ? 'active' : ''}`;
        li.innerHTML = `<a class="page-link" href="#">${n}</a>`;
        li.addEventListener('click', e => { e.preventDefault(); setPageFn(n); fetchFn(); });
        list.appendChild(li);
    }
    function appendEllipsis() {
        const li = document.createElement('li');
        li.className = 'page-item disabled';
        li.innerHTML = `<span class="page-link text-meta">...</span>`;
        list.appendChild(li);
    }
}
