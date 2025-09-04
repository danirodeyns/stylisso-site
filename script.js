console.log("Stylisso site loaded.");

document.addEventListener('DOMContentLoaded', function () {

  // --- CSRF-token ophalen ---
  fetch('csrf.php')
    .then(res => res.json())
    .then(data => {
      if (!data.csrf_token) throw new Error('Geen CSRF-token ontvangen');
      window.csrfToken = data.csrf_token;

      // Vul alle forms met CSRF-token
      document.querySelectorAll('form').forEach(form => {
        const csrfInput = form.querySelector('input[name="csrf_token"]');
        if (csrfInput) csrfInput.value = data.csrf_token;
      });

      console.log("CSRF-token ingesteld:", data.csrf_token);
    })
    .catch(err => console.error('CSRF-token kon niet opgehaald worden', err));

  // --- Header inladen via fetch ---
  fetch('header.html')
    .then(res => res.text())
    .then(html => {
      const headerContainer = document.getElementById('header-placeholder');
      if (!headerContainer) return;
      headerContainer.innerHTML = html;

      const cartDropdown = document.getElementById('cartDropdown');

      // --- Setup user controls ---
      setupHeaderUserControls(headerContainer);

      // --- Cookie banner initialiseren ---
      initCookieBanner();

      // --- Cart fetchen ---
      fetchCart(cartDropdown);
    });

  // --- fetchCart functie (gecombineerd) ---
  function fetchCart(cartDropdown) {
    fetch('cart.php?action=get_cart')
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          renderCartItems(data.cart);
          renderCartDropdown(data.cart, cartDropdown);
        } else {
          console.error('Fout bij ophalen winkelwagen:', data.message);
        }
      })
      .catch(err => console.error('Fout bij ophalen cart:', err));
  }

  // --- renderCartItems (grote winkelwagenpagina) ---
  const cartItemsContainer = document.getElementById('cart-items');
  const subtotalDisplay = document.getElementById('cart-subtotal');
  const cartSummary = document.getElementById('cart-summary');

  function calculateSubtotal(cart) {
    if (!subtotalDisplay) return;
    const total = cart.reduce((sum, item) => sum + (parseFloat(item.price) * item.quantity), 0);
    subtotalDisplay.textContent = `€${total.toFixed(2)}`;
  }

  function renderCartItems(cart) {
    if (!cartItemsContainer) return;
    cartItemsContainer.innerHTML = "";

    if (cart.length === 0) {
      if (cartSummary) cartSummary.style.display = "none";
      const emptyMsg = document.createElement('p');
      emptyMsg.className = 'empty-cart-message';
      emptyMsg.textContent = 'Je winkelwagen is leeg';
      cartItemsContainer.appendChild(emptyMsg);
      if (subtotalDisplay) subtotalDisplay.textContent = "€0,00";
      return;
    }

    if (cartSummary) cartSummary.style.display = "block";

    cart.forEach((item, index) => {
      const itemDiv = document.createElement('div');
      itemDiv.classList.add('cart-item');

      // Dynamische attributen: id voor DB, index voor sessie
      const idAttr = item.id ? `data-id="${item.id}"` : (item.index !== undefined ? `data-index="${item.index}"` : '');

      if (item.type === 'voucher') {
        itemDiv.innerHTML = `
          <div class="item-image-wrapper">
            <img src="cadeaubon/voucher.png" alt="${item.name}" class="item-image item-image-light"/>
            <img src="cadeaubon/voucher (dark mode).png" alt="${item.name}" class="item-image item-image-dark"/>
          </div>
          <div class="item-info">
            <h3>${item.name}</h3>
            <p>${item.variant || ''}</p>
            <p>Prijs: €${parseFloat(item.price).toFixed(2)}</p>
            <label>
              Aantal:
              <input type="number" value="${item.quantity}" min="1" ${idAttr} data-type="${item.type}" class="quantity-input">
            </label>
            <button class="remove-item" ${idAttr} data-type="${item.type}">
              <img src="trash bin/trash bin.png" class="remove-icon remove-icon-light" alt="Verwijderen">
              <img src="trash bin/trash bin (dark mode).png" class="remove-icon remove-icon-dark" alt="Verwijderen">
            </button>
          </div>
        `;
      } else {
        itemDiv.innerHTML = `
          <img src="${item.image}" alt="${item.name}" class="item-image"/>
          <div class="item-info">
            <h3>${item.name}</h3>
            <p>${item.variant || ''}</p>
            <p>Prijs: €${parseFloat(item.price).toFixed(2)}</p>
            <label>
              Aantal:
              <input type="number" value="${item.quantity}" min="1" ${idAttr} data-type="${item.type}" class="quantity-input">
            </label>
            <button class="remove-item" ${idAttr} data-type="${item.type}">
              <img src="trash bin/trash bin.png" class="remove-icon remove-icon-light" alt="Verwijderen">
              <img src="trash bin/trash bin (dark mode).png" class="remove-icon remove-icon-dark" alt="Verwijderen">
            </button>
          </div>
        `;
      }

      cartItemsContainer.appendChild(itemDiv);
    });

    calculateSubtotal(cart);
  }

  // --- renderCartDropdown ---
  function renderCartDropdown(cart, cartDropdown) {
    if (!cartDropdown) return;
    cartDropdown.innerHTML = "";

    if (cart.length === 0) {
      const emptyMsg = document.createElement('p');
      emptyMsg.className = 'empty-cart';
      emptyMsg.textContent = 'Je winkelwagen is leeg';
      cartDropdown.appendChild(emptyMsg);
      return;
    }

    const ul = document.createElement('ul');
    ul.classList.add('dropdown-cart-list');

    cart.forEach(item => {
      const li = document.createElement('li');
      if (item.type === 'voucher') {
        li.innerHTML = `
          <div class="dropdown-item-image-wrapper">
            <img src="cadeaubon/voucher.png" alt="${item.name}" class="dropdown-item-image dropdown-item-image-light"/>
            <img src="cadeaubon/voucher (dark mode).png" alt="${item.name}" class="dropdown-item-image dropdown-item-image-dark"/>
          </div>
          <span class="item-text">${item.name} <span class="item-price">€${parseFloat(item.price).toFixed(2)}</span></span>
        `;
      } else {
        li.innerHTML = `
          <img src="${item.image}" alt="${item.name}" class="dropdown-item-image"/>
          <span class="item-text">${item.name} <span class="item-price">€${parseFloat(item.price).toFixed(2)}</span></span>
        `;
      }
      ul.appendChild(li);
    });

    cartDropdown.appendChild(ul);
  }

  // --- Footer inladen ---
  fetch('footer.html')
    .then(res => res.text())
    .then(html => {
      const footerContainer = document.getElementById('footer-placeholder');
      if (footerContainer) footerContainer.innerHTML = html;
    })
    .catch(err => console.error('Fout bij laden footer:', err));

  // --- Header user controls ---
  function setupHeaderUserControls(headerContainer) {
    fetch('current_user.php')
      .then(res => res.json())
      .then(data => {
        const loginBtn = headerContainer.querySelector('#loginBtn');
        const registerBtn = headerContainer.querySelector('#registerBtn');
        const userDisplay = headerContainer.querySelector('#userDisplay');
        const userDropdown = headerContainer.querySelector('#userDropdown');

        if (data.loggedIn) {
          if (loginBtn) loginBtn.style.display = 'none';
          if (registerBtn) registerBtn.style.display = 'none';
          if (userDisplay) {
            userDisplay.textContent = `Welkom, ${data.userName}`;
            userDisplay.style.display = 'inline-block';
            userDisplay.classList.add('user-button');
          }

          if (userDropdown) {
            userDropdown.classList.add('user-dropdown');
            userDisplay.addEventListener('click', e => {
              e.stopPropagation();
              userDropdown.classList.toggle('open');
            });
            document.addEventListener('click', e => {
              if (!userDropdown.contains(e.target) && !userDisplay.contains(e.target)) {
                userDropdown.classList.remove('open');
              }
            });
          }
        } else {
          if (loginBtn) loginBtn.style.display = 'inline-block';
          if (registerBtn) registerBtn.style.display = 'inline-block';
          if (userDisplay) userDisplay.style.display = 'none';
          if (userDropdown) userDropdown.classList.remove('open');
        }

        const logoutBtns = headerContainer.querySelectorAll('#logoutBtn, #logoutButton');
        logoutBtns.forEach(btn => {
          btn.addEventListener('click', e => {
            e.preventDefault();
            fetch('logout.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: `csrf_token=${encodeURIComponent(window.csrfToken)}`
            })
            .then(res => res.json())
            .then(data => {
              if (data.success) window.location.reload();
              else alert('Uitloggen mislukt');
            })
            .catch(err => console.error('Fout bij uitloggen:', err));
          });
        });
      })
      .catch(err => console.error('Fout bij ophalen huidige gebruiker:', err));
  }

  // --- Cookie banner ---
  function initCookieBanner() {
    const banner = document.getElementById("cookie-banner");
    if (!banner) return;

    const acceptAll = document.getElementById("accept-all");
    const acceptFunctional = document.getElementById("accept-functional");

    function setCookie(name, value, days) {
      const date = new Date();
      date.setTime(date.getTime() + (days*24*60*60*1000));
      document.cookie = `${name}=${value};expires=${date.toUTCString()};path=/;SameSite=Lax`;
    }

    function getCookie(name) {
      const cname = name + "=";
      const decoded = decodeURIComponent(document.cookie);
      const ca = decoded.split(';');
      for (let c of ca) {
        c = c.trim();
        if (c.indexOf(cname) === 0) return c.substring(cname.length);
      }
      return "";
    }

    const consent = getCookie("cookieConsent");
    if (consent === "all" || consent === "functional") {
      banner.style.display = "none";
    } else {
      banner.style.display = "block";
    }

    if (acceptAll) acceptAll.addEventListener("click", () => { setCookie("cookieConsent","all",365); banner.style.display="none"; });
    if (acceptFunctional) acceptFunctional.addEventListener("click", () => { setCookie("cookieConsent","functional",365); banner.style.display="none"; });
  }

  // --- Functies voor update & remove items ---
  function updateQuantityOnServer(itemId, itemType, quantity, itemIndex = null) {
    const payload = { type: itemType, quantity };
    if (itemId) payload.id = itemId;
    if (itemIndex !== null) payload.index = itemIndex;

    fetch('cart.php?action=update_quantity', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.csrfToken },
      body: JSON.stringify(payload)
    });
  }

  function removeItemFromServer(itemId, itemType, itemIndex = null) {
    const formData = new FormData();
    formData.append('type', itemType);
    if (itemId) formData.append('id', itemId);
    if (itemIndex !== null) formData.append('index', itemIndex);
    formData.append('csrf_token', window.csrfToken);

    console.log('Verstuurd FormData:', {
      type: itemType,
      id: itemId,
      index: itemIndex,
      csrf_token: window.csrfToken
    });

    fetch('cart.php?action=remove_item', {
      method: 'POST',
      body: formData
    })
    .then(res => res.text())
    .then(text => {
      let data;
      try {
        data = JSON.parse(text);
      } catch (err) {
        console.error('Geen geldige JSON van server:', text);
        alert('Er ging iets mis bij het verwijderen.');
        return;
      }
      if (data.success) fetchCart(document.getElementById('cartDropdown'));
      else alert(data.message || 'Fout bij verwijderen item');
    });
  }

  // --- Event listener ---
  document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-item')) {
      const btn = e.target.closest('.remove-item');
      const type = btn.dataset.type;
      const id = btn.dataset.id ? parseInt(btn.dataset.id) : null;
      const index = btn.dataset.index ? parseInt(btn.dataset.index) : null;
      console.log('Verwijder:', {type, id, index});
      removeItemFromServer(id, type, index);
    }
  });

  // ================================
  // CHECKOUT FORM: update profiel + afrekenen
  // ================================
  const checkoutForm = document.getElementById('checkoutForm');
  if (checkoutForm) {
      checkoutForm.addEventListener('submit', function(e) {
          e.preventDefault(); // voorkomt standaard form submit

          // Voeg voucher toe aan hidden input als die nog niet bestaat
          let usedVoucherInput = document.getElementById('used_voucher_input');
          if (!usedVoucherInput) {
              usedVoucherInput = document.createElement('input');
              usedVoucherInput.type = 'hidden';
              usedVoucherInput.name = 'used_voucher';
              usedVoucherInput.id = 'used_voucher_input';
              checkoutForm.appendChild(usedVoucherInput);
          }

          // Zet huidige voucher data
          usedVoucherInput.value = JSON.stringify({
              code: window.voucherCode || '',
              amount: window.voucherAmount || 0
          });

          const formData = new FormData(checkoutForm);

          fetch('afrekenen.php', {
              method: 'POST',
              body: formData
          })
          .then(response => response.text())
          .then(data => {
              if (data.trim() === "success") {
                  // Redirect naar index.html bij succes
                  window.location.href = 'index.html';
              } else {
                  console.error('Server response:', data);
                  alert('Er is iets misgegaan bij het verwerken van je bestelling.');
              }
          })
          .catch(err => {
              console.error('Fout bij afrekenen:', err);
              alert('Er is iets misgegaan bij het verwerken van je bestelling.');
          });
      });
  }

  const subtotalOrder = document.getElementById('subtotalOrder');
  const redeemVoucherSpan = document.getElementById('redeem_voucher');
  const savedVoucherDropdown = document.getElementById('saved_voucher');
  const taxAmount = document.getElementById('totalAmount');
  const totalAmount = document.getElementById('totalAmount');

  let cartData = [];
  let voucherAmount = 0;
  let vouchers = [];

  // Haal producten uit cart
  fetch('cart.php?action=get_cart')
      .then(res => res.json())
      .then(data => {
          if (!data.success) return;
          cartData = data.cart;
          updateTotals();
      })
      .catch(err => console.error('Fout bij ophalen cart:', err));

  // Haal vouchers op
  fetch('get_vouchers.php')
      .then(res => res.json())
      .then(data => {
          vouchers = data.filter(v => !v.is_used); // enkel actieve vouchers
          // vul dropdown
          vouchers.forEach(v => {
              const option = document.createElement('option');
              option.value = v.code;
              option.textContent = `${v.code} - €${v.value.toFixed(2)} (${v.is_used ? 'Gebruikt' : 'Beschikbaar'})`;
              savedVoucherDropdown.appendChild(option);
          });
      })
      .catch(err => console.error('Fout bij ophalen vouchers:', err));

  // Functie om totaal te berekenen
  function updateTotals() {
    let subtotal = 0;
    let allVouchers = true; // Start met de veronderstelling dat alles vouchers zijn

    cartData.forEach(item => {
        subtotal += parseFloat(item.price) * item.quantity;
        if (item.type !== 'voucher') {
            allVouchers = false;
        }
    });

    // Beperk voucherAmount tot subtotal
    const effectiveVoucher = Math.min(voucherAmount, subtotal);

    const adjustedSubtotal = subtotal - effectiveVoucher;

    // Verzendkosten 0 als alles vouchers zijn
    const shipping = allVouchers ? 0.00 : 5.00;

    const vat = adjustedSubtotal * 0.21;
    const total = adjustedSubtotal + shipping;

    if (subtotalOrder) subtotalOrder.textContent = `€${adjustedSubtotal.toFixed(2)}`;
    if (redeemVoucherSpan) redeemVoucherSpan.textContent = `€${effectiveVoucher.toFixed(2)}`;
    if (shippingCost) shippingCost.textContent = `€${shipping.toFixed(2)}`;
    if (taxAmount) taxAmount.textContent = `€${vat.toFixed(2)}`;
    if (totalAmount) totalAmount.textContent = `€${total.toFixed(2)}`;
  }

  // Event bij klikken op "Gebruiken"
  document.getElementById('applyVoucherButton')?.addEventListener('click', function() {
      const selectedCode = savedVoucherDropdown.value;
      if (!selectedCode) return;

      // zoek de voucher in vouchers-array
      const voucher = vouchers.find(v => v.code === selectedCode);
      if (!voucher) return;

      voucherAmount = parseFloat(voucher.remaining_value);
      updateTotals();

      // Zet gekozen bon in hidden input (JSON formaat)
      const voucherData = {
          code: voucher.code,
          amount: voucherAmount
      };
      document.getElementById('used_voucher_input').value = JSON.stringify(voucherData);
  });

  // --- Foutmeldingen en oude waarden uit queryparameters ---
  const params = new URLSearchParams(window.location.search);

  function showError(inputId, message) {
    const input = document.getElementById(inputId);
    if (input) {
      // Verwijder bestaande foutmelding als die er is
      const existingError = input.parentNode.querySelector('.input-error');
      if (existingError) existingError.remove();

      const errorMsg = document.createElement('div');
      errorMsg.textContent = message;
      errorMsg.className = 'input-error';
      errorMsg.style.color = "red";
      errorMsg.style.fontSize = "0.9rem";
      errorMsg.style.marginTop = "4px";
      input.insertAdjacentElement('afterend', errorMsg);
    }
  }

  // Toon foutmeldingen
  if (params.get('error_email') === 'exists') {
    showError('register-email', 'E-mail bestaat al');
  }
  if (params.get('error_password2') === 'nomatch') {
    showError('register-password', 'Wachtwoorden komen niet overeen');
  }

  // Oude waarden terugzetten in formulier
  const oldName = params.get('old_name');
  if (oldName) {
    const nameInput = document.getElementById('register-name');
    if (nameInput) nameInput.value = decodeURIComponent(oldName);
  }

  const oldEmail = params.get('old_email');
  if (oldEmail) {
    const emailInput = document.getElementById('register-email');
    if (emailInput) emailInput.value = decodeURIComponent(oldEmail);
  }

  // Login fouten tonen via query params
  if (params.get('error') === 'wrong_password') {
    showError('login-password', 'Foutief wachtwoord');
  }

  if (params.get('error') === 'email_not_found') {
    showError('login-email', 'E-mail niet gevonden');
  }

  // Oude waarde e-mail terugzetten bij loginformulier
  const oldLoginEmail = params.get('old_email');
  if (oldLoginEmail) {
    const loginEmailInput = document.getElementById('login-email');
    if (loginEmailInput) loginEmailInput.value = decodeURIComponent(oldLoginEmail);
  }

    const form = document.getElementById('profileForm');
    const messages = document.getElementById('formMessages');
  
  if (params.get("error")) {
    const errors = params.get("error").split(",");
    errors.forEach(err => {
      if (err === "name_empty") showError("name", "Naam is verplicht.");
      if (err === "address_empty") showError("address", "Adres is verplicht.");
      if (err === "email_invalid") showError("email", "Ongeldig e-mailadres.");
      if (err === "email_exists") showError("email", "E-mailadres is al in gebruik.");
      if (err === "password_mismatch") showError("passwordConfirm", "Wachtwoorden komen niet overeen.");
      if (err === "password_same") showError("password", "Nieuw wachtwoord mag niet gelijk zijn aan het huidige.");
    });
  }

  if (params.get("success") === "1") {
    const successEl = document.createElement("p");
    successEl.textContent = "Profiel succesvol bijgewerkt!";
    successEl.style.color = "green";
    successEl.style.marginTop = "1rem";
    form.appendChild(successEl);
  }

