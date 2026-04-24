
// ===== GANTT JS =====
function initGanttCursors() {
    const tooltip     = document.getElementById('gantt_tooltip');
    const gtSched     = document.getElementById('gt-sched');
    const gtActualIn  = document.getElementById('gt-actual-in');
    const gtActualOut = document.getElementById('gt-actual-out');
    const gtEarlyRow = document.getElementById('gt-early-row');
    const gtEarly    = document.getElementById('gt-early');
    const gtLateRow   = document.getElementById('gt-late-row');
    const gtLate      = document.getElementById('gt-late');
    const gtOtRow     = document.getElementById('gt-ot-row');
    const gtOt        = document.getElementById('gt-ot');
    const gtUtRow = document.getElementById('gt-ut-row');
    const gtUt    = document.getElementById('gt-ut');

    document.querySelectorAll('.ganttBarContainer').forEach(container => {
        const line  = container.querySelector('.ganttCursorLine');
        const label = container.querySelector('.ganttCursorLabel');
        if (!line || !label) return;

        const rangeStart = parseInt(container.dataset.rangeStart);
        const rangeEnd   = parseInt(container.dataset.rangeEnd);
        const range      = rangeEnd - rangeStart;

        const hasData = !!container.dataset.actualIn;

        container.addEventListener('mousemove', (e) => {
            const rect    = container.getBoundingClientRect();
            const x       = e.clientX - rect.left;
            const percent = Math.max(0, Math.min(1, x / rect.width));
            const time    = Math.floor(rangeStart + (percent * range));

            if (line)  line.style.left  = (percent * 100) + '%';
            if (label) label.style.left = (percent * 100) + '%';

            if (label) {
                label.textContent = new Date(time * 1000).toLocaleTimeString('en-US', {
                    hour: 'numeric', minute: '2-digit', hour12: true, timeZone: 'Asia/Manila'
                });
            }

            if (hasData && tooltip) {
                gtSched.textContent     = container.dataset.schedIn + ' – ' + container.dataset.schedOut;
                gtActualIn.textContent  = container.dataset.actualIn;
                gtActualOut.textContent = container.dataset.actualOut;

                // Show if early
                if (container.dataset.early) {
                    gtEarly.textContent      = container.dataset.early;
                    gtEarlyRow.style.display = 'flex';
                } else {
                    gtEarlyRow.style.display = 'none';
                }
                
                // Show if late
                if (container.dataset.late) {
                    gtLate.textContent          = container.dataset.late;
                    gtLateRow.style.display     = 'flex';
                } else {
                    gtLateRow.style.display = 'none';
                }

                // Show overtime if it is approved or rejected
                if (container.dataset.overtime) {
                    gtOt.textContent  = container.dataset.overtime;
                    const status      = container.dataset.overtimeStatus;
                    gtOtRow.className = 'ganttToolTipRow ganttToolTipOverTime'
                                    + (status === 'approved' ? ' approved' : status === 'rejected' ? ' rejected' : '');
                    gtOtRow.style.display = 'flex';
                } else {
                    gtOtRow.style.display = 'none';
                }
                
                // Show undertime only when it is the next day (day is finished)
                const isToday = container.dataset.isToday === '1';

                if (container.dataset.undertime && !isToday) {
                    gtUt.textContent = container.dataset.undertime;
                    gtUtRow.style.display = 'flex';
                } else {
                    gtUtRow.style.display = 'none';
                }

                tooltip.style.left = e.clientX + 'px';
                tooltip.style.top  = e.clientY  + 'px';
                tooltip.classList.add('visible');
            }
        });

        container.addEventListener('mouseleave', () => {
            if (tooltip) tooltip.classList.remove('visible');
        });
    });
}

function refreshGantt() {
    const container = document.querySelector('.ganttContainer');
    if (!container) return;

    // Fade out current rows
    container.style.transition = 'opacity 0.2s ease';
    container.style.opacity    = '0';

    // Build URL preserving the current date range
    const params = new URLSearchParams(window.location.search);
    const start  = params.get('start') ?? '';
    const end    = params.get('end')   ?? '';
    const url    = window.location.pathname + (start && end ? `?start=${start}&end=${end}` : '');

    setTimeout(() => {
        fetch(url)
            .then(r => r.text())
            .then(html => {
                const doc      = new DOMParser().parseFromString(html, 'text/html');
                const newGantt = doc.querySelector('.ganttContainer');
                if (!newGantt || !container) return;

                // Swap in the new container
                container.replaceWith(newGantt);

                // Stagger fade-in each row
                newGantt.style.opacity    = '0';
                newGantt.style.transition = 'opacity 0.3s ease';

                newGantt.querySelectorAll('.ganttRow').forEach((row, i) => {
                    row.style.opacity    = '0';
                    row.style.transform  = 'translateY(6px)';
                    row.style.transition = `opacity 0.3s ease ${i * 40}ms, transform 0.3s ease ${i * 40}ms`;

                    requestAnimationFrame(() => requestAnimationFrame(() => {
                        row.style.opacity   = '1';
                        row.style.transform = 'translateY(0)';
                    }));
                });

                newGantt.style.opacity = '1';

                // Re-initialize cursor and tooltip behavior on the new rows
                initGanttCursors();
            })
            .catch(err => console.error('Gantt refresh failed:', err));
    }, 200); // Wait for fade-out to finish before swapping
}