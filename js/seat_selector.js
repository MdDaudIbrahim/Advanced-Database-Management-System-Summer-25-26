// js/seat_selector.js — Interactive seat grid logic

document.addEventListener('DOMContentLoaded', function () {
    const seatGrid    = document.getElementById('seatGrid');
    const seatInput   = document.getElementById('selectedSeat');
    const proceedBtn  = document.getElementById('proceedBtn');
    const summaryType = document.getElementById('summaryType');
    const summarySeat = document.getElementById('summarySeat');
    const summaryTotal= document.getElementById('summaryTotal');
    const ticketType  = document.getElementById('ticketType');
    const priceInput  = document.getElementById('ticketPrice');

    const PRICES = { Standard: 200, VIP: 500, Premium: 800 };

    if (!seatGrid) return;

    let selectedSeat = null;

    // Click seat
    seatGrid.querySelectorAll('.seat-btn:not(.occupied)').forEach(btn => {
        btn.addEventListener('click', function () {
            // Deselect previous
            seatGrid.querySelectorAll('.seat-btn.selected').forEach(b => {
                b.classList.remove('selected');
                b.classList.add('available');
            });
            // Select this
            this.classList.remove('available');
            this.classList.add('selected');
            selectedSeat = parseInt(this.dataset.seat);

            // Update form
            seatInput.value = selectedSeat;
            summarySeat.textContent = 'Seat ' + selectedSeat;
            updateSummary();
            if (proceedBtn) proceedBtn.disabled = false;
        });
    });

    // Ticket type changes price
    if (ticketType) {
        ticketType.addEventListener('change', function () {
            updateSummary();
        });
    }

    // Load booked seats dynamically when match changes
    const matchPicker = document.getElementById('matchPicker');
    if (matchPicker) {
        matchPicker.addEventListener('change', function () {
            const mid = this.value;
            if (!mid) return;
            // Reset selection
            selectedSeat = null;
            if (seatInput) seatInput.value = '';
            if (proceedBtn) proceedBtn.disabled = true;
            if (summarySeat) summarySeat.textContent = '—';

            // AJAX: get booked seats for this match
            fetch(`/ALL CODES/ADMS STMS/api/get_booked_seats.php?match_id=${mid}`)
                .then(r => r.json())
                .then(data => {
                    seatGrid.querySelectorAll('.seat-btn').forEach(btn => {
                        const sno = parseInt(btn.dataset.seat);
                        if (data.booked.includes(sno)) {
                            btn.className = 'seat-btn occupied';
                            btn.disabled = true;
                        } else {
                            btn.className = 'seat-btn available';
                            btn.disabled = false;
                        }
                    });
                })
                .catch(() => {});
        });
    }

    function updateSummary() {
        const type  = ticketType ? ticketType.value : 'Standard';
        const price = PRICES[type] || 200;
        if (priceInput)  priceInput.value = price;
        if (summaryType) summaryType.textContent = type;
        if (summaryTotal) summaryTotal.textContent = '৳' + price;
    }

    updateSummary();
});