// Huidige profielgegevens ophalen en Mijn Stylisso overzicht vullen
fetch('get_user_data.php')
    .then(res => res.json())
    .then(data => {
        if (!data.error) {
            // OUD: profielpagina invullen
            const nameInput = document.getElementById('name');
            const emailInput = document.getElementById('email');
            const addressInput = document.getElementById('address');
            const newsletterCheckbox = document.getElementById('newsletter');
            const companyInput = document.getElementById('company_name');
            const vatInput = document.getElementById('vat_number');
            if (nameInput) nameInput.value = data.name;
            if (emailInput) emailInput.value = data.email;
            if (addressInput) addressInput.value = data.address;
            if (newsletterCheckbox) newsletterCheckbox.checked = (data.newsletter == 1);
            if (companyInput && data.company_name) companyInput.value = data.company_name;
            if (vatInput && data.vat_number) vatInput.value = data.vat_number;

            // NIEUW: Mijn Stylisso overzicht invullen
            const userName = document.getElementById('user-name');
            const userEmail = document.getElementById('user-email');
            const userAddressLine = document.getElementById('user-address-line');
            const userAddress = document.getElementById('user-address');
            const userCompanyLine = document.getElementById('user-company-line');
            const userCompany = document.getElementById('user-company');
            const userVatLine = document.getElementById('user-vat-line');
            const userVat = document.getElementById('user-vat');

            if (userName) userName.textContent = data.name;
            if (userEmail) userEmail.textContent = data.email;

            // Alleen tonen als adres is ingevuld
            if (data.address && data.address.trim() !== '') {
                userAddress.textContent = data.address;
                userAddressLine.style.display = '';
            } else {
                userAddressLine.style.display = 'none';
            }

            // Alleen tonen als bedrijfsnaam is ingevuld
            if (data.company_name && data.company_name.trim() !== '') {
                userCompany.textContent = data.company_name;
                userCompanyLine.style.display = '';
            } else {
                userCompanyLine.style.display = 'none';
            }

            // Alleen tonen als BTW-nummer is ingevuld
            if (data.vat_number && data.vat_number.trim() !== '') {
                userVat.textContent = data.vat_number;
                userVatLine.style.display = '';
            } else {
                userVatLine.style.display = 'none';
            }
        } else {
            const messages = document.getElementById('messages');
            if (messages) messages.innerHTML = `<p style="color:red;">${data.error}</p>`;
        }
    })
    .catch(err => console.error('Fout bij ophalen van gebruikersgegevens:', err));
  });

