document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.table-scroll-wrapper, .tableScroll, .cutoff-table-scroll').forEach(function (wrapper) {
        const zone = wrapper.closest('.card') || wrapper;

        let nearScrollbar = false;
        let targetLeft = wrapper.scrollLeft;
        let targetTop = wrapper.scrollTop;
        let rafId = null;

        zone.addEventListener('mousemove', function (e) {
            nearScrollbar = e.clientY > wrapper.getBoundingClientRect().bottom - 50; // change 40 to increase/decrease the detection area (px from bottom)
        });
        zone.addEventListener('mouseleave', function () { nearScrollbar = false; });

        wrapper.addEventListener('scroll', function () {
            if (!rafId) {
                targetLeft = wrapper.scrollLeft;
                targetTop = wrapper.scrollTop;
            }
        });

        function animate() {
            const diffX = targetLeft - wrapper.scrollLeft;
            const diffY = targetTop - wrapper.scrollTop;

            if (Math.abs(diffX) < 0.5 && Math.abs(diffY) < 0.5) {
                wrapper.scrollLeft = targetLeft;
                wrapper.scrollTop = targetTop;
                rafId = null;
                return;
            }

            if (Math.abs(diffX) >= 0.5) wrapper.scrollLeft += diffX * 0.12;
            if (Math.abs(diffY) >= 0.5) wrapper.scrollTop += diffY * 0.12;

            rafId = requestAnimationFrame(animate);
        }

        function addDeltaX(delta) {
            const max = wrapper.scrollWidth - wrapper.clientWidth;
            targetLeft = Math.max(0, Math.min(max, targetLeft + delta));
            if (!rafId) rafId = requestAnimationFrame(animate);
        }

        function addDeltaY(delta) {
            const max = wrapper.scrollHeight - wrapper.clientHeight;
            targetTop = Math.max(0, Math.min(max, targetTop + delta));
            if (!rafId) rafId = requestAnimationFrame(animate);
        }

        zone.addEventListener('wheel', function (e) {
            if (e.target.closest('.dropdown-menu')) return;

            const hasH = wrapper.scrollWidth > wrapper.clientWidth;
            const hasV = wrapper.scrollHeight > wrapper.clientHeight;

            if (!hasH && !hasV) return;

            // trackpad horizontal swipe → smooth horizontal
            if (e.deltaX !== 0) {
                e.preventDefault();
                addDeltaX(e.deltaX);
                return;
            }

            // only horizontal exists → redirect wheel to horizontal
            if (hasH && !hasV) {
                e.preventDefault();
                addDeltaX(e.deltaY);
                return;
            }

            // both axes, near scrollbar → redirect to horizontal
            if (hasH && nearScrollbar) {
                e.preventDefault();
                addDeltaX(e.deltaY);
                return;
            }

            // vertical scroll available → smooth vertical
            e.preventDefault();
            addDeltaY(e.deltaY);
        }, { passive: false });
    });
});
