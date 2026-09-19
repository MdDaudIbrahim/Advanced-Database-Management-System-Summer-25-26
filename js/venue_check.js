// js/venue_check.js
// Real-time AJAX venue conflict check for Schedule Match page

document.addEventListener('DOMContentLoaded', function () {
    const venueSelect = document.getElementById('venueSelect');
    const matchDate   = document.getElementById('matchDate');
    const matchTime   = document.getElementById('matchTime');
    const conflictBanner = document.getElementById('venueConflictBanner');
    const okBanner       = document.getElementById('venueOkBanner');
    const submitBtn      = document.getElementById('submitBtn');

    if (!venueSelect) return;

    let checkTimeout = null;

    function checkVenueConflict() {
        const vid  = venueSelect.value;
        const date = matchDate ? matchDate.value : '';
        const time = matchTime ? matchTime.value : '';

        if (!vid || !date || !time) {
            conflictBanner.style.display = 'none';
            okBanner.style.display = 'none';
            return;
        }

        clearTimeout(checkTimeout);
        checkTimeout = setTimeout(() => {
            fetch(`/ALL CODES/ADMS STMS/api/check_venue.php?venue_id=${vid}&date=${date}&time=${time}`)
                .then(r => r.json())
                .then(data => {
                    if (data.conflict) {
                        conflictBanner.style.display = 'flex';
                        okBanner.style.display = 'none';
                        if (submitBtn) submitBtn.disabled = true;
                    } else {
                        conflictBanner.style.display = 'none';
                        okBanner.style.display = 'flex';
                        if (submitBtn) submitBtn.disabled = false;
                    }
                })
                .catch(() => {
                    conflictBanner.style.display = 'none';
                    okBanner.style.display = 'none';
                    if (submitBtn) submitBtn.disabled = false;
                });
        }, 400); // debounce 400ms
    }

    venueSelect.addEventListener('change', checkVenueConflict);
    if (matchDate) matchDate.addEventListener('change', checkVenueConflict);
    if (matchTime) matchTime.addEventListener('change', checkVenueConflict);
});