// ---------------------------
// Nieuwe functie: Bestellingen laden
// ---------------------------
async function loadOrders() {
  const container = document.getElementById('orders-container');
  if (!container) return; // Alleen uitvoeren als container aanwezig is

  try {
    const response = await fetch('get_orders.php');
    const orders = await response.json();

    if (orders.error) {
      container.innerHTML = `<p>${orders.error}</p>`;
      return;
    }

    if (orders.length === 0) {
      container.innerHTML = `<p>Je hebt nog geen bestellingen geplaatst.</p>`;
      return;
    }

    let html = '<table class="orders-table">';
    html += `
      <thead>
        <tr>
          <th>Order ID</th>
          <th>Datum</th>
          <th>Producten</th>
          <th>Totaalprijs</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
    `;

    orders.forEach(order => {
      html += `
        <tr>
          <td>${order.order_id}</td>
          <td>${order.created_at}</td>
          <td>${order.products}</td>
          <td>€${order.total_price}</td>
          <td>${order.status.charAt(0).toUpperCase() + order.status.slice(1)}</td>
        </tr>
      `;
    });

    html += '</tbody></table>';
    container.innerHTML = html;

  } catch (err) {
    container.innerHTML = `<p>Fout bij laden van bestellingen.</p>`;
    console.error(err);
  }

  // Alleen aanroepen op mijn_stylisso.html
  if (document.getElementById('last-order')) {
    loadLastOrder();
  }
}

