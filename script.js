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
            <input type="hidden" value="${item.quantity}" min="1" ${idAttr} data-type="${item.type}" class="quantity-input">
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
              amount: Number(window.voucherAmount) || 0 // <-- fix!
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
                  window.location.href = 'bedankt.html';
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

      window.voucherCode = voucher.code;
      window.voucherAmount = parseFloat(voucher.remaining_value); // <-- fix!

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
// Helperfunctie om datum te formatteren
// ---------------------------
function formatDate(dateString) {
  const date = new Date(dateString);
  const day = String(date.getDate()).padStart(2, '0');
  const month = String(date.getMonth() + 1).padStart(2, '0'); // maanden zijn 0-index
  const year = date.getFullYear();
  return `${day}-${month}-${year}`;
}

// ---------------------------
// Bestellingen laden op bestellingen.html
// ---------------------------
async function loadOrders() {
  const container = document.getElementById('orders-container');
  if (!container) return;

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
          <th>Ordernummer</th>
          <th>Datum</th>
          <th>Producten</th>
          <th>Totaalprijs</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
    `;

    orders.forEach(order => {
      const items = order.products || []; // al een array

      let productHtml = '';
      items.forEach(item => {
        if (item.toLowerCase().includes('cadeaubon')) {
          // Voucher apart weergeven
          productHtml += `<div>${item}</div>`;
        } else {
          // Normaal product: "Aantal x Product"
          const [qtyPart, ...nameParts] = item.split(' x');
          const name = nameParts.join(' x'); // voor namen met 'x' erin
          productHtml += `<div>${qtyPart} x ${name}</div>`;
        }
      });

      html += `
        <tr>
          <td>${order.id}</td>
          <td>${formatDate(order.created_at)}</td>
          <td>${productHtml || '<em>Geen producten</em>'}</td>
          <td>€${parseFloat(order.total_price).toFixed(2)}</td>
          <td>${order.status.charAt(0).toUpperCase() + order.status.slice(1)}</td>
        </tr>
      `;
    });

    html += '</tbody></table>';
    container.innerHTML = html;

    // Maak rijen klikbaar om factuur te openen
    container.querySelectorAll('tbody tr').forEach(row => {
      row.style.cursor = 'pointer';
      row.addEventListener('click', () => {
        const orderId = row.querySelector('td').textContent; // eerste kolom = order_id
        openInvoicePDF(orderId);
      });
    });

  } catch (err) {
    container.innerHTML = `<p>Fout bij laden van bestellingen.</p>`;
    console.error(err);
  }
}

// ---------------------------
// Laatste bestelling laden op mijn_stylisso.html
// ---------------------------
async function loadLastOrder() {
  const container = document.getElementById('last-order');
  if (!container) return;

  try {
    const response = await fetch('get_last_order.php');
    const order = await response.json();

    if (order.error) {
      container.innerHTML = `<p>${order.error}</p>`;
      return;
    }

    let products = [];
    let vouchers = [];

    if (order.products && order.products.length) {
      order.products.forEach(item => {
        if (item.name.toLowerCase().includes('cadeaubon')) {
          vouchers.push(item);
        } else {
          products.push(item);
        }
      });
    }

    let productList = '';

    if (products.length > 0) {
      // Normale producten
      productList += products.map(p => `${p.quantity} x ${p.name}`).join(', ');
    }

    if (vouchers.length > 0) {
      // Voeg vouchers toe
      if (productList) productList += ', ';
      productList += vouchers.length === 1
        ? `${vouchers[0].name.replace(/Cadeaubon: /, '')}`
        : 'Cadeaubon(nen)';
    }

    container.innerHTML = `
      Ordernummer: ${order.id}<br>
      Datum: ${formatDate(order.created_at)}<br>
      Producten: ${productList || '<em>Geen producten</em>'}<br>
      Totaalprijs: €${parseFloat(order.total_price).toFixed(2)}<br>
      Status: ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
    `;

  } catch (err) {
    container.innerHTML = `<p>Fout bij laden van laatste bestelling.</p>`;
    console.error(err);
  }
}

// ---------------------------
// Functie om factuur PDF op te halen
// ---------------------------
function openInvoice(orderId, orderDate) {
  const url = `get_invoice.php?order_id=${orderId}&date=${orderDate}`;
  window.open(url, '_blank');
}

// ---------------------------
// DOMContentLoaded event
// ---------------------------
window.addEventListener('DOMContentLoaded', () => {
  // Bestellingen laden als container aanwezig is
  if (document.getElementById('orders-container')) {
    loadOrders();
  }

  // Laatste bestelling laden als container aanwezig is
  if (document.getElementById('last-order')) {
    loadLastOrder();
  }
});

// ==========================
// Helperfunctie om datum te formatteren (YYYY-MM-DD -> DD-MM-YYYY)
// ==========================
function formatDate(dateString) {
  const date = new Date(dateString);
  const day = String(date.getDate()).padStart(2, "0");
  const month = String(date.getMonth() + 1).padStart(2, "0"); // maand is 0-index
  const year = date.getFullYear();
  return `${day}-${month}-${year}`;
}

// ==========================
// Toon laatste bestelling (detailweergave) op bedankt.html, inclusief vouchers
// ==========================
async function loadLastOrderDetails() {
  const container = document.getElementById("order-details");
  if (!container) return; // alleen uitvoeren op bedankt.html

  try {
    const res = await fetch("get_last_order_bedankt.php");
    const data = await res.json();

    if (!data || data.error) {
      container.innerHTML = data.error || "Geen bestelling gevonden.";
      return;
    }

    let html = `
      <h2>Order ${data.id}</h2>
      <p><strong>Datum:</strong> ${formatDate(data.created_at)}</p>
      <table class="bedankt-table">
        <thead>
          <tr>
            <th>Product</th>
            <th>Aantal</th>
            <th>Eenheidsprijs</th>
            <th>Prijs</th>
          </tr>
        </thead>
        <tbody>
    `;

    data.products.forEach(item => {
      // Optioneel: herken vouchers aan "Voucher:" in de naam
      const isVoucher = item.name.toLowerCase().includes("voucher");
      html += `
        <tr${isVoucher ? ' class="voucher-row"' : ''}>
          <td>${item.name}</td>
          <td>1</td>
          <td>€${parseFloat(item.price).toFixed(2)}</td>
          <td>€${(parseFloat(item.price) * item.quantity).toFixed(2)}</td>
        </tr>
      `;
    });

    html += `
        </tbody>
      </table>
      <h3>Totaal: €${parseFloat(data.total_price).toFixed(2)}</h3>
    `;

    container.innerHTML = html;
  } catch (err) {
    container.innerHTML = "Er is iets misgegaan bij het laden van je bestelling.";
    console.error(err);
  }
}

// Auto uitvoeren alleen op bedankt.html
document.addEventListener("DOMContentLoaded", () => {
  if (document.getElementById("order-details")) {
    loadLastOrderDetails();
  }
});

// ---------------------------
// Nieuwe functie: Retourneren
// ---------------------------
document.addEventListener('DOMContentLoaded', () => {
    const returnCardsContainer = document.getElementById('return-cards');

    // Haal CSRF-token op
    let csrfToken = '';
    fetch('csrf.php')
        .then(res => res.json())
        .then(data => { csrfToken = data.csrf_token; })
        .catch(err => console.error('Fout bij ophalen CSRF-token:', err));

    // Haal bestellingen op
    fetch('retourneren.php')
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                returnCardsContainer.innerHTML = `<p>${data.error}</p>`;
                return;
            }

            const orders = {};
            data.forEach(item => {
                if (!orders[item.order_id]) {
                    orders[item.order_id] = { order_date: item.order_date, items: [] };
                }
                orders[item.order_id].items.push(item);
            });

            returnCardsContainer.innerHTML = '';

            const sortedOrderIds = Object.keys(orders).sort((a,b) => 
                new Date(orders[b].order_date) - new Date(orders[a].order_date)
            );

            sortedOrderIds.forEach(orderId => {
                const order = orders[orderId];
                const orderDiv = document.createElement('div');
                orderDiv.className = 'return-card';

                const dateObj = new Date(order.order_date);
                const formattedDate = `${String(dateObj.getDate()).padStart(2,'0')}-${String(dateObj.getMonth()+1).padStart(2,'0')}-${dateObj.getFullYear()}`;

                const orderHeader = document.createElement('h3');
                orderHeader.textContent = `Order ${orderId} - Aankoopdatum: ${formattedDate}`;
                orderDiv.appendChild(orderHeader);

                order.items.forEach(product => {
                    const productDiv = document.createElement('div');
                    productDiv.className = 'return-product';

                    const img = document.createElement('img');
                    img.src = product.product_image;
                    img.alt = product.product_name;
                    img.className = 'return-product-img';

                    const name = document.createElement('p');
                    name.textContent = product.product_name;

                    const returnLink = document.createElement('a');
                    returnLink.href = '#';
                    returnLink.className = 'return-link';

                    // Bepaal knoptekst op basis van return_status
                    switch(product.return_status) {
                        case 'requested':
                            returnLink.textContent = 'Retour aangevraagd';
                            returnLink.style.pointerEvents = 'none';
                            returnLink.classList.add('return-requested');
                            break;
                        case 'approved':
                            returnLink.textContent = 'Retour goedgekeurd';
                            returnLink.style.pointerEvents = 'none';
                            returnLink.classList.add('return-approved');
                            break;
                        case 'processed':
                            returnLink.textContent = 'Retour verwerkt';
                            returnLink.style.pointerEvents = 'none';
                            returnLink.classList.add('return-processed');
                            break;
                        case 'rejected':
                            returnLink.textContent = 'Retour afgekeurd';
                            returnLink.style.pointerEvents = 'none';
                            returnLink.classList.add('return-rejected');
                            break;
                        default:
                            returnLink.textContent = 'Retour starten';
                            // Klik event
                            returnLink.addEventListener('click', e => {
                                e.preventDefault();
                                if (!csrfToken) { alert('CSRF-token niet geladen.'); return; }

                                fetch('submit_returns.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type':'application/x-www-form-urlencoded' },
                                    body: `order_item_id=${product.order_item_id}&reason=Retour+verzoek&csrf_token=${csrfToken}`
                                })
                                .then(res => res.json())
                                .then(resp => {
                                    if (resp.success) {
                                        returnLink.textContent = 'Retour aangevraagd';
                                        returnLink.style.pointerEvents = 'none';
                                        returnLink.classList.add('return-requested');
                                    } else {
                                        alert(resp.error || 'Er is iets misgegaan.');
                                    }
                                })
                                .catch(err => alert('Fout bij verwerken retour: '+err));
                            });
                    }

                    productDiv.appendChild(img);
                    productDiv.appendChild(name);
                    productDiv.appendChild(returnLink);
                    orderDiv.appendChild(productDiv);
                });

                returnCardsContainer.appendChild(orderDiv);
            });
        })
        .catch(err => {
            returnCardsContainer.innerHTML = `<p>Er is een fout opgetreden: ${err}</p>`;
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

let csrfToken = null;

// Functie om CSRF-token op te halen
async function getCsrfToken() {
  if (csrfToken) return csrfToken; // cache token
  try {
    const res = await fetch('./csrf.php');
    const data = await res.json();
    csrfToken = data.csrf_token;
    return csrfToken;
  } catch (err) {
    console.error('Fout bij ophalen CSRF-token:', err);
    throw err;
  }
}

async function loadWishlist() {
  try {
    const response = await fetch("wishlist.php");
    const data = await response.json();

    const container = document.getElementById("wishlist-container");
    container.innerHTML = "";

    // --- leeg lijstje ---
    if (data.error || data.length === 0) {
      const emptyBox = document.createElement("div");
      emptyBox.className = "empty-wishlist-box";
      emptyBox.textContent = "Je verlanglijstje is leeg";
      container.appendChild(emptyBox);
      return;
    }

    // --- CSRF-token ophalen voordat we event listeners aanmaken ---
    const token = await getCsrfToken();

    const grid = document.createElement("div");
    grid.className = "wishlist-grid";

    data.forEach(item => {
      const card = document.createElement("div");
      card.className = "wishlist-card";

      card.innerHTML = `
        <img src="${item.image}" alt="${item.name}" class="wishlist-item-image"/>
        <div class="wishlist-info">
          <h3>${item.name}</h3>
          ${item.variant ? `<p>${item.variant}</p>` : ""}
          <p>Prijs: €${parseFloat(item.price).toFixed(2)}</p>
          <div class="wishlist-actions">
            <button class="add-to-cart" data-id="${item.id}">
              <img src="shopping bag/shopping bag.png" alt="Toevoegen aan winkelwagen" class="cart-icon cart-icon-light" />
              <img src="shopping bag/shopping bag (dark mode).png" alt="Toevoegen aan winkelwagen" class="cart-icon cart-icon-dark" />
            </button>
            <button class="remove-from-wishlist" data-id="${item.id}" data-type="${item.type}">
              <img src="trash bin/trash bin.png" class="remove-icon remove-icon-light" alt="Verwijderen">
              <img src="trash bin/trash bin (dark mode).png" class="remove-icon remove-icon-dark" alt="Verwijderen">
            </button>
          </div>
        </div>
      `;

      // --- klik op de card → naar productpagina ---
      card.addEventListener("click", () => {
        window.location.href = `productpagina.html?id=${item.id}`;
      });

      // --- add-to-cart knop ---
      card.querySelector(".add-to-cart").addEventListener("click", async (e) => {
        e.stopPropagation();
        const productId = e.currentTarget.dataset.id;
        if (!productId) return;

        await fetch("./wishlist_cart_add.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: `product_id=${productId}&quantity=1&csrf_token=${encodeURIComponent(token)}`
        });

        // navigeer naar winkelwagen
        window.location.href = "cart.html";
      });

      // --- remove-from-wishlist knop ---
      card.querySelector(".remove-from-wishlist").addEventListener("click", async (e) => {
        e.stopPropagation();
        const productId = e.currentTarget.dataset.id;
        if (!productId) return;

        await fetch("./wishlist_remove.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: `product_id=${productId}&csrf_token=${encodeURIComponent(token)}`
        });

        // herlaad wishlist
        loadWishlist();
      });

      grid.appendChild(card);
    });

    container.appendChild(grid);

  } catch (err) {
    document.getElementById("wishlist-container").innerHTML =
      "<p>Fout bij laden van wishlist.</p>";
    console.error(err);
  }
}

document.addEventListener("DOMContentLoaded", loadWishlist);

document.addEventListener('DOMContentLoaded', async () => {
  const titleEl = document.getElementById('product-title');
  const imageEl = document.getElementById('product-image');
  const descEl = document.getElementById('product-description');
  const priceEl = document.getElementById('product-price');
  const quantityEl = document.getElementById('product-quantity');
  const addBtn = document.getElementById('add-to-cart');
  const csrfTokenEl = document.getElementById('csrf_token'); // hidden input
  const errorEl = document.getElementById('product-error');

  const params = new URLSearchParams(window.location.search);
  const id = params.get('id');

  if (!id) {
    errorEl.textContent = "Geen product geselecteerd.";
    return;
  }

  // --- Haal CSRF-token op via fetch ---
try {
  const csrfResp = await fetch('csrf.php');
  const csrfData = await csrfResp.json();
  csrfTokenEl.value = csrfData.csrf_token; // vul hidden input
} catch (err) {
  errorEl.textContent = "Fout bij ophalen van beveiligingstoken.";
  console.error(err);
  return;
}

  try {
    // Product ophalen via PHP JSON
    const response = await fetch(`get_product.php?id=${id}`);
    const product = await response.json();

    if (product.error) {
      errorEl.textContent = product.error;
      return;
    }

    // Vul HTML
    titleEl.textContent = product.name;
    imageEl.src = product.image;
    imageEl.alt = product.name;
    descEl.innerHTML = product.description.replace(/\n/g, "<br>");
    priceEl.textContent = `€${parseFloat(product.price).toFixed(2)}`;

    // Add to cart knop
    addBtn.addEventListener('click', async () => {
      const quantity = parseInt(quantityEl.value);
      if (quantity < 1) return;

      const formData = new FormData();
      formData.append('product_id', product.id);
      formData.append('quantity', quantity);
      formData.append('csrf_token', csrfTokenEl.value); // CSRF-token meesturen

      try {
        const addResp = await fetch('add_to_cart.php', {
          method: 'POST',
          body: formData
        });
        const result = await addResp.json(); // JSON verwacht
        if(result.success){
          window.location.href = 'cart.html';
        } else {
          alert(result.error || 'Fout bij toevoegen aan winkelwagen.');
        }
      } catch (err) {
        alert('Fout bij toevoegen aan winkelwagen.');
        console.error(err);
      }
    });

  } catch (err) {
    errorEl.textContent = "Fout bij het laden van het product.";
    console.error(err);
  }
});

// --- Redirect als gebruiker niet ingelogd ---
document.addEventListener('DOMContentLoaded', () => {
    const privatePages = [
        'gegevens.html', 'retourneren.html', 'cadeaubonnen.html','afrekenen.html',
        'bestellingen.html','mijn_stylisso.html','bedankt.html','afrekenen.php',
        'create_invoice.php','get_invoice.php','get_last_order_bedankt.php',
        'get_last_order.php','get_orders.php','get_returns.php','get_user_data.php',
        'get_vouchers.php','logout.php','redeem_voucher.php','retourneren.php',
        'submit_returns.php','update_profile.php','delete_account.php'
    ]; // pagina's alleen voor ingelogde gebruikers
    const loginPage = 'login_registreren.html';
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';

    fetch('check_login.php')
        .then(res => res.json())
        .then(data => {
            const loggedIn = data.logged_in;

            if (!loggedIn && privatePages.includes(currentPage)) {
                // Redirect altijd naar login_registreren.html
                window.location.href = loginPage;
            }
        })
        .catch(err => {
            console.error('Fout bij login check:', err);
            // Optioneel: redirect ook bij fout
            window.location.href = loginPage;
        });
});

// --- Redirect ingelogde gebruiker weg van "guest-only" pagina's ---
document.addEventListener('DOMContentLoaded', () => {
    const guestPages = ['login_registreren.html','reset_password.html','reset_success.html','wachtwoord vergeten.html','login.php','register.php','reset_password.php','wachtwoord vergeten.php']; // pagina's alleen voor niet-ingelogde gebruikers
    const homePage = 'index.html';
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';

    fetch('check_login.php')
        .then(res => res.json())
        .then(data => {
            const loggedIn = data.logged_in;

            if (loggedIn && guestPages.includes(currentPage)) {
                // Als ingelogd en op een gastpagina → redirect naar home
                window.location.href = homePage;
            }
        })
        .catch(err => {
            console.error('Fout bij login check:', err);
        });
});