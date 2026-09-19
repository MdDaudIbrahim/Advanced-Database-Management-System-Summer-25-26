// js/payment.js — Interactive Payment Gateway Selection & Form Handling
// Sports Tournament Management System (STMS)

document.addEventListener('DOMContentLoaded', function () {
    const methodItems       = document.querySelectorAll('.payment-method-item');
    const selectedMethodInp = document.getElementById('selectedPayMethod');
    const cardFields        = document.getElementById('cardFields');
    const mfsFields         = document.getElementById('mfsFields');
    const cashFields        = document.getElementById('cashFields');
    const sectionTitle      = document.getElementById('detailsSectionTitle');
    const mfsLabel          = document.getElementById('mfsNumberLabel');
    const submitBtn         = document.getElementById('submitPaymentBtn');
    const paymentForm       = document.getElementById('paymentForm');

    const cardNumInput      = document.getElementById('cardNumber');
    const expiryInput       = document.getElementById('expiryDate');
    const cvvInput          = document.getElementById('cvv');
    const cardNameInput     = document.getElementById('cardholderName');
    const mfsNumInput       = document.getElementById('mfsNumber');
    const mfsPinInput       = document.getElementById('mfsPin');

    // Handle Payment Method Selection
    methodItems.forEach(item => {
        item.addEventListener('click', function () {
            const method = this.getAttribute('data-method');
            if (typeof window.selectPaymentMethod === 'function') {
                window.selectPaymentMethod(method, this);
            } else {
                methodItems.forEach(i => i.classList.remove('active'));
                this.classList.add('active');

                if (selectedMethodInp) selectedMethodInp.value = method;

                if (method === 'Cash') {
                    if (cardFields) cardFields.style.display = 'none';
                    if (mfsFields)  mfsFields.style.display  = 'none';
                    if (cashFields) cashFields.style.display = 'block';

                    if (sectionTitle) sectionTitle.textContent = 'Cash Payment Details';

                    [cardNumInput, expiryInput, cvvInput, cardNameInput, mfsNumInput, mfsPinInput].forEach(inp => {
                        if (inp) { inp.removeAttribute('required'); inp.disabled = true; }
                    });
                } else if (method === 'bKash' || method === 'Nagad') {
                    if (cardFields) cardFields.style.display = 'none';
                    if (cashFields) cashFields.style.display = 'none';
                    if (mfsFields)  mfsFields.style.display  = 'block';

                    if (sectionTitle) sectionTitle.textContent = `${method} Payment Details`;
                    if (mfsLabel)     mfsLabel.textContent     = `${method} Mobile Number`;

                    [cardNumInput, expiryInput, cvvInput, cardNameInput].forEach(inp => {
                        if (inp) { inp.removeAttribute('required'); inp.disabled = true; }
                    });
                    if (mfsNumInput) { mfsNumInput.disabled = false; mfsNumInput.setAttribute('required', 'required'); }
                    if (mfsPinInput) { mfsPinInput.disabled = false; mfsPinInput.setAttribute('required', 'required'); }
                } else {
                    if (cardFields) cardFields.style.display = 'block';
                    if (mfsFields)  mfsFields.style.display  = 'none';
                    if (cashFields) cashFields.style.display = 'none';

                    if (sectionTitle) sectionTitle.textContent = 'Payment Details';

                    [cardNumInput, expiryInput, cvvInput, cardNameInput].forEach(inp => {
                        if (inp) { inp.disabled = false; inp.setAttribute('required', 'required'); }
                    });
                    [mfsNumInput, mfsPinInput].forEach(inp => {
                        if (inp) { inp.removeAttribute('required'); inp.disabled = true; }
                    });
                }
            }
        });
    });

    // Card Number Auto-formatting: #### #### #### ####
    if (cardNumInput) {
        cardNumInput.addEventListener('input', function (e) {
            let val = e.target.value.replace(/\D/g, '').substring(0, 16);
            let formatted = val.match(/.{1,4}/g)?.join(' ') || val;
            e.target.value = formatted;
        });
    }

    // Expiry Date Auto-formatting: MM / YY
    if (expiryInput) {
        expiryInput.addEventListener('input', function (e) {
            let val = e.target.value.replace(/\D/g, '').substring(0, 4);
            if (val.length >= 3) {
                e.target.value = val.substring(0, 2) + ' / ' + val.substring(2);
            } else {
                e.target.value = val;
            }
        });
    }

    // CVV Auto-formatting (digits only)
    if (cvvInput) {
        cvvInput.addEventListener('input', function (e) {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
        });
    }

    // MFS Number formatting (digits only, max 11)
    if (mfsNumInput) {
        mfsNumInput.addEventListener('input', function (e) {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 11);
        });
    }

    // Submit Payment Handling
    if (submitBtn && paymentForm) {
        submitBtn.addEventListener('click', function () {
            if (!paymentForm.checkValidity()) {
                paymentForm.reportValidity();
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = `
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="spin">
                    <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                    <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"></path>
                </svg>
                Processing Payment...
            `;

            paymentForm.submit();
        });
    }
});