// ---------------------------
// Event listener bij DOM load
// ---------------------------
window.addEventListener('DOMContentLoaded', () => {
  // Bestaande initialisaties
  initHeaderFooter(); // voorbeeld

  // Alleen bestellingen laden op bestellingen.html
  if (document.getElementById('orders-container')) {
    loadOrders();
  }
});

// ---------------------------
// Nieuwe functie: Retourneren
// ---------------------------

document.addEventListener('DOMContentLoaded', () => {
    fetch('csrf.php')
    .then(res => res.json())
    .then(data => {
        if (data.csrf_token) {
            document.querySelectorAll('input[name="csrf_token"]').forEach(input => {
                input.value = data.csrf_token;
            });
        }
    });
  
    const orderSelect = document.getElementById('orderSelect');
    const productSelect = document.getElementById('productSelect');
    const returnForm = document.getElementById('returnForm');
    const messageDiv = document.getElementById('return-message');
    let orderItemsData = {};

    // Haal bestellingen op
    fetch('get_returns.php')
        .then(res => res.json())
        .then(data => {
            if (data.orders) {
                data.orders.forEach(order => {
                    const option = document.createElement('option');
                    option.value = order.id;
                    option.textContent = `Order #${order.id} - ${order.created_at}`;
                    orderSelect.appendChild(option);
                });
                orderItemsData = data.orderItems;

                // vul producten van eerste order
                if (data.orders[0]) fillProducts(data.orders[0].id);
            }
        });

    function fillProducts(orderId) {
        productSelect.innerHTML = '<option value="">-- Kies een product --</option>';
        if (orderItemsData[orderId]) {
            orderItemsData[orderId].forEach(item => {
                const option = document.createElement('option');
                option.value = item.product_id;
                option.textContent = `${item.name} (Aantal: ${item.quantity})`;
                productSelect.appendChild(option);
            });
        }
    }

    orderSelect.addEventListener('change', function() {
        fillProducts(this.value);
    });

    returnForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(returnForm);

        fetch('submit_return.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(resp => {
            messageDiv.textContent = resp.success || resp.error;
            messageDiv.style.color = resp.success ? 'green' : 'red';
            if (resp.success) returnForm.reset();
        });
    });
});

