console.log("Stylisso site loaded.");

document.addEventListener('DOMContentLoaded', function () {
  // --- CSRF-token ophalen ---
  fetch('csrf.php')
    .then(res => res.json())
    .then(data => {
      window.csrfToken = data.csrf_token;
      console.log("CSRF-token ingesteld:", window.csrfToken);
    })
    .catch(err => console.error('CSRF-token kon niet opgehaald worden', err));
  
  // --- Header inladen via fetch ---
  fetch('header.html')
    .then(response => {
      if (!response.ok) throw new Error('Header kon niet geladen worden');
      return response.text();
    })
    .then(html => {
      const headerContainer = document.getElementById('header-placeholder');
      if (!headerContainer) {
        console.warn('Geen container gevonden voor header');
        return;
      }
      headerContainer.innerHTML = html;

      // --- Check ingelogde gebruiker ---
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
            }

            if (userDisplay && userDropdown) {
              userDisplay.addEventListener('click', function (e) {
                e.stopPropagation();
                userDropdown.classList.toggle('open');
              });

              document.addEventListener('click', function (e) {
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
        })
        .catch(err => console.error('Fout bij ophalen huidige gebruiker:', err));

      // --- Logout knop event listener ---
      const logoutBtn = headerContainer.querySelector('#logoutBtn');
      if (logoutBtn) {
        logoutBtn.addEventListener('click', function (e) {
          e.preventDefault();
          fetch('logout.php', { method: 'POST' })
            .then(res => res.json())
            .then(data => {
              if (data.success) {
                const currentPage = window.location.pathname.split('/').pop();
                const redirectPages = ['mijn_stylisso.html', 'bestellingen.html', 'retourneren.html', 'gegevens.html', 'cadeaubonnen.html', 'cart.html'];

                if (redirectPages.includes(currentPage)) {
                  window.location.href = 'login_registreren.html';
                } else {
                  window.location.reload();
                }
              } else {
                alert('Uitloggen mislukt');
              }
            })
            .catch(() => alert('Fout bij uitloggen'));
        });
      }

      // Logout knop linker menu
      const logoutButton = document.getElementById('logoutButton');
      if (logoutButton) {
        logoutButton.addEventListener('click', (e) => {
          e.preventDefault();

          fetch('logout.php', { method: 'POST' })
            .then(res => res.json())
            .then(data => {
              if (data.success) {
                window.location.href = 'index.html';
              } else {
                alert('Uitloggen mislukt.');
              }
            })
            .catch(err => console.error(err));
        });
      }

      // --- Taalkeuze vlag dropdown ---
      const select = headerContainer.querySelector('.custom-lang-select');
      const dropdown = headerContainer.querySelector('#flagDropdown');

      if (select && dropdown) {
        select.addEventListener('click', function (e) {
          e.stopPropagation();
          select.classList.toggle('open');
        });

        dropdown.addEventListener('click', function (e) {
          const item = e.target.closest('div[data-value]');
          if (item) {
            const targetHref = item.dataset.href;
            const currentPage = window.location.pathname.split('/').pop();
            if (targetHref && targetHref !== currentPage) {
              window.location.href = targetHref;
            } else {
              select.classList.remove('open');
            }
          }
        });

        document.addEventListener('click', function (e) {
          if (!select.contains(e.target)) {
            select.classList.remove('open');
          }
        });
      } else {
        console.warn('Dropdown elementen niet gevonden in de header');
      }
      // --- Cookie banner initialiseren ---
      function initCookieBanner() {
        const banner = document.getElementById("cookie-banner");
        const acceptAll = document.getElementById("accept-all");
        const acceptFunctional = document.getElementById("accept-functional");
        const cookiesAcceptedField = document.getElementById("cookiesAccepted"); // hidden login field

        if (!banner) return; // Banner bestaat niet, stop

        function setCookie(name, value, days) {
          const date = new Date();
          date.setTime(date.getTime() + (days*24*60*60*1000));
          const expires = "expires=" + date.toUTCString();
          document.cookie = name + "=" + value + ";" + expires + ";path=/;SameSite=Lax";
        }

        function getCookie(name) {
          const cname = name + "=";
          const decodedCookie = decodeURIComponent(document.cookie);
          const ca = decodedCookie.split(';');
          for (let i = 0; i < ca.length; i++) {
            let c = ca[i].trim();
            if (c.indexOf(cname) === 0) return c.substring(cname.length, c.length);
          }
          return "";
        }

        const cookieConsent = getCookie("cookieConsent");

        if (cookieConsent && (cookieConsent === "all" || cookieConsent === "functional")) {
            banner.style.display = "none";
            if (cookiesAcceptedField) {
                cookiesAcceptedField.value = "1"; // lange termijn cookie mag
            }
        } else {
            banner.style.display = "block";
        }

        if (acceptAll) {
          acceptAll.addEventListener("click", () => {
            setCookie("cookieConsent", "all", 365);
            banner.style.display = "none";
          });
        }

        if (acceptFunctional) {
          acceptFunctional.addEventListener("click", () => {
            setCookie("cookieConsent", "functional", 365);
            banner.style.display = "none";
          });
        }
      }

      // ✅ Pas hier aanroepen, NA het inladen van de header
      initCookieBanner();
    })
    .catch(error => {
      console.error('Fout bij laden header:', error);
    });
    
  // --- Footer inladen via fetch ---
  fetch('footer.html')
    .then(response => {
      if (!response.ok) throw new Error('Footer kon niet geladen worden');
      return response.text();
    })
    .then(html => {
      const footerContainer = document.getElementById('footer-placeholder');
      if (footerContainer) {
        footerContainer.innerHTML = html;
      } else {
        console.warn('Geen container gevonden voor footer');
      }
    })
    .catch(error => {
      console.error('Fout bij laden footer:', error);
    });

  // --- Winkelwagen beheer ---
  const cartDropdown = document.getElementById('cartDropdown');
  const cartItemsContainer = document.getElementById('cart-items');
  const subtotalDisplay = document.getElementById('cart-subtotal');
  const cartSummary = document.getElementById('cart-summary');

  function fetchCart() {
    fetch('cart.php?action=get_cart')
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          renderCartItems(data.cart);
          renderCartDropdown(data.cart);
        } else {
          console.error('Fout bij ophalen winkelwagen:', data.message);
        }
      })
      .catch(err => {
        console.error('Fout bij ophalen cart:', err);
      });
  }

  function calculateSubtotal(cart) {
    if (!subtotalDisplay) return;
    const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    subtotalDisplay.textContent = `€${total.toFixed(2)}`;
  }

  function renderCartItems(cart) {
    if (!cartItemsContainer) return;
    cartItemsContainer.innerHTML = "";

    if (cart.length === 0) {
      let emptyMsg = cartItemsContainer.querySelector('.empty-cart-message');
      if (!emptyMsg) {
        emptyMsg = document.createElement('p');
        emptyMsg.className = 'empty-cart-message';
        emptyMsg.textContent = 'Je winkelwagen is leeg';
        cartItemsContainer.appendChild(emptyMsg);
      }
      emptyMsg.style.display = "block";

      if (subtotalDisplay) subtotalDisplay.textContent = "€0,00";
      if (cartSummary) cartSummary.style.display = "none";
      return;
    }

    const emptyMsg = cartItemsContainer.querySelector('.empty-cart-message');
    if (emptyMsg) emptyMsg.style.display = "none";

    if (cartSummary) cartSummary.style.display = "block";

    cart.forEach(item => {
      const itemDiv = document.createElement('div');
      itemDiv.classList.add('cart-item');
      itemDiv.innerHTML = `
        <img src="${item.image}" alt="${item.name}" class="item-image">
        <div class="item-info">
          <h3>${item.name}</h3>
          <p>${item.variant || ''}</p>
          <p>Prijs: €${item.price.toFixed(2)}</p>
          <label>
            Aantal:
            <input type="number" value="${item.quantity}" min="1" data-id="${item.product_id}" class="quantity-input">
          </label>
          <button class="remove-item" data-id="${item.product_id}">
            <img src="trash bin/trash bin.png" class="remove-icon remove-icon-light" alt="Verwijderen">
            <img src="trash bin/trash bin (dark mode).png" class="remove-icon remove-icon-dark" alt="Verwijderen">
          </button>
        </div>
      `;
      cartItemsContainer.appendChild(itemDiv);
    });

    calculateSubtotal(cart);
  }

  function renderCartDropdown(cart) {
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
      li.innerHTML = `
        <span class="item-text">${item.name} <span class="item-price">€${item.price.toFixed(2)}</span></span>
      `;
      ul.appendChild(li);
    });

    cartDropdown.appendChild(ul);
  }

  function updateQuantityOnServer(productId, quantity) {
    fetch('cart.php?action=update_quantity', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ product_id: productId, quantity: quantity })
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        fetchCart();
      } else {
        alert('Fout bij bijwerken hoeveelheid');
      }
    });
  }

  function removeItemFromServer(productId) {
    fetch('cart.php?action=remove_item', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ product_id: productId })
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        fetchCart();
      } else {
        alert('Fout bij verwijderen item');
      }
    });
  }

  document.addEventListener('change', function (e) {
    if (e.target.classList.contains('quantity-input')) {
      const productId = parseInt(e.target.dataset.id, 10);
      const quantity = parseInt(e.target.value, 10);
      if (!isNaN(quantity) && quantity > 0) {
        updateQuantityOnServer(productId, quantity);
      }
    }
  });

  document.addEventListener('click', function (e) {
    if (e.target.closest('.remove-item')) {
      const productId = parseInt(e.target.closest('.remove-item').dataset.id, 10);
      removeItemFromServer(productId);
    }
  });

  fetchCart();

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
      errorMsg.style.fontSize = "0.9em";
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

  if (!voucherList) return; // Alleen uitvoeren als element aanwezig is

  fetch('get_vouchers.php')
    .then(res => {
      if (!res.ok) {
        throw new Error(`HTTP fout! status: ${res.status}`);
      }
      return res.json();
    })
    .then(data => {
      console.log('Voucher data:', data); // debug

      if (data.error) {
        voucherList.innerHTML = `<p>${data.error}</p>`;
        return;
      }

      if (!Array.isArray(data) || data.length === 0) {
        voucherList.innerHTML = `<p>Geen cadeaubonnen gekoppeld.</p>`;
        return;
      }

      const ul = document.createElement('ul');
      ul.classList.add('voucher-list');

      data.forEach(voucher => {
        const li = document.createElement('li');
        li.innerHTML = `
          <strong>Code:</strong> ${voucher.code} | 
          <strong>Waarde:</strong> €${Number(voucher.value).toFixed(2)} | 
          <strong>Status:</strong> ${voucher.is_used ? 'Gebruikt' : 'Beschikbaar'} | 
          <small>Toegevoegd op: ${voucher.redeemed_at}</small>
        `;
        li.style.color = voucher.is_used ? 'red' : 'green';
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
    customAmountInput.disabled = true;

    buttons.forEach(button => {
        button.addEventListener('click', () => {
            // Active class resetten
            buttons.forEach(btn => btn.classList.remove('active'));

            // Klik op deze knop markeren
            button.classList.add('active');

            if (button.dataset.amount === 'custom') {
                // Aangepast geselecteerd -> input zichtbaar en inschakelen
                customAmountInput.style.display = 'block';
                customAmountInput.disabled = false;
                customAmountInput.focus();
                customAmountInput.value = '';
            } else {
                // Ander bedrag -> input verbergen en waarde invullen
                customAmountInput.style.display = 'none';
                customAmountInput.disabled = true;
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