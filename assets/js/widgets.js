// dateRangePicker.js

function createRangePickerButton({
    buttonEl,
    labelEl,
    startInput,
    endInput,
    onChange
}) {
    function fmtDate(d) {
        return d.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    function updateLabel(dates) {
        if (!dates.length) {
            labelEl.textContent = 'Today';
            return;
        }

        const sameDay =
            dates.length > 1 &&
            dates[0].toDateString() === dates[1].toDateString();

        labelEl.textContent =
            (dates.length === 1 || sameDay)
                ? fmtDate(dates[0])
                : `${fmtDate(dates[0])} – ${fmtDate(dates[1])}`;
    }

    return flatpickr(buttonEl, {
        mode: 'range',
        dateFormat: 'Y-m-d',
        
        defaultDate: startInput.value && endInput.value
            ? [startInput.value, endInput.value]
            : [new Date(), new Date()],

        onReady: (_, __, instance) => {
            updateLabel(instance.selectedDates);
        },

        onChange: (dates) => {
            updateLabel(dates);

            if (dates.length !== 2) return;

            const pad = n => String(n).padStart(2, '0');
            const toLocal = d =>
                `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;

            startInput.value = toLocal(dates[0]);
            endInput.value   = toLocal(dates[1]);

            if (onChange) onChange(dates);
        }
    });
}