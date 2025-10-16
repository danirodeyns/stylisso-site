document.addEventListener('DOMContentLoaded', function () {
    const voucher2Form = document.getElementById("voucher2Form");
    const resultContainer = document.getElementById("voucher2_result");
    const errorContainer = document.getElementById("voucher2_error");
    const codeSpan = document.getElementById("voucher2_Code");
    const amountSpan = document.getElementById("voucher2_amount_set");
    const expiresSpan = document.getElementById("voucher2_Expires");
    const receiverSpan = document.getElementById("voucher2_Receiver");
    const submitBtn = document.getElementById("voucher2_createBtn");

    if (!voucher2Form) return;

    // --- Foutmelding tonen ---
    function showError(msg) {
        errorContainer.textContent = msg;
        errorContainer.classList.remove("hidden");
    }

    // --- Voucher aanmaken via fetch ---
    async function createVoucher(amount, reason, assign = "") {
        try {
        let url = `create_own_voucher.php?amount=${encodeURIComponent(amount)}&reason=${encodeURIComponent(reason)}`;
        if (assign) url += `&assign=${encodeURIComponent(assign)}`;

        const response = await fetch(url);
        const data = await response.json();

        if (data.success && data.voucher) {
            // --- Resultaat invullen ---
            codeSpan.textContent = data.voucher.code;
            amountSpan.textContent = data.voucher.amount.toFixed(2);
            expiresSpan.textContent = data.voucher.expires_at;

            // --- Assigned user correct tonen ---
            if (data.user && data.user.id) {
            receiverSpan.textContent = `${data.user.id} - ${data.user.name}`;
            } else {
            receiverSpan.textContent = "-";
            }

            // --- Resultaat zichtbaar maken ---
            resultContainer.classList.remove("hidden");

            // --- Factuurlink toevoegen indien aanwezig ---
            if (data.invoice && data.invoice.success && data.invoice.file) {
            const oldLink = resultContainer.querySelector("a");
            if (oldLink) oldLink.remove();

            const link = document.createElement("a");
            link.href = "invoices/" + data.invoice.file;
            link.textContent = "📄 Download interne factuur";
            link.target = "_blank";
            link.style.display = "block";
            link.style.marginTop = "1rem";
            resultContainer.appendChild(link);
            }
        } else {
            showError(data.message || "Er is iets misgegaan bij het aanmaken.");
        }
        } catch (err) {
        showError("Serverfout: " + err.message);
        } finally {
        submitBtn.disabled = false;
        }
    }

    // --- Event listener voor formulier ---
    voucher2Form.addEventListener("submit", (e) => {
        e.preventDefault(); // voorkom page reload

        errorContainer.classList.add("hidden");
        resultContainer.classList.add("hidden");

        const amount = parseFloat(document.getElementById("voucher2_amount").value);
        const reason = document.getElementById("voucher2_reason").value.trim();
        const assign = document.getElementById("create_own_voucher_user_id").value.trim();

        if (isNaN(amount) || amount <= 0) return showError("Voer een geldig bedrag in.");
        if (reason.length < 3) return showError("Geef een duidelijke reden op voor de uitgifte.");

        submitBtn.disabled = true;

        createVoucher(amount, reason, assign);
    });
});