document.addEventListener('DOMContentLoaded', () => {
  const voucherList = document.getElementById('voucher-list');
  if (!voucherList) return;

  fetch('get_vouchers.php')
    .then(res => {
      if (!res.ok) throw new Error(`HTTP fout! status: ${res.status}`);
      return res.json();
    })
    .then(data => {
      console.log('Voucher data:', data);

      if (data.error) {
        voucherList.innerHTML = `<p>${data.error}</p>`;
        return;
      }

      if (!Array.isArray(data) || data.length === 0) {
        voucherList.innerHTML = `<p>Geen cadeaubonnen gekoppeld.</p>`;
        return;
      }

      const now = new Date();

      // Filter alleen geldige vouchers
      const validVouchers = data.filter(voucher => {
        const remaining = Number(voucher.remaining_value ?? 0);
        const expiresAt = voucher.expires_at ? new Date(voucher.expires_at) : null;
        return remaining > 0 && (!expiresAt || expiresAt >= now);
      });

      if (validVouchers.length === 0) {
        voucherList.innerHTML = `<p>Geen cadeaubonnen gekoppeld.</p>`;
        return;
      }

      const ul = document.createElement('ul');
      ul.classList.add('voucher-list');

      validVouchers.forEach(voucher => {
        const li = document.createElement('li');

        const remainingValue = Number(voucher.remaining_value ?? 0).toFixed(2);

        let expireDate = 'Onbepaald';
        if (voucher.expires_at) {
          const dateObj = new Date(voucher.expires_at);
          if (!isNaN(dateObj)) {
            const day = String(dateObj.getDate()).padStart(2, '0');
            const month = String(dateObj.getMonth() + 1).padStart(2, '0');
            const year = dateObj.getFullYear();
            expireDate = `${day}-${month}-${year}`;
          }
        }

        li.innerHTML = `
          <span class="left"><strong>Code:</strong> ${voucher.code}</span>
          <span class="separator">|</span>
          <span class="center"><strong>Resterende waarde:</strong> €${remainingValue}</span>
          <span class="separator">|</span>
          <span class="right"><strong>Vervalt op:</strong> ${expireDate}</span>
        `;
        ul.appendChild(li);
      });

      voucherList.innerHTML = '';
      voucherList.appendChild(ul);
    })
    .catch(err => {
      voucherList.innerHTML = `<p>Fout bij laden van cadeaubonnen. Controleer console.</p>`;
      console.error('Fout bij ophalen vouchers:', err);
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const buttons = document.querySelectorAll('.amount-buttons button');
    const customButton = document.getElementById('custom-button');
    const customAmountInput = document.getElementById('custom-amount');

    // Standaard input verbergen totdat Aangepast is gekozen
    customAmountInput.style.display = 'none';

    buttons.forEach(button => {
        button.addEventListener('click', () => {
            // Active class resetten
            buttons.forEach(btn => btn.classList.remove('active'));

            // Klik op deze knop markeren
            button.classList.add('active');

            if (button.dataset.amount === 'custom') {
                // Aangepast geselecteerd -> input zichtbaar en inschakelen
                customAmountInput.style.display = 'block';
                customAmountInput.value = '';
                customAmountInput.focus();
            } else {
                // Ander bedrag -> input verbergen en waarde invullen
                customAmountInput.style.display = 'none';
                customAmountInput.value = button.dataset.amount;
            }
        });
    });

    // Optioneel: bij submit checken dat bedrag minimaal 5 is
    const form = document.querySelector('.voucher-form');
    form.addEventListener('submit', e => {
        const amount = parseFloat(customAmountInput.value);
        if (!customAmountInput.disabled && amount < 5) {
            e.preventDefault();
            alert('Voer een bedrag van minimaal €5 in.');
        }
    });
});

(function () {
  function onReady() {
    const voucherForm  = document.getElementById('voucherForm');
    const messageBox   = document.getElementById('voucher-message');
    const savedSelect  = document.getElementById('saved_voucher');
    const applyBtn     = document.getElementById('applyVoucherButton');
    const codeInput    = document.getElementById('voucher_code');

    if (!voucherForm) return;

    let isSubmitting = false;

    // Enkele, dominante submit-handler
    voucherForm.addEventListener('submit', (e) => {
      e.preventDefault();
      e.stopImmediatePropagation(); // blokkeert oude listeners
      if (isSubmitting) return;
      isSubmitting = true;

      const submitBtn = voucherForm.querySelector('button[type="submit"]');
      const prevLabel = submitBtn ? submitBtn.textContent : '';
      if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Bezig…'; }

      const formData = new FormData(voucherForm);

      fetch('redeem_voucher.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      })
      .then(r => r.json())
      .then(res => {
        if (res.error) {
          messageBox.textContent = res.error;
          messageBox.style.color = 'red';
          messageBox.style.margin = '1rem 0';
        } else if (res.success) {
          messageBox.textContent = res.success;
          messageBox.style.color = 'green';
          messageBox.style.margin = '1rem 0';
          if (codeInput) codeInput.value = '';
          refreshSavedVouchers(savedSelect);
          refreshVoucherList();
        }
      })
      .catch(err => {
        console.error('redeem_voucher error:', err);
        messageBox.textContent = 'Er ging iets mis, probeer opnieuw.';
        messageBox.style.color = 'red';
        messageBox.style.margin = '1rem 0';
      })
      .finally(() => {
        isSubmitting = false;
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = prevLabel; }
      });
    }, true); // capture:true => onze handler eerst

    // Init dropdown en lijst
    refreshSavedVouchers(savedSelect);
    refreshVoucherList();

    // Dropdown gebruiken met dezelfde messageBox logica
    if (applyBtn && savedSelect) {
      applyBtn.addEventListener('click', () => {
        const code = savedSelect.value;
        if (!code) {
          messageBox.textContent = "Kies eerst een cadeaubon.";
          messageBox.style.color = "red";
          messageBox.style.margin = "1rem 0";
          return;
        }

        // Succesmelding
        messageBox.textContent = `Cadeaubon toegepast: ${code}`;
        messageBox.style.color = "green";
        messageBox.style.margin = "1rem 0";

        // TODO: hier logica toevoegen om totaal aan te passen
      });
    }
  }

  function refreshSavedVouchers(selectEl) {
    if (!selectEl) return;
    fetch('get_vouchers.php', { credentials: 'same-origin' })
      .then(r => r.json())
      .then(list => {
        if (!Array.isArray(list)) return;
        const now = new Date();
        const opts = ['<option value="">Kies een bon</option>'];
        list.forEach(v => {
          const expires = v.expires_at ? new Date(v.expires_at) : null;
          const remaining = Number(v.remaining_value ?? 0);
          if (remaining > 0 && (!expires || expires >= now)) {
            const expireDate = expires 
              ? `${String(expires.getDate()).padStart(2,'0')}-${String(expires.getMonth()+1).padStart(2,'0')}-${expires.getFullYear()}` 
              : '';
            opts.push(
              `<option value="${v.code}">${v.code} — €${remaining.toFixed(2)}${expireDate ? ' (tot ' + expireDate + ')' : ''}</option>`
            );
          }
        });
        selectEl.innerHTML = opts.join('');
      })
      .catch(err => console.error('get_vouchers dropdown error:', err));
  }

  function refreshVoucherList() {
    const container = document.getElementById('voucher-list');
    if (!container) return;

    fetch('get_vouchers.php', { credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => {
        if (data.error || !Array.isArray(data)) {
          container.innerHTML = `<p>${data.error || 'Geen cadeaubonnen gekoppeld.'}</p>`;
          return;
        }
        const now = new Date();
        const valid = data.filter(v => {
          const expires = v.expires_at ? new Date(v.expires_at) : null;
          const remaining = Number(v.remaining_value ?? 0);
          return remaining > 0 && (!expires || expires >= now);
        });
        if (!valid.length) {
          container.innerHTML = '<p>Geen cadeaubonnen gekoppeld.</p>';
          return;
        }
        const ul = document.createElement('ul');
        ul.className = 'voucher-list';
        valid.forEach(v => {
          const expires = v.expires_at ? new Date(v.expires_at) : null;
          const expireDate = expires
            ? `${String(expires.getDate()).padStart(2,'0')}-${String(expires.getMonth()+1).padStart(2,'0')}-${expires.getFullYear()}`
            : 'Onbepaald';
          li = document.createElement('li');
          li.innerHTML = `
            <span class="left"><strong>Code:</strong> ${v.code}</span>
            <span class="separator" aria-hidden="true">|</span>
            <span class="center"><strong>Resterende waarde:</strong> €${Number(v.remaining_value ?? 0).toFixed(2)}</span>
            <span class="separator" aria-hidden="true">|</span>
            <span class="right"><strong>Vervalt op:</strong> ${expireDate}</span>
          `;
          ul.appendChild(li);
        });
        container.innerHTML = '';
        container.appendChild(ul);
      })
      .catch(err => {
        console.error('get_vouchers list error:', err);
        container.innerHTML = '<p>Fout bij laden van cadeaubonnen.</p>';
      });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', onReady);
  } else {
    onReady();
  }
})();

document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('.auth-form');
  if (!form) return;

  // Voeg een div toe onder het formulier voor berichten, als die nog niet bestaat
  let messageBox = document.getElementById('message');
  if (!messageBox) {
    messageBox = document.createElement('div');
    messageBox.id = 'message';
    messageBox.style.margin = '1rem 0';
    messageBox.style.color = 'red';
    form.appendChild(messageBox);
  }

  // Token en eventuele fout/succes uit de URL halen
  const urlParams = new URLSearchParams(window.location.search);
  const token = urlParams.get('token');
  const error = urlParams.get('error');
  const success = urlParams.get('success');

  const tokenInput = document.getElementById('reset_token');

  if (tokenInput) {
    if (token) {
      tokenInput.value = token;
    } else {
      messageBox.textContent = "Ongeldige of ontbrekende reset-link.";
      messageBox.style.color = "red";
    }
  }

  if (error) {
    messageBox.textContent = decodeURIComponent(error);
    messageBox.style.color = "red";
  }

  if (success) {
    messageBox.textContent = decodeURIComponent(success);
    messageBox.style.color = "green";
  }
});