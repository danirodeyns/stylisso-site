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

      // --- Taal dropdown activeren ---
      setupLanguageDropdown(headerContainer);

      applyTranslations(); // vertalingen toepassen
    });

  // ==========================
  // --- Functie: taal dropdown ---
  // ==========================
  async function setupLanguageDropdown(headerContainer) {
    const langSelect = headerContainer.querySelector('.custom-lang-select');
    const selectedFlag = headerContainer.querySelector('#selectedFlag');
    const flagDropdown = headerContainer.querySelector('#flagDropdown');
    if (!langSelect || !selectedFlag || !flagDropdown) return;

    // --- Cookie helpers ---
    function setCookie(name, value, days) {
      const date = new Date();
      date.setTime(date.getTime() + days*24*60*60*1000);
      document.cookie = `${name}=${encodeURIComponent(value)};expires=${date.toUTCString()};path=/;SameSite=Lax`;
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

    // --- Functie: vlag updaten ---
    function applyLanguage(lang) {
      selectedFlag.dataset.value = lang;
      const img = selectedFlag.querySelector('img');
      if (!img) return;
      const map = {
        'be-nl':'flags/be.png','be-fr':'flags/be.png','be-en':'flags/be.png',
        'nl-nl':'flags/nl.png','nl-en':'flags/nl.png',
        'fr-fr':'flags/fr.png','fr-en':'flags/fr.png',
        'de-de':'flags/de.png','de-en':'flags/de.png',
        'lu-fr':'flags/lu.png','lu-de':'flags/lu.png','lu-lb':'flags/lu.png','lu-en':'flags/lu.png'
      };
      img.src = map[lang] || 'flags/be.png';
    }

    // --- Initiële taal bepalen ---
    let lang = getCookie('siteLanguage');
    if (!lang) {
      try {
        // land ophalen via PHP (IP detectie)
        const res = await fetch('detect_country.php');
        const data = await res.json();
        const country = data.country || 'be'; // fallback

        // browsertaal ophalen
        const browserLang = navigator.language || navigator.userLanguage; // bv. "nl-BE"
        let langPart = browserLang.split('-')[0].toLowerCase();

        // combineer land + taal
        lang = `${country}-${langPart}`;

        // valideer tegen beschikbare combinaties
        const validCombinations = ['be-nl','be-fr','be-en','nl-nl','nl-en','fr-fr','fr-en','de-de','de-en','lu-fr','lu-de','lu-lb','lu-en'];
        if (!validCombinations.includes(lang)) {
          lang = country === 'be' ? 'be-nl' : country+'-en'; // fallback
        }

        setCookie('siteLanguage', lang, 365);
      } catch(err) {
        console.error('Kan taal + land niet automatisch detecteren:', err);
        lang = 'be-nl';
      }
    }
    applyLanguage(lang);
    applyTranslations(); // vertalingen toepassen

    // --- Event listeners ---
    selectedFlag.addEventListener('click', e => {
      e.stopPropagation();
      langSelect.classList.toggle('open');
    });

    flagDropdown.querySelectorAll('div[data-value]').forEach(div => {
      div.addEventListener('click', () => {
        const newLang = div.dataset.value;
        setCookie('siteLanguage', newLang, 365);
        applyLanguage(newLang);
        langSelect.classList.remove('open');
        applyTranslations(); // vertalingen toepassen
        document.dispatchEvent(new CustomEvent("languageChanged", { detail: { lang: newLang}}));
      });
    });

    document.addEventListener('click', e => {
      if (!langSelect.contains(e.target)) langSelect.classList.remove('open');
    });
  }

  // --- fetchCart functie (gecombineerd) ---
  function fetchCart(cartDropdown) {
    fetchWithLang('cart.php?action=get_cart')
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
      emptyMsg.setAttribute('data-i18n', 'script_cart_empty');
      cartItemsContainer.appendChild(emptyMsg);
      applyTranslations();
      if (subtotalDisplay) subtotalDisplay.textContent = "€0,00";
      return;
    }

    if (cartSummary) cartSummary.style.display = "block";

    cart.forEach((item) => {
      const itemDiv = document.createElement('div');
      itemDiv.classList.add('cart-item');

      const idAttr = item.id
        ? `data-id="${item.id}"`
        : (item.index !== undefined ? `data-index="${item.index}"` : '');

      // ✅ Voucher image logica zoals in de dropdown
      let imageHtml = '';
      if (item.type === 'voucher') {
        imageHtml = `
          <div class="item-image-wrapper">
            <img src="${item.image}" alt="${item.name}" class="item-image item-image-light"/>
            ${item.dark_image ? `<img src="${item.dark_image}" alt="${item.name}" class="item-image item-image-dark"/>` : ''}
          </div>
        `;
      } else {
        imageHtml = `<img src="${item.image}" alt="${item.name}" class="item-image"/>`;
      }

      // ✅ Toon of verberg aantal afhankelijk van type
      const quantityHtml = item.type === 'voucher' ? '' : `
        <label>
          <span data-i18n="script_cart_amount">Aantal:</span>
          <input type="number" value="${item.quantity}" min="1" ${idAttr} data-type="${item.type}" class="quantity-input">
        </label>
      `;

      itemDiv.innerHTML = `
        ${imageHtml}
        <div class="item-info">
          <h3>${item.name}</h3>
          <p>${item.variant || ''}</p>
          ${item.maat ? `<p><span data-i18n="script_cart_size">Maat:</span> ${item.maat}</p>` : ''}
          <p><span data-i18n="script_cart_price">Prijs:</span> €${parseFloat(item.price).toFixed(2)}</p>
          ${quantityHtml}
          <button class="remove-item" ${idAttr} data-type="${item.type}">
            <img src="trash bin/trash bin.png" class="remove-icon remove-icon-light" alt="Verwijderen">
            <img src="trash bin/trash bin (dark mode).png" class="remove-icon remove-icon-dark" alt="Verwijderen">
          </button>
        </div>
      `;

      cartItemsContainer.appendChild(itemDiv);

      // ✅ Vertalingen toepassen op alleen dit nieuwe item
      applyTranslations(itemDiv);

      // ✅ Klikbaar maken — alleen als product_id bestaat
      if (item.product_id) {
        const productId = String(item.product_id).trim();
        itemDiv.addEventListener('click', (e) => {
          // voorkom klikken op knoppen of invoervelden
          if (e.target.closest('input, button, .quantity-input, .remove-item')) return;

          const target = new URL('productpagina.html', window.location.href);
          target.searchParams.set('id', productId);

          window.location.href = target.toString();
        });
        itemDiv.style.cursor = 'pointer';
      }
    });

    // ✅ Eventlisteners toevoegen voor alle quantity inputs (alleen niet-vouchers)
    cartItemsContainer.querySelectorAll('.quantity-input').forEach(input => {
      input.addEventListener('change', e => {
        const newQty = parseInt(e.target.value);
        const id = e.target.dataset.id;
        const type = e.target.dataset.type;

        fetchWithLang('cart.php?action=update_quantity', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `id=${encodeURIComponent(id)}&type=${encodeURIComponent(type)}&quantity=${newQty}&csrf_token=${encodeURIComponent(window.csrfToken)}`
        })
        .then(data => {
          if (data.success) fetchCart(document.getElementById('cartDropdown'));
        })
        .catch(err => console.error('Fout bij update_quantity:', err));
      });
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
      emptyMsg.setAttribute('data-i18n', 'script_cart_empty');
      cartDropdown.appendChild(emptyMsg);
      applyTranslations();
      return;
    }

    const ul = document.createElement('ul');
    ul.classList.add('dropdown-cart-list');

    cart.forEach(item => {
      const li = document.createElement('li');

      let imageHtml = '';
      if (item.type === 'voucher') {
        imageHtml = `
          <div class="dropdown-item-image-wrapper">
            <img src="${item.image}" alt="${item.name}" class="dropdown-item-image dropdown-item-image-light"/>
            ${item.dark_image ? `<img src="${item.dark_image}" alt="${item.name}" class="dropdown-item-image dropdown-item-image-dark"/>` : ''}
          </div>
        `;
      } else {
        imageHtml = `<img src="${item.image}" alt="${item.name}" class="dropdown-item-image"/>`;
      }

      li.innerHTML = `
        ${imageHtml}
        <span class="item-text">${item.name} <span class="item-price">€${parseFloat(item.price).toFixed(2)}</span></span>
      `;
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
      applyTranslations(); // vertalingen toepassen
    })
    .catch(err => console.error('Fout bij laden footer:', err));

  // Plaats dit bovenaan, binnen DOMContentLoaded, maar buiten andere functies
  function setCookie(name, value, days) {
    const date = new Date();
    date.setTime(date.getTime() + days*24*60*60*1000);
    document.cookie = `${name}=${encodeURIComponent(value)};expires=${date.toUTCString()};path=/;SameSite=Lax`;
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
  
    // --- Header user controls ---
  function setupHeaderUserControls(headerContainer) {
    fetch('current_user.php')
      .then(res => res.json())
      .then(data => {
        let currentLang = getCookie('siteLanguage') || 'be-nl';
        const loginBtn = headerContainer.querySelector('#loginBtn');
        const registerBtn = headerContainer.querySelector('#registerBtn');
        const userDisplay = headerContainer.querySelector('#userDisplay');
        const userDropdown = headerContainer.querySelector('#userDropdown');

        if (data.loggedIn) {
          if (loginBtn) loginBtn.style.display = 'none';
          if (registerBtn) registerBtn.style.display = 'none';
          if (userDisplay) {
            userDisplay.setAttribute('data-i18n', 'script_welcome');
            userDisplay.dataset.extraText = data.userName;
            userDisplay.textContent = `${translations[currentLang]?.script_welcome || 'Welkom'}, ${data.userName}`;
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

  // --- Cookie banner + GA & Ads ---
  function initCookieBanner() {
    const banner = document.getElementById("cookie-banner");
    if (!banner) return;

    const acceptAll = document.getElementById("accept-all");
    const acceptAnalytics = document.getElementById("accept-functional-analytics");
    const acceptFunctional = document.getElementById("accept-functional");

    function setCookie(name, value, days) {
      const date = new Date();
      date.setTime(date.getTime() + (days*24*60*60*1000));
      document.cookie = `${name}=${encodeURIComponent(value)};expires=${date.toUTCString()};path=/;SameSite=Lax`;
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

    // --- Functie voor functionele cookie: taal onthouden ---
    function setLanguage(lang) {
      setCookie("siteLanguage", lang, 365);
      console.log("Taal opgeslagen als:", lang);
    }

    function getLanguage() {
      return getCookie("siteLanguage") || "nl"; // fallback naar Nederlands
    }

    // --- Controleer bestaande consent ---
    const consentCookie = getCookie("cookieConsent");
    let consent = null;
    if (consentCookie) {
      try {
        consent = JSON.parse(consentCookie);
        banner.style.display = "none";
      } catch(e) {
        consent = null;
        banner.style.display = "block";
      }
    } else {
      banner.style.display = "block";
    }

    if (consent && consent.functional) {
      const input = document.getElementById('cookiesAccepted');
      if (input) input.value = "1";
    }

    // --- Functie om gtag.js te laden en config te doen ---
    function loadGTAG() {
      console.log("gtag.js geladen");

      const gtagScript = document.createElement('script');
      gtagScript.async = true;
      gtagScript.src = "https://www.googletagmanager.com/gtag/js?id=G-33YQ0QLLQS";
      document.head.appendChild(gtagScript);

      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      window.gtag = gtag;

      gtag('js', new Date());

      if (consent && consent.analytics) gtag('config', 'G-33YQ0QLLQS');
      if (consent && consent.marketing) gtag('config', 'AW-XXXXXXXXX'); // vul je Google Ads ID in
    }

    // --- Bij bestaande consent direct laden ---
    if (consent && (consent.analytics || consent.marketing)) {
      loadGTAG();
    }

    // --- Event listeners ---
    if (acceptAll) acceptAll.addEventListener("click", () => {
      const obj = { functional: true, analytics: true, marketing: true };
      setCookie("cookieConsent", JSON.stringify(obj), 365);
      banner.style.display = "none";
      consent = obj;
      loadGTAG();
    });

    if (acceptAnalytics) acceptAnalytics.addEventListener("click", () => {
      const obj = { functional: true, analytics: true, marketing: false };
      setCookie("cookieConsent", JSON.stringify(obj), 365);
      banner.style.display = "none";
      consent = obj;
      loadGTAG();
    });

    if (acceptFunctional) acceptFunctional.addEventListener("click", () => {
      const obj = { functional: true, analytics: false, marketing: false };
      setCookie("cookieConsent", JSON.stringify(obj), 365);
      banner.style.display = "none";
      consent = obj;
    });

    // --- Pas taal toe bij elke load ---
    const userLang = getLanguage();
    console.log("Huidige taal:", userLang);
    // hier kan je je pagina vertalen of aanpassen op basis van userLang
  }

  document.addEventListener("DOMContentLoaded", initCookieBanner);

  // --- Functies voor update & remove items ---
  function updateQuantityOnServer(itemId, itemType, quantity, itemIndex = null) {
    const payload = { type: itemType, quantity };
    if (itemId) payload.id = itemId;
    if (itemIndex !== null) payload.index = itemIndex;

    fetchWithLang('cart.php?action=update_quantity', {
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

    return fetchWithLang('cart.php?action=remove_item', {
      method: 'POST',
      body: formData
    })
    .then(data => { // data is al JSON
      if (data.success) return true;
      else {
        const msg = data.message || translations[lang]?.script_remove_item_failed || 'Fout bij verwijderen item';
        alert(msg);
        return false;
      }
    });
  }

  // --- Event listener ---
  document.addEventListener('click', function(e) {
    const btn = e.target.closest('.remove-item');
    if (!btn) return;

    const type = btn.dataset.type;
    const id = btn.dataset.id ? parseInt(btn.dataset.id) : null;
    const index = btn.dataset.index ? parseInt(btn.dataset.index) : null;
    console.log('Verwijder:', {type, id, index});

    removeItemFromServer(id, type, index).then(success => {
      if (success) {
        // Haal de volledige cart opnieuw op van server
        fetchCart(document.getElementById('cartDropdown'));
        }
      })
    });

  loadNewProducts();
  loadPopularProducts();

    // --- update winkelwagen bij taalwijziging ---
  document.addEventListener("languageChanged", () => {
    const cartDropdown = document.getElementById('cartDropdown');
    fetchCart(cartDropdown);

    // Extra: grote winkelwagenpagina verversen
    if (document.getElementById('cart-items')) {
      fetchCart(cartDropdown);
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
              console.log("Server response:", data);

              const paymentErrorEl = document.getElementById('payment-error');
              if (paymentErrorEl) paymentErrorEl.textContent = ''; // wis vorige melding

              if (data.trim() === "success") {
                  // ✅ Succes: redirect
                  window.location.href = 'bedankt.html';
                  return;
              }

              // 🚫 Alleen deze fout tonen
              if (data.includes("Ongeldige betaalmethode")) {
                if (paymentErrorEl) {
                    // Zet data-i18n attribuut zodat applyTranslations het kan oppakken
                    paymentErrorEl.setAttribute('data-i18n', 'script_ongeldige_betaalmethode');

                    // Optioneel: fallback tekst direct instellen
                    paymentErrorEl.textContent = translations['be-nl']['script_ongeldige_betaalmethode'] || 'Ongeldige betaalmethode.';

                    paymentErrorEl.style.color = "red";
                    paymentErrorEl.style.fontSize = "0.9rem";
                    paymentErrorEl.style.marginTop = "4px";

                    // Vertaling toepassen (zorgt ervoor dat taalwijziging ook werkt)
                    if (typeof applyTranslations === 'function') {
                        applyTranslations();
                    }
                }
                return;
              }

              // Alle andere responses worden enkel gelogd
              console.error('Server response (genegeerd):', data);
          })
          .catch(err => {
              console.error('Fout bij afrekenen:', err);
              const msgBox = document.createElement("div");
              msgBox.setAttribute("data-i18n", "script_checkout_error");
              document.body.appendChild(msgBox);
              applyTranslations(msgBox);
              alert(msgBox.textContent);
              msgBox.remove();
          });
      });
  }

  const subtotalOrder = document.getElementById('subtotalOrder');
  const redeemVoucherSpan = document.getElementById('redeem_voucher');
  const savedVoucherDropdown = document.getElementById('saved_voucher');
  const taxAmount = document.getElementById('taxAmount');
  const totalAmount = document.getElementById('totalAmount');

  let cartData = [];
  let voucherAmount = 0;
  let vouchers = [];

  // Haal producten uit cart
  fetchWithLang('cart.php?action=get_cart')
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
              const statusSpan = document.createElement('span');
              statusSpan.setAttribute('data-i18n', v.is_used ? 'script_voucher_used' : 'script_voucher_available');
              applyTranslations(statusSpan);
              option.textContent = `${v.code} - €${v.value.toFixed(2)} (${statusSpan.textContent})`;
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

      // Voucher = ingezette voucher (maximaal gelijk aan subtotal)
      const voucher = Math.min(voucherAmount, subtotal);

      // Shipping: standaard 5, behalve als subtotal >= 50 of alle items vouchers zijn
      let shipping = 5.00;
      if (subtotal >= 50 || allVouchers) {
          shipping = 0.00;
      }

      // BTW = subtotaal * 0.21 (subtotaal is zonder voucher)
      const vat = subtotal * 0.21;

      // Totaal = subtotaal + shipping – voucher
      const total = subtotal + shipping - voucher;

      // Update UI
      if (subtotalOrder) subtotalOrder.textContent = `€${subtotal.toFixed(2)}`;
      if (redeemVoucherSpan) {
        if (voucher > 0) {
          redeemVoucherSpan.textContent = `-€${voucher.toFixed(2)}`;
        } else {
          redeemVoucherSpan.textContent = `€0.00`;
        }
      }
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
    console.log("showError aangeroepen:", { inputId, message }); // ✅ debug

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
    } else {
      console.warn("showError: input niet gevonden:", inputId); // ✅ debug
    }
  }

  // Toon foutmeldingen
  if (params.get('error_email') === 'exists') {
    const span = document.createElement('span');
    span.setAttribute('data-i18n', 'script_register_email_exists');
    document.body.appendChild(span);
    applyTranslations();
    const message = span.textContent; 
    span.remove();
    showError('register-email', message);
  }

  if (params.get('error_password2') === 'nomatch') {
    const span = document.createElement('span');
    span.setAttribute('data-i18n', 'script_register_password_nomatch');
    document.body.appendChild(span);
    applyTranslations();
    const message = span.textContent;
    span.remove();
    showError('register-password', message);
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
    const span = document.createElement('span');
    span.setAttribute('data-i18n', 'script_login_wrong_password');
    document.body.appendChild(span);
    applyTranslations();
    const message = span.textContent; 
    span.remove();
    showError('login-password', message);
  }

  if (params.get('error') === 'email_not_found') {
    const span = document.createElement('span');
    span.setAttribute('data-i18n', 'script_login_email_not_found');
    document.body.appendChild(span);
    applyTranslations();
    const message = span.textContent;
    span.remove();
    showError('login-email', message);
  }

  // Oude waarde e-mail terugzetten bij loginformulier
  const oldLoginEmail = params.get('old_email');
  if (oldLoginEmail) {
    const loginEmailInput = document.getElementById('login-email');
    if (loginEmailInput) loginEmailInput.value = decodeURIComponent(oldLoginEmail);
  }

  const form = document.getElementById('profileForm');
  const messages = document.getElementById('formMessages');
  
  if (params.get("errors")) {
    const errors = params.get("errors").split(",");
    errors.forEach(err => {
      // 1. span maken en data-i18n zetten
      let span = document.createElement('span');
      switch(err) {
        case "name_empty":
          span.setAttribute('data-i18n', 'script_name_required');
          break;
        case "address_empty":
          span.setAttribute('data-i18n', 'script_address_required');
          break;
        case "email_invalid":
          span.setAttribute('data-i18n', 'script_email_invalid');
          break;
        case "email_exists":
          span.setAttribute('data-i18n', 'script_email_exists');
          break;
        case "password_mismatch":
          span.setAttribute('data-i18n', 'script_password_mismatch');
          break;
        case "password_same":
          span.setAttribute('data-i18n', 'script_password_same');
          break;
        default:
          return; // onbekende error, niks doen
      }

      // 2. span tijdelijk toevoegen aan DOM
      span.style.display = "none"; // zodat het niet zichtbaar is
      document.body.appendChild(span);

      // 3. vertaling toepassen
      applyTranslations();

      // 4. vertaalde tekst lezen
      const message = span.textContent;

      // 5. span verwijderen
      span.remove();

      // 6. error tonen bij juiste input
      const inputId = err === "name_empty" ? "name" :
                      err === "address_empty" ? "address" :
                      err === "email_invalid" || err === "email_exists" ? "email" :
                      err === "password_mismatch" ? "passwordConfirm" :
                      err === "password_same" ? "password" :
                      "";

      if (inputId) showError(inputId, message);
    });
  }

  if (params.get("success") === "1") {
    const successEl = document.createElement("p");
    successEl.setAttribute('data-i18n', 'script_profile_updated');
    document.body.appendChild(successEl);
    applyTranslations();
    const message = successEl.textContent;
    successEl.remove();
    successEl.textContent = message;
    successEl.style.color = "green";
    successEl.style.marginTop = "1rem";
    form.appendChild(successEl);
  }

  const billingCheckbox = document.getElementById('different_billing');

  // Billing velden ophalen
  const billingLabel = document.querySelector('label[for="billing_street"]');
  const billingStreetNumber = document.getElementById('billing_street_number');
  const billingPostalCity = document.getElementById('billing_postal_city');
  const billingCountry = document.getElementById('billing_country');

  function toggleBillingFields() {
    const show = billingCheckbox.checked;
    billingLabel.style.display = show ? '' : 'none';
    billingStreetNumber.style.display = show ? '' : 'none';
    billingPostalCity.style.display = show ? '' : 'none';
    billingCountry.style.display = show ? '' : 'none';
  }

  if (billingCheckbox) billingCheckbox.addEventListener('change', toggleBillingFields);

  // Functie om hoofdletters, accenten en spaties te negeren
  function normalizeCountry(str) {
    return str
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim();
  }

  // Toegestane landen
  const allowedCountries = ['belgie', 'belgium', 'belgique'];

  // Validatie bij opslaan/updaten
  const dataform = document.getElementById('user-data-form');
  if (dataform) {
    dataform.addEventListener('submit', (e) => {
      const shippingCountryVal = normalizeCountry(document.getElementById('country').value);
      const billingCountryVal = normalizeCountry(document.getElementById('billing_country').value);

      let errorMessage = '';

      if (!allowedCountries.includes(shippingCountryVal)) {
        errorMessage = `Verzending naar ${document.getElementById('country').value} is nog niet mogelijk.`;
      }

      if (billingCheckbox.checked && !allowedCountries.includes(billingCountryVal)) {
        errorMessage = `Facturatie naar ${document.getElementById('billing_country').value} is nog niet mogelijk.`;
      }

      if (errorMessage !== '') {
        e.preventDefault();
        alert(errorMessage);
        return false;
      }
    });
  }

  // --- Foutmeldingen uit URL tonen (met vertaling) ---
  function showTranslatedError(input, i18nKey, fallbackText) {
    if (!input) return;
    let errorEl = input.nextElementSibling;
    if (!errorEl || !errorEl.classList.contains('error-message')) {
      errorEl = document.createElement('div');
      errorEl.className = 'error-message';
      errorEl.style.color = 'red';
      errorEl.style.fontSize = '0.9em';
      input.parentNode.insertBefore(errorEl, input.nextSibling);
    }
    errorEl.setAttribute('data-i18n', i18nKey);
    errorEl.textContent = fallbackText || '';
    if (typeof applyTranslations === 'function') {
      applyTranslations();
    }
  }

  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('success') === '0') {
    const errors = (urlParams.get('errors') || '').split(',');
    errors.forEach(err => {
      if (err === 'country_not_allowed') {
        const shippingCountry = document.getElementById('country');
        if (shippingCountry) {
          showTranslatedError(
            shippingCountry,
            'script_shipping_country_not_allowed',
            'Verzending naar dit land is nog niet mogelijk.'
          );
        }
      }
      if (err === 'billing_country_not_allowed') {
        const billingCountry = document.getElementById('billing_country');
        if (billingCountry) {
          showTranslatedError(
            billingCountry,
            'script_billing_country_not_allowed',
            'Facturatie naar dit land is nog niet mogelijk.'
          );
        }
      }
    });
  }

  // --- Fetch user data ---
  fetch('get_user_data.php')
    .then(res => res.json())
    .then(data => {
      if (!data.error) {
        // Profielgegevens invullen
        const nameInput = document.getElementById('name');
        const emailInput = document.getElementById('email');
        const newsletterCheckbox = document.getElementById('newsletter');
        const companyInput = document.getElementById('company_name');
        const vatInput = document.getElementById('vat_number');

        if (nameInput) nameInput.value = data.name || '';
        if (emailInput) emailInput.value = data.email || '';
        if (newsletterCheckbox) newsletterCheckbox.checked = (data.newsletter == 1);
        if (companyInput) companyInput.value = data.company_name || '';
        if (vatInput) vatInput.value = data.vat_number || '';

        // Shipping address
        const streetInput = document.getElementById('street');
        const houseInput = document.getElementById('house_number');
        const postalInput = document.getElementById('postal_code');
        const cityInput = document.getElementById('city');
        const countryInput = document.getElementById('country');

        if (data.shipping_address) {
          if (streetInput) streetInput.value = data.shipping_address.street || '';
          if (houseInput) houseInput.value = data.shipping_address.house_number || '';
          if (postalInput) postalInput.value = data.shipping_address.postal_code || '';
          if (cityInput) cityInput.value = data.shipping_address.city || '';
          if (countryInput) countryInput.value = data.shipping_address.country || '';
        }

        // Billing address
        if (data.billing_address && billingCheckbox) {
          billingCheckbox.checked = true;
          toggleBillingFields();

          document.getElementById('billing_street').value = data.billing_address.street || '';
          document.getElementById('billing_house_number').value = data.billing_address.house_number || '';
          document.getElementById('billing_postal_code').value = data.billing_address.postal_code || '';
          document.getElementById('billing_city').value = data.billing_address.city || '';
          document.getElementById('billing_country').value = data.billing_address.country || '';
        } else if (billingCheckbox) {
          billingCheckbox.checked = false;
          toggleBillingFields();
        }

        // Mijn Stylisso overzicht
        const userName = document.getElementById('user-name');
        const userEmail = document.getElementById('user-email');
        const userAddressLine = document.getElementById('user-address-line');
        const userAddress = document.getElementById('user-address');
        const userBillingAddressLine = document.getElementById('user-billing-address-line');
        const userBillingAddress = document.getElementById('user-address-billing');
        const userCompanyLine = document.getElementById('user-company-line');
        const userCompany = document.getElementById('user-company');
        const userVatLine = document.getElementById('user-vat-line');
        const userVat = document.getElementById('user-vat');

        if (userName) userName.textContent = data.name || '';
        if (userEmail) userEmail.textContent = data.email || '';

        // Shipping adres
        if (data.shipping_address) {
          const ship = data.shipping_address;
          const shippingStr = `${ship.street} ${ship.house_number}, ${ship.postal_code} ${ship.city}`;
          if (userAddress) userAddress.textContent = shippingStr;
          if (userAddressLine) userAddressLine.style.display = shippingStr ? '' : 'none';
        } else if (userAddressLine) {
          userAddressLine.style.display = 'none';
        }

        // Billing adres
        if (data.billing_address) {
          const bill = data.billing_address;
          const billingStr = `${bill.street} ${bill.house_number}, ${bill.postal_code} ${bill.city}`;
          if (userBillingAddress) userBillingAddress.textContent = billingStr;
          if (userBillingAddressLine) userBillingAddressLine.style.display = '';
        } else if (userBillingAddressLine) {
          userBillingAddressLine.style.display = 'none';
        }

        // Bedrijf
        if (data.company_name) {
          if (userCompany) userCompany.textContent = data.company_name;
          if (userCompanyLine) userCompanyLine.style.display = '';
        } else if (userCompanyLine) {
          userCompanyLine.style.display = 'none';
        }

        // BTW nummer
        if (data.vat_number) {
          if (userVat) userVat.textContent = data.vat_number;
          if (userVatLine) userVatLine.style.display = '';
        } else if (userVatLine) {
          userVatLine.style.display = 'none';
        }

      } else {
        console.error('Fout bij ophalen van gebruikersgegevens:', data.error);
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
    const orders = await fetchWithLang('get_orders.php');

    if (!orders || orders.length === 0) {
      const p = document.createElement('p');
      p.setAttribute('data-i18n', 'script_order_none');
      container.innerHTML = '';
      container.appendChild(p);
      applyTranslations(p);
      return;
    }

    let html = '';
    orders.forEach(order => {
      const items = order.products || [];
      let productHtml = '';

      items.forEach(item => {
        if (item.type === 'voucher') {
          productHtml += `
            <div class="order-product-row voucher-row" style="cursor:pointer;">
              <div class="order-product-info">
                <img src="cadeaubon/voucher.png" alt="Cadeaubon" class="order-product-img order-product-img-light"/>
                <img src="cadeaubon/voucher (dark mode).png" alt="Cadeaubon" class="order-product-img order-product-img-dark"/>
                <span class="order-product-name" data-i18n="script_order_voucher">Cadeaubon</span>
              </div>
              <div class="order-product-details">
                <span class="order-product-price">€${parseFloat(item.price).toFixed(2)}</span>
              </div>
            </div>
          `;
          applyTranslations();
        } else if (item.type === 'product') {
          // Voeg maat toe als deze niet null is
          const maatHtml = item.maat ? ` (<span class="order-product-size">${item.maat}</span>)` : '';

          productHtml += `
            <div class="order-product-row" data-product-id="${item.id}" style="cursor:pointer;">
              <div class="order-product-info">
                <img src="${item.image}" alt="${item.name}" class="order-product-img">
                <span class="order-product-name">${item.name}${maatHtml}</span>
              </div>
              <div class="order-product-details">
                <span class="order-product-qty"><span data-i18n="script_order_amount">Aantal</span>: ${item.quantity}</span>
                <span class="order-product-price">€${parseFloat(item.price).toFixed(2)}</span>
              </div>
            </div>
          `;
        }
      });

      html += `
        <div class="order-card" data-order-id="${order.id}">
          <div class="order-card-header">
            <div class="order-card-title">
              <span><span data-i18n="script_order_number">Order</span> ${order.id} - <span data-i18n="script_order_date">Aankoopdatum</span>: ${formatDate(order.created_at)}</span>
            </div>
            <button class="invoice-btn" title="Factuur bekijken" onclick="event.stopPropagation(); openInvoicePDF(${order.id});">
              <img src="factuursymbool/factuursymbool.png" alt="Factuur" class="invoice-icon invoice-icon-light" />
              <img src="factuursymbool/factuursymbool (dark mode).png" alt="Factuur" class="invoice-icon invoice-icon-dark" />
            </button>
          </div>
          <div class="order-products-list">
            ${productHtml}
          </div>
          <div class="order-detail-row">
            <span class="order-detail-label" data-i18n="script_order_total_label"></span>
            <span class="order-detail-value">€${parseFloat(order.total_price).toFixed(2)}</span>
          </div>
          <div class="order-detail-row">
            <span class="order-detail-label" data-i18n="script_order_status_label"></span>
            <span class="order-detail-value">${order.status.charAt(0).toUpperCase() + order.status.slice(1)}</span>
          </div>
        </div>
      `;
    });

    container.innerHTML = html;
    applyTranslations();

    // Factuurknop klikbaar
    container.querySelectorAll('.invoice-btn').forEach(button => {
      button.style.cursor = 'pointer';
      button.addEventListener('click', (event) => {
        event.stopPropagation();
        const orderCard = button.closest('.order-card');
        if (!orderCard) return;
        const orderId = orderCard.getAttribute('data-order-id');
        openInvoicePDF(orderId);
      });
    });

    // Producten klikbaar naar productpagina
    container.querySelectorAll('.order-product-row[data-product-id]').forEach(row => {
      row.addEventListener('click', () => {
        const productId = row.getAttribute('data-product-id');
        window.location.href = `productpagina.html?id=${productId}`;
      });
    });

    // Vouchers klikbaar naar cadeaubonnen_kopen.html
    container.querySelectorAll('.voucher-row').forEach(row => {
      row.addEventListener('click', () => {
        window.location.href = 'cadeaubonnen_kopen.html';
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
    const order = await fetchWithLang('get_last_order.php'); // fetchWithLang retourneert al JSON

    if (order.error) {
      let i18nKey = 'script_last_order_error';
      if (order.error.toLowerCase().includes('nog geen bestellingen')) {
        i18nKey = 'script_order_none';
      }
      container.innerHTML = `<p data-i18n="${i18nKey}"></p>`;
      applyTranslations(container);
      return;
    }

    let products = [];
    let vouchers = [];

    if (order.products && order.products.length) {
      order.products.forEach(item => {
        if (item.type === 'voucher' || (item.name && item.name.toLowerCase().includes('cadeaubon'))) {
          vouchers.push(item);
        } else {
          products.push(item);
        }
      });
    }

    let productList = '';

    if (products.length > 0) {
      productList += products.map(p => `${p.quantity} x ${p.product_name || p.name}`).join(', ');
    }

    if (vouchers.length > 0) {
      if (productList) productList += ', ';
      productList += vouchers.length === 1
        ? `${vouchers[0].product_name || vouchers[0].name}`.replace(/Cadeaubon: /, '')
        : 'Cadeaubon(nen)';
    }

    container.innerHTML = `
      <span><span data-i18n="script_order_number"></span>: ${order.id}</span><br>
      <span><span data-i18n="script_order_date"></span>: ${formatDate(order.created_at)}</span><br>
      <span><span data-i18n="script_order_products"></span>: ${productList || `<em data-i18n="script_no_products"></em>`}</span><br>
      <span><span data-i18n="script_order_total_label"></span> €${parseFloat(order.total_price).toFixed(2)}</span><br>
      <span><span data-i18n="script_order_status_label"></span> ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}</span>
    `;
    applyTranslations(container);

  } catch (err) {
    container.innerHTML = `<p data-i18n="script_last_order_load_error"></p>`;
    applyTranslations(container);
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

document.addEventListener('languageChanged', () => {
  loadOrders();
});

document.addEventListener('languageChanged', () => {
  loadLastOrder();
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
    const data = await fetchWithLang("get_last_order_bedankt.php");

    if (!data || data.error) {
      container.innerHTML = ''; // eerst leegmaken

      const p = document.createElement('p');
      if (data && data.error) {
        p.textContent = data.error; // serverfout tonen
      } else {
        p.setAttribute('data-i18n', 'script_no_order_found'); // vertaling gebruiken
        applyTranslations(p);
      }

      container.appendChild(p);
      return;
    }

    let html = `
      <h2><span data-i18n="script_bedankt_title">Order</span> ${data.id}</h2>
      <p><strong data-i18n="script_bedankt_date">Datum:</strong> ${formatDate(data.created_at)}</p>
      <table class="bedankt-table">
        <thead>
          <tr>
            <th data-i18n="script_bedankt_product">Product</th>
            <th data-i18n="script_bedankt_amount">Aantal</th>
            <th data-i18n="script_bedankt_unit_price">Eenheidsprijs</th>
            <th data-i18n="script_bedankt_price">Prijs</th>
          </tr>
        </thead>
        <tbody>
    `;

    data.products.forEach(item => {
      let productHtml = "";

      if (item.type === "voucher") {
        productHtml = `
          <div style="display:flex; align-items:center; gap:8px;">
            <img src="cadeaubon/voucher.png" alt="Cadeaubon" class="order-product-img order-product-img-light" style="width:40px; height:40px; object-fit:cover;">
            <img src="cadeaubon/voucher (dark mode).png" alt="Cadeaubon" class="order-product-img order-product-img-dark" style="width:40px; height:40px; object-fit:cover;">
            <span data-i18n="script_voucher_name"></span>
          </div>
        `;
      } else {
        productHtml = `
          <div style="display:flex; align-items:center; gap:8px;">
            <img src="${item.image || 'placeholder.jpg'}" 
                alt="${item.name}">
            <span>${item.name}</span>
            ${item.maat ? `<span style="margin-left:8px;">(${item.maat})</span>` : ''}
          </div>
        `;
      }

      html += `
        <tr class="${item.type === 'voucher' ? 'voucher-row' : ''}">
          <td>${productHtml}</td>
          <td>${item.quantity}</td>
          <td>€${parseFloat(item.price).toFixed(2)}</td>
          <td>€${(parseFloat(item.price) * item.quantity).toFixed(2)}</td>
        </tr>
      `;
    });

    html += `
        </tbody>
      </table>
      <h3><span data-i18n="script_order_total_label"></span> €${parseFloat(data.total_price).toFixed(2)}</h3>
    `;

    container.innerHTML = html;
    applyTranslations(container);
  } catch (err) {
    container.innerHTML = '';
    const p = document.createElement('p');
    p.setAttribute('data-i18n', 'script_order_load_failed');
    applyTranslations(p);
    container.appendChild(p);
    console.error(err);
  }
}

// Auto uitvoeren alleen op bedankt.html
document.addEventListener("DOMContentLoaded", () => {
  if (document.getElementById("order-details")) {
    loadLastOrderDetails();
  }
});

document.addEventListener('DOMContentLoaded', () => {

  // ---------------------------
  // Retourkaarten (optioneel)
  // ---------------------------
  const returnCardsContainer = document.getElementById('return-cards');
  if (returnCardsContainer) {

    // CSRF-token ophalen
    let csrfToken = '';
    async function ensureCsrf() {
      if (csrfToken) return csrfToken;
      try {
        const data = await fetch('csrf.php').then(res => res.json());
        csrfToken = data.csrf_token || '';
        return csrfToken;
      } catch (err) {
        console.error('Fout bij ophalen CSRF-token:', err);
        return '';
      }
    }

    // Retourkaarten laden
    async function loadReturns() {
      try {
        const data = await fetch('retourneren.php').then(res => res.json());
        returnCardsContainer.innerHTML = '';

        if (!data || data.length === 0 || data.error) {
          const p = document.createElement('p');
          p.className = data?.length === 0 ? 'no-orders' : '';
          p.setAttribute('data-i18n', data?.error ? 'script_returns_error' : 'script_order_none');
          returnCardsContainer.appendChild(p);
          applyTranslations(returnCardsContainer);
          return;
        }

        const orders = {};
        data.forEach(item => {
          if (!orders[item.order_id]) orders[item.order_id] = { order_date: item.order_date, items: [] };
          orders[item.order_id].items.push(item);
        });

        const sortedOrderIds = Object.keys(orders).sort((a,b) => new Date(orders[b].order_date) - new Date(orders[a].order_date));

        sortedOrderIds.forEach(orderId => {
          const order = orders[orderId];
          const orderDiv = document.createElement('div');
          orderDiv.className = 'return-card';

          const dateObj = new Date(order.order_date);
          const formattedDate = `${String(dateObj.getDate()).padStart(2,'0')}-${String(dateObj.getMonth()+1).padStart(2,'0')}-${dateObj.getFullYear()}`;

          const orderHeader = document.createElement('h3');
          orderHeader.innerHTML = `<span data-i18n="script_order_number"></span> ${orderId} - <span data-i18n="script_order_date"></span>: ${formattedDate}`;
          orderDiv.appendChild(orderHeader);

          order.items.forEach(product => {
            const productDiv = document.createElement('div');
            productDiv.className = 'return-product';

            // ✅ Maak de hele kaart klikbaar naar de productpagina
            productDiv.addEventListener('click', () => {
              if (product.product_id) {
                window.location.href = `productpagina.html?id=${product.product_id}`;
              }
            });
            productDiv.style.cursor = 'pointer';

            const img = document.createElement('img');
            img.src = product.image;
            img.alt = product.product_name;
            img.className = 'return-product-img';

            const name = document.createElement('p');
            name.textContent = product.product_name + (product.size ? ` (${product.size})` : '');

            const returnLink = document.createElement('a');
            returnLink.href = '#';
            returnLink.className = 'return-link';

            // ❗ Zorg dat klikken op retourlink de kaartlink niet triggert
            returnLink.addEventListener('click', e => e.stopPropagation());

            const today = new Date();
            const orderDate = new Date(order.order_date);
            const diffDays = (today - orderDate) / (1000 * 60 * 60 * 24);

            if (diffDays > 30) {
              returnLink.setAttribute('data-i18n', 'script_order_status_expired');
              returnLink.style.pointerEvents = 'none';
              returnLink.classList.add('return-expired');
            } else {
              switch(product.return_status) {
                case 'requested':
                  returnLink.setAttribute('data-i18n', 'script_order_status_requested');
                  returnLink.style.pointerEvents = 'none';
                  returnLink.classList.add('return-requested');
                  break;
                case 'approved':
                  returnLink.setAttribute('data-i18n', 'script_order_status_approved');
                  returnLink.style.pointerEvents = 'none';
                  returnLink.classList.add('return-approved');
                  break;
                case 'processed':
                  returnLink.setAttribute('data-i18n', 'script_order_status_processed');
                  returnLink.style.pointerEvents = 'none';
                  returnLink.classList.add('return-processed');
                  break;
                case 'rejected':
                  returnLink.setAttribute('data-i18n', 'script_order_status_rejected');
                  returnLink.style.pointerEvents = 'none';
                  returnLink.classList.add('return-rejected');
                  break;
                default:
                  returnLink.setAttribute('data-i18n', 'script_order_status_start');
                  returnLink.addEventListener('click', async e => {
                    e.preventDefault();
                    await ensureCsrf();
                    if (!csrfToken) {
                      alert(document.querySelector('[data-i18n="script_csrf_not_loaded"]').textContent);
                      return;
                    }

                    fetch('submit_returns.php', {
                      method: 'POST',
                      headers: { 'Content-Type':'application/x-www-form-urlencoded' },
                      body: `order_item_id=${product.order_item_id}&reason=Retour+verzoek&csrf_token=${csrfToken}`
                    })
                    .then(res => res.json())
                    .then(resp => {
                      if (resp.success) {
                        returnLink.setAttribute('data-i18n', 'script_return_request_success');
                        returnLink.style.pointerEvents = 'none';
                        returnLink.classList.add('return-requested');
                        applyTranslations(returnLink);
                      } else {
                        alert(resp.error || document.querySelector('[data-i18n="script_return_request_error"]').textContent);
                      }
                    })
                    .catch(err => alert(document.querySelector('[data-i18n="script_return_request_error"]').textContent + ': ' + err));
                  });
              }
            }

            productDiv.appendChild(img);
            productDiv.appendChild(name);
            productDiv.appendChild(returnLink);
            orderDiv.appendChild(productDiv);
          });

          returnCardsContainer.appendChild(orderDiv);
        });

        applyTranslations(returnCardsContainer);

      } catch(err) {
        console.error(err);
        const p = document.createElement('p');
        p.setAttribute('data-i18n', 'script_return_request_error');
        returnCardsContainer.innerHTML = '';
        returnCardsContainer.appendChild(p);
        applyTranslations(returnCardsContainer);
      }
    }

    loadReturns();
    document.addEventListener('languageChanged', loadReturns);
  }

  // ---------------------------
  // Order details en retour verwerken
  // ---------------------------
  const loadOrderBtn = document.getElementById('loadOrderBtn');
  const orderIdInput = document.getElementById('orderIdInput');
  const returnBtn = document.getElementById('ReturnBtn');

  function getStatusText(statusKey) {
    const lang = document.cookie.match(/(?:^|;\s*)siteLanguage=([^;]+)/)?.[1] || "be-nl";
    const dict = translations[lang] || translations["be-nl"];
    return dict[statusKey] || statusKey;
  }

  const orderStatusMap = {
    "pending": "script_processing_retours_order_status_pending",
    "paid": "script_processing_retours_order_status_paid",
    "shipped": "script_processing_retours_order_status_shipped",
    "received": "script_processing_retours_order_status_received",
    "cancelled": "script_processing_retours_order_status_cancelled"
  };

  function getOrderStatusText(dbStatus) {
    const key = orderStatusMap[dbStatus.toLowerCase()] || dbStatus;
    return { key, text: getStatusText(key) };
  }

  function populateOrderTable(order) {
    const tbody = document.getElementById('orderItemsBody');
    tbody.innerHTML = '';
    if (!order.items) return;

    order.items.forEach(item => {
      if (item.type === 'voucher') return;
      const qty = Number(item.quantity) || 0;
      const price = Number(item.price) || 0;
      let statusKey = 'none';
      let approvedChecked = '', rejectedChecked = '';
      const itemReturn = order.return_items?.find(r => r.order_item_id === item.order_item_id);

      if (itemReturn?.return_status) {
        statusKey = itemReturn.return_status === 'processed' ? 'approved' : itemReturn.return_status;
        if (statusKey === 'approved') approvedChecked = 'checked';
        if (statusKey === 'rejected') rejectedChecked = 'checked';
      }

      const statusText = getStatusText(`script_processing_retours_status_${statusKey}`);
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${item.product_name}</td>
        <td style="text-align:center;">${qty}</td>
        <td style="text-align:center;">€${price.toFixed(2)}</td>
        <td style="text-align:center;">€${(qty*price).toFixed(2)}</td>
        <td style="text-align:center;" data-i18n="script_processing_retours_status_${statusKey}" data-status-original="${statusKey}">${statusText}</td>
        <td style="text-align:center;"><input type="checkbox" class="approveItem" data-item-id="${item.order_item_id}" ${approvedChecked}></td>
        <td style="text-align:center;"><input type="checkbox" class="rejectItem" data-item-id="${item.order_item_id}" ${rejectedChecked}></td>
      `;
      tbody.appendChild(tr);

      const approveCb = tr.querySelector('.approveItem');
      const rejectCb = tr.querySelector('.rejectItem');
      approveCb.addEventListener('change', () => { if (approveCb.checked) rejectCb.checked = false; });
      rejectCb.addEventListener('change', () => { if (rejectCb.checked) approveCb.checked = false; });
    });
  }

  function loadOrder() {
    const orderId = orderIdInput.value.trim();
    if (!orderId) { alert(getStatusText('script_processing_retours_alert_invalid_id')); return; }

    fetchWithLang(`get_order_retour.php?order_id=${orderId}`)
      .then(data => {
        if (!data.success) { alert(data.message || getStatusText('script_processing_retours_alert_fetch_error')); return; }

        const order = data.order;
        document.getElementById('orderDetails').style.display = 'block';
        document.getElementById('orderNumber').textContent = order.order_id;
        document.getElementById('orderDate').textContent = order.order_date;
        document.getElementById('orderTotal').textContent = Number(order.total_price).toFixed(2);

        const orderStatusObj = getOrderStatusText(order.order_status);
        const orderStatusEl = document.getElementById('orderStatus');
        orderStatusEl.textContent = orderStatusObj.text;
        orderStatusEl.setAttribute('data-i18n', orderStatusObj.key);

        document.getElementById('customerName').textContent = order.customer_name;
        document.getElementById('customerEmail').textContent = order.customer_email;
        document.getElementById('customerCompany').textContent = order.company_name || '-';
        document.getElementById('customerVAT').textContent = order.vat_number || '-';

        const addressEl = document.getElementById('customerAddress');
        let addressText = '';
        if (order.address_shipping) {
          addressText += `<span data-i18n="script_processing_retours_shipping_label">${getStatusText('script_processing_retours_shipping_label')}</span>: ${order.address_shipping.street} ${order.address_shipping.house_number}, ${order.address_shipping.postal_code} ${order.address_shipping.city}, ${order.address_shipping.country}`;
        }
        if (order.address_billing) {
          if (addressText) addressText += '\n';
          addressText += `<span data-i18n="script_processing_retours_billing_label">${getStatusText('script_processing_retours_billing_label')}</span>: ${order.address_billing.street} ${order.address_billing.house_number}, ${order.address_billing.postal_code} ${order.address_billing.city}, ${order.address_billing.country}`;
        }
        addressEl.innerHTML = addressText || '-';
        addressEl.style.whiteSpace = 'pre-line';

        populateOrderTable(order);
      })
      .catch(err => { console.error(err); alert(getStatusText('script_processing_retours_alert_load_error')); });
  }

  loadOrderBtn?.addEventListener('click', loadOrder);

  returnBtn?.addEventListener('click', () => {
    const orderId = orderIdInput.value.trim();
    if (!orderId) { alert(getStatusText('script_processing_retours_alert_no_id')); return; }

    const approvedItems = [], rejectedItems = [];
    document.querySelectorAll('.approveItem').forEach(cb => { if (cb.checked) approvedItems.push(cb.dataset.itemId); });
    document.querySelectorAll('.rejectItem').forEach(cb => { if (cb.checked) rejectedItems.push(cb.dataset.itemId); });

    fetch('processing_retours.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ order_id: orderId, approved_items: approvedItems, rejected_items: rejectedItems })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        alert(getStatusText('script_processing_retours_alert_success'));
        loadOrder();
      } else {
        alert(getStatusText('script_processing_retours_alert_processing_error') + ': ' + (data.message || 'Onbekende fout'));
      }
    })
    .catch(err => { console.error(err); alert(getStatusText('script_processing_retours_alert_general_error')); });
  });

  // Live vertalingen bij taalwissel
  document.addEventListener('translationsApplied', () => {
    document.querySelectorAll('#orderItemsBody td[data-i18n]').forEach(cell => {
      const key = cell.getAttribute('data-i18n');
      if (key) cell.textContent = getStatusText(key);
    });

    const orderStatusEl = document.getElementById('orderStatus');
    const keyStatus = orderStatusEl.getAttribute('data-i18n');
    if (keyStatus) orderStatusEl.textContent = getStatusText(keyStatus);

    document.getElementById('customerAddress').querySelectorAll('[data-i18n]').forEach(span => {
      const key = span.getAttribute('data-i18n');
      if (key) span.textContent = getStatusText(key);
    });
  });

  // Enter-toets functionaliteit
  if (window.location.pathname.endsWith('processing_retours.html')) {
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        const active = document.activeElement;
        if (active === orderIdInput) {
          event.preventDefault();
          loadOrderBtn?.click();
        } else if (active && active.form && active.form.contains(returnBtn)) {
          event.preventDefault();
          returnBtn?.click();
        }
      }
    });
  }

  // --- update bij taalwijziging ---
  document.addEventListener("languageChanged", loadOrder);
  document.addEventListener("languageChanged", loadNewProducts);
  document.addEventListener("languageChanged", loadPopularProducts);
  document.addEventListener("languageChanged", loadProductGrid);
  document.addEventListener("languageChanged", loadSearchGrid);
  document.addEventListener("languageChanged", () => {
    if (document.getElementById("order-details")) {
      loadLastOrderDetails();
    }
  });
});

document.addEventListener('DOMContentLoaded', async () => {
  const voucherList = document.getElementById('voucher-list');
  if (!voucherList) return;

  // Wacht tot vertalingen beschikbaar zijn
  async function waitForTranslations() {
    return new Promise(resolve => {
      if (typeof translations !== 'undefined') resolve();
      else {
        const interval = setInterval(() => {
          if (typeof translations !== 'undefined') {
            clearInterval(interval);
            resolve();
          }
        }, 50);
      }
    });
  }
  await waitForTranslations();

  // Functie om boodschap te tonen
  function showMessage(container, key) {
    container.innerHTML = '';
    const p = document.createElement('p');
    p.setAttribute('data-i18n', key);
    container.appendChild(p);
    applyTranslations(container);
  }

  try {
    const res = await fetch('get_vouchers.php', { credentials: 'same-origin' });
    if (!res.ok) throw new Error(`HTTP fout! status: ${res.status}`);
    const data = await res.json();

    // Filter geldige vouchers
    const now = new Date();
    const validVouchers = (Array.isArray(data) ? data : []).filter(v => {
      const remaining = Number(v.remaining_value ?? 0);
      const expiresAt = v.expires_at ? new Date(v.expires_at) : null;
      return remaining > 0 && (!expiresAt || expiresAt >= now);
    });

    // Alleen de laatste voucher tonen op mijn_stylisso.html
    let vouchersToRender = validVouchers;
    if (window.location.pathname.includes('mijn_stylisso.html')) {
      vouchersToRender = validVouchers.length ? [validVouchers[0]] : [];
    }

    // Als geen vouchers te renderen
    if (!vouchersToRender.length) {
      const key = window.location.pathname.includes('mijn_stylisso.html')
        ? 'mijn_stylisso_text_no_vouchers'
        : 'script_no_vouchers';
      showMessage(voucherList, key);
      return;
    }

    // Render vouchers
    const ul = document.createElement('ul');
    ul.classList.add('voucher-list');

    vouchersToRender.forEach(voucher => {
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
        <span class="left min-width"><strong data-i18n="script_voucher_code">Code:</strong> ${voucher.code}</span>
        <span class="left max-width">${voucher.code}</span>
        <span class="separator">|</span>
        <span class="center min-width"><strong data-i18n="script_voucher_remaining_value">Resterende waarde:</strong> €${remainingValue}</span>
        <span class="center max-width">€${remainingValue}</span>
        <span class="separator">|</span>
        <span class="right min-width"><strong data-i18n="script_voucher_expiry">Vervalt op:</strong> ${expireDate}</span>
        <span class="right max-width">${expireDate}</span>
      `;
      ul.appendChild(li);
    });

    voucherList.innerHTML = '';
    voucherList.appendChild(ul);
    applyTranslations(voucherList);

  } catch (err) {
    console.error('Fout bij ophalen vouchers:', err);
    showMessage(voucherList, 'script_voucher_load_error');
  }
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
          const dict = translations[lang] || translations["be-nl"];
          alert(dict["script_voucher_min_amount"]);
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
        messageBox.textContent = dict["script_voucher_redeem_error"];
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
          messageBox.setAttribute('data-i18n', 'script_select_voucher_first');
          applyTranslations(messageBox);
          messageBox.style.color = "red";
          messageBox.style.margin = "1rem 0";
          return;
        }

        // Succesmelding
        messageBox.innerHTML = ''; // eerst leegmaken

        const spanText = document.createElement('span');
        spanText.setAttribute('data-i18n', 'script_voucher_applied');

        const spanCode = document.createElement('span');
        spanCode.className = 'voucher-code';
        spanCode.textContent = code;

        // Voeg beide toe in messageBox
        messageBox.appendChild(spanText);
        messageBox.appendChild(document.createTextNode(': ')); // dubbele punt tussen tekst en code
        messageBox.appendChild(spanCode);

        // Stijl
        messageBox.style.color = 'green';
        messageBox.style.margin = '1rem 0';

        // Vertalingen toepassen
        applyTranslations(messageBox);

        // TODO: hier logica toevoegen om totaal aan te passen
      });
    }
  }

  function refreshSavedVouchers(selectEl) {
    if (!selectEl) return;

    // Haal de taal uit cookie en fallback
    const cookieMatch = document.cookie.match(/(?:^|;\s*)siteLanguage=([^;]+)/);
    const lang = cookieMatch ? decodeURIComponent(cookieMatch[1]) : "be-nl";
    const dict = translations[lang] || translations["be-nl"];

    fetch('get_vouchers.php', { credentials: 'same-origin' })
        .then(r => r.json())
        .then(list => {
            if (!Array.isArray(list)) return;

            const now = new Date();
            const opts = [`<option value="">${dict["script_select_voucher"]}</option>`];

            list.forEach(v => {
                const expires = v.expires_at ? new Date(v.expires_at) : null;
                const remaining = Number(v.remaining_value ?? 0);

                // Alleen geldige vouchers tonen
                if (remaining > 0 && (!expires || expires >= now)) {
                    let expireText = '';
                    if (expires) {
                        const day = String(expires.getDate()).padStart(2, '0');
                        const month = String(expires.getMonth() + 1).padStart(2, '0');
                        const year = expires.getFullYear();
                        expireText = ` (${dict["script_until"]} ${day}-${month}-${year})`;
                    }

                    opts.push(
                        `<option value="${v.code}">${v.code} — €${remaining.toFixed(2)}${expireText}</option>`
                    );
                }
            });

            // Dropdown vullen
            selectEl.innerHTML = opts.join('');
        })
        .catch(err => console.error('get_vouchers dropdown error:', err));
  }

  function refreshVoucherList(isMijnStylisso = false) {
    const container = document.getElementById('voucher-list');
    if (!container) return;

    fetch('get_vouchers.php', { credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => {
        const cookieMatch = document.cookie.match(/(?:^|;\s*)siteLanguage=([^;]+)/);
        const lang = cookieMatch ? decodeURIComponent(cookieMatch[1]) : "be-nl";
        const dict = translations[lang] || translations["be-nl"];

        if (data.error || !Array.isArray(data)) {
          container.innerHTML = `<p>${data.error || dict["script_no_vouchers"]}</p>`;
          applyTranslations(container);
          return;
        }

        const now = new Date();
        const valid = data.filter(v => {
          const expires = v.expires_at ? new Date(v.expires_at) : null;
          const remaining = Number(v.remaining_value ?? 0);
          return remaining > 0 && (!expires || expires >= now);
        });

        if (!valid.length) {
          const key = isMijnStylisso ? 'mijn_stylisso_text_no_vouchers' : 'script_no_vouchers';
          container.innerHTML = `<p data-i18n="${key}"></p>`;
          applyTranslations(container);
          return;
        }

        let vouchersToRender = valid;
        if (isMijnStylisso) vouchersToRender = [valid[0]]; // alleen laatste

        const ul = document.createElement('ul');
        ul.className = 'voucher-list';
        vouchersToRender.forEach(v => {
          const expires = v.expires_at ? new Date(v.expires_at) : null;
          const expireDate = expires
            ? `${String(expires.getDate()).padStart(2,'0')}-${String(expires.getMonth()+1).padStart(2,'0')}-${expires.getFullYear()}`
            : 'Onbepaald';
          const li = document.createElement('li');
          li.innerHTML = `
            <span class="left min-width"><strong data-i18n="script_voucher_code">Code:</strong> ${v.code}</span>
            <span class="left max-width">${v.code}</span>
            <span class="separator" aria-hidden="true">|</span>
            <span class="center min-width"><strong data-i18n="script_voucher_remaining_value">Resterende waarde:</strong> €${Number(v.remaining_value ?? 0).toFixed(2)}</span>
            <span class="center max-width">€${Number(v.remaining_value ?? 0).toFixed(2)}</span>
            <span class="separator" aria-hidden="true">|</span>
            <span class="right min-width"><strong data-i18n="script_voucher_expiry">Vervalt op:</strong> ${expireDate}</span>
            <span class="right max-width">${expireDate}</span>
          `;
          ul.appendChild(li);
        });

        container.innerHTML = '';
        container.appendChild(ul);
        applyTranslations(container); // ✅ nu altijd
      })
      .catch(err => {
        console.error('get_vouchers list error:', err);
        const p = document.createElement('p');
        p.setAttribute('data-i18n', 'script_voucher_load_error');
        container.innerHTML = '';
        container.appendChild(p);
        applyTranslations(container);
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
      messageBox.textContent = '';
      messageBox.setAttribute('data-i18n', 'script_invalid_reset_link');
      messageBox.style.color = "red";
      applyTranslations(messageBox);
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
    // --- Haal wishlist met vertaling op ---
    const data = await fetchWithLang("wishlist.php");

    const container = document.getElementById("wishlist-container");
    container.innerHTML = "";

    // --- leeg lijstje ---
    if (data.error || data.length === 0) {
      const emptyBox = document.createElement("div");
      emptyBox.className = "empty-wishlist-box";
      emptyBox.setAttribute("data-i18n", "script_wishlist_empty");

      container.appendChild(emptyBox);
      setTimeout(() => applyTranslations(emptyBox), 0);
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
          <p><span data-i18n="script_cart_price">Prijs:</span> €${parseFloat(item.price).toFixed(2)}</p>
          ${item.sizes && item.sizes.length > 0
            ? `<label for="size-${item.id}" data-i18n="script_wishlist_size">Maat:</label>
              <select id="size-${item.id}" class="wishlist-product-size">
                ${item.sizes.map(size => `<option value="${size}">${size}</option>`).join('')}
              </select>`
            : ""}
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

      // Voeg dit toe na het maken van de select-elementen
      const sizeSelect = card.querySelector(".wishlist-product-size");
      if (sizeSelect) {
        sizeSelect.addEventListener("click", (e) => e.stopPropagation());
        sizeSelect.addEventListener("mousedown", (e) => e.stopPropagation()); // voorkomt dat klik wordt doorgestuurd
        sizeSelect.addEventListener("change", (e) => {
          e.stopPropagation();
        });
      }

      // --- klik op de card → naar productpagina ---
      card.addEventListener("click", () => {
        window.location.href = `productpagina.html?id=${item.id}`;
      });

      // --- add-to-cart knop ---
      card.querySelector(".add-to-cart").addEventListener("click", async (e) => {
        e.stopPropagation();
        const productId = e.currentTarget.dataset.id;
        if (!productId) return;

        // --- maat ophalen indien aanwezig ---
        const sizeSelect = card.querySelector(".wishlist-product-size");
        const selectedSize = sizeSelect ? sizeSelect.value : '';

        await fetch("./wishlist_cart_add.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: `product_id=${productId}&quantity=1&size=${encodeURIComponent(selectedSize)}&csrf_token=${encodeURIComponent(token)}`
        });

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

        loadWishlist();
      });

      grid.appendChild(card);
    });

    container.appendChild(grid);

    applyTranslations(container);

  } catch (err) {
    const container = document.getElementById("wishlist-container");
    container.innerHTML = '';
    const p = document.createElement('p');
    p.setAttribute('data-i18n', 'script_wishlist_load_error');
    container.appendChild(p);
    applyTranslations(container);
    console.error(err);
  }
}

document.addEventListener("DOMContentLoaded", loadWishlist);

// --- update bij taalwijziging ---
document.addEventListener("languageChanged", loadWishlist);

document.addEventListener('DOMContentLoaded', async () => {
  const titleEl = document.getElementById('product-title');
  const imageEl = document.getElementById('product-image');
  const descEl = document.getElementById('product-description');
  const specsEl = document.getElementById('product-specificaties'); 
  const priceEl = document.getElementById('product-price');
  const quantityEl = document.getElementById('product-quantity');
  const addBtn = document.getElementById('add-to-cart');
  const csrfTokenEl = document.getElementById('csrf_token');
  const errorEl = document.getElementById('product-error');
  const wishlistBtn = document.getElementById('wishlist-btn');
  const sizeWrapper = document.getElementById('maat-wrapper');
  const sizeDropdown = document.getElementById('product-size');
  const params = new URLSearchParams(window.location.search);
  const id = params.get('id');

  if (!id) {
    errorEl.textContent = '';
    errorEl.setAttribute('data-i18n', 'script_no_product_selected');
    applyTranslations(errorEl);
    return;
  }

  // --- Huidige taal initieel uit cookie ---
  let currentLang = (document.cookie.match(/(^|;)\s*siteLanguage\s*=\s*([^;]+)/)?.pop()) || 'be-nl';

  // --- Helper: fetch met taal ---
  async function fetchWithLang(url, options = {}) {
    const separator = url.includes('?') ? '&' : '?';
    const resp = await fetch(`${url}${separator}lang=${encodeURIComponent(currentLang)}`, options);
    return resp.json();
  }

  // --- Haal CSRF-token op ---
  try {
    const csrfResp = await fetch('csrf.php');
    const csrfData = await csrfResp.json();
    csrfTokenEl.value = csrfData.csrf_token;
  } catch (err) {
    errorEl.textContent = '';
    errorEl.setAttribute('data-i18n', 'script_csrf_load_error');
    applyTranslations(errorEl);
    console.error(err);
    return;
  }

  // --- Check login status ---
  async function isLoggedIn() {
    try {
      const resp = await fetch('current_user.php');
      const data = await resp.json();
      return data.loggedIn;
    } catch (err) {
      console.error('Login check error:', err);
      return false;
    }
  }

// --- Grote afbeelding + thumbnails ---
let thumbsWrapper = document.getElementById('product-thumbnails');
if (!thumbsWrapper) {
  thumbsWrapper = document.createElement('div');
  thumbsWrapper.id = 'product-thumbnails';
  document.querySelector('.product-left').appendChild(thumbsWrapper);
}

function renderProductImages(images, fallbackImage) {
  if (!Array.isArray(images) || images.length === 0) images = [fallbackImage];
  if (!images[0]) images[0] = 'images/placeholder.png';

  // Grote afbeelding = eerste
  imageEl.src = images[0];
  imageEl.alt = titleEl.textContent || '';

  // Als er maar één afbeelding is, thumbnails verbergen
  if (images.length === 1) {
    thumbsWrapper.style.display = 'none';
    thumbsWrapper.innerHTML = '';
    return;
  }

  thumbsWrapper.style.display = 'flex';
  thumbsWrapper.innerHTML = '';

  // Hoeveelheid kleine afbeeldingen
  images.slice(0, 5).forEach((imgSrc, index) => {
    const thumb = document.createElement('img');
    thumb.src = imgSrc;
    thumb.alt = titleEl.textContent || '';
    thumb.dataset.index = index; // eventueel voor CSS active state

    // Eerste thumbnail direct active
    if (index === 0) thumb.classList.add('active');

    thumb.addEventListener('click', () => {
      imageEl.src = imgSrc;
      thumbsWrapper.querySelectorAll('img').forEach(t => t.classList.remove('active'));
      thumb.classList.add('active');
    });

    thumbsWrapper.appendChild(thumb);
  });
}

  // --- Functie om product + gerelateerde data te laden ---
  async function loadProductData() {
    try {
      const product = await fetchWithLang(`get_product.php?id=${encodeURIComponent(id)}`);
      if (product.error) {
        errorEl.textContent = product.error;
        return;
      }

      // --- Vul HTML ---
      titleEl.textContent = product.name;
      descEl.innerHTML = product.description?.replace(/\n/g, "<br>") || '';
      priceEl.textContent = `€${parseFloat(product.price || 0).toFixed(2)}`;

      // --- Afbeeldingen ---
      renderProductImages(product.images, product.image);

      // --- Specificaties ---
      if (product.specifications) {
        const specsArray = product.specifications.split(';').map(s => s.trim()).filter(s => s);
        const ul = document.createElement('ul');
        specsArray.forEach(spec => {
          const li = document.createElement('li');
          li.textContent = spec;
          ul.appendChild(li);
        });
        specsEl.innerHTML = '';
        specsEl.appendChild(ul);
      } else {
        specsEl.textContent = '';
      }

      // --- Maat dropdown ---
      if (product.maat && Array.isArray(product.maat)) {
        sizeDropdown.innerHTML = "";
        product.maat.forEach(opt => {
          const option = document.createElement("option");
          option.value = opt.trim();
          option.textContent = opt.trim();
          sizeDropdown.appendChild(option);
        });
        sizeDropdown.required = true;
        sizeWrapper.style.display = "block";
      } else {
        sizeDropdown.required = false;
        sizeWrapper.style.display = "none";
      }

      // --- Wishlist knop ---
      if (wishlistBtn) {
        const iconLight = wishlistBtn.querySelector(".wishlist-icon-light");
        const iconDark = wishlistBtn.querySelector(".wishlist-icon-dark");
        let inWishlist = product.in_wishlist;

        iconLight.src = inWishlist ? "wishlist/added.png" : "wishlist/wishlist.png";
        iconDark.src = inWishlist ? "wishlist/added (dark mode).png" : "wishlist/wishlist (dark mode).png";

        wishlistBtn.onclick = () => {
          const action = inWishlist ? "remove" : "add";
          const url = action === "add" ? "wishlist_add.php" : "wishlist_remove.php";
          const formData = new URLSearchParams();
          formData.append("product_id", product.id);

          fetch(url, { method: "POST", body: formData })
            .then(res => res.json())
            .then(response => {
              if (response.error) return console.error("Wishlist error:", response.error);
              inWishlist = !inWishlist;
              iconLight.src = inWishlist ? "wishlist/added.png" : "wishlist/wishlist.png";
              iconDark.src = inWishlist ? "wishlist/added (dark mode).png" : "wishlist/wishlist (dark mode).png";
            })
            .catch(err => console.error("Wishlist fetch error:", err));
        };
      }

      // --- Add to cart knop ---
      addBtn.onclick = async () => {
        const quantity = parseInt(quantityEl.value);
        if (quantity < 1) return;

        const formData = new FormData();
        formData.append('product_id', product.id);
        formData.append('quantity', quantity);
        formData.append('csrf_token', csrfTokenEl.value);

        if (product.maat && sizeDropdown && sizeDropdown.value) {
          formData.append('maat', sizeDropdown.value);
        }

        try {
          const addResp = await fetch('add_to_cart.php', { method: 'POST', body: formData });
          const result = await addResp.json();
          if (result.success) {
            window.location.href = 'cart.html';
          } else {
            const temp = document.createElement('span');
            temp.setAttribute('data-i18n', 'script_add_to_cart_error');
            applyTranslations(temp);
            alert(result.error || temp.textContent);
          }
        } catch (err) {
          const temp = document.createElement('span');
          temp.setAttribute('data-i18n', 'script_add_to_cart_error');
          applyTranslations(temp);
          alert(temp.textContent);
          console.error(err);
        }
      };

      // --- Render producten ---
      function renderProducts(products, containerId, excludeId = null) {
        const container = document.getElementById(containerId);
        container.innerHTML = "";
        const visibleProducts = products ? products.filter(p => p.id !== excludeId) : [];

        if (!visibleProducts || visibleProducts.length === 0) {
          const emptyMsg = document.createElement("p");
          emptyMsg.setAttribute("data-i18n", "productpagina_no_products");
          container.appendChild(emptyMsg);
          applyTranslations(emptyMsg);
          return;
        }

        visibleProducts.forEach(p => {
          const card = document.createElement("div");
          card.className = "product-card2";

          let inWishlist = p.in_wishlist;
          card.innerHTML = `
            <div class="wishlist-btn" data-id="${p.id}">
              <img src="${inWishlist ? 'wishlist/added.png' : 'wishlist/wishlist.png'}" 
                  alt="Wishlist knop" 
                  class="wishlist-icon-light">
              <img src="${inWishlist ? 'wishlist/added (dark mode).png' : 'wishlist/wishlist (dark mode).png'}" 
                  alt="Wishlist knop dark" 
                  class="wishlist-icon-dark">
            </div>
            <a href="productpagina.html?id=${p.id}">
              <img src="${p.image}" alt="${p.name}" class="product-thumb">
              <h3>${p.name}</h3>
              <p>€${parseFloat(p.price).toFixed(2)}</p>
            </a>
          `;

          const wishlistBtn = card.querySelector(".wishlist-btn");
          const iconLight = wishlistBtn.querySelector(".wishlist-icon-light");
          const iconDark = wishlistBtn.querySelector(".wishlist-icon-dark");

          wishlistBtn.addEventListener("click", (e) => {
            e.stopPropagation();
            const action = inWishlist ? "remove" : "add";
            const url = action === "add" ? "wishlist_add.php" : "wishlist_remove.php";
            const formData = new URLSearchParams();
            formData.append("product_id", p.id);

            fetch(url, { method: "POST", body: formData })
              .then(res => res.json())
              .then(response => {
                if (response.error) return console.error("Wishlist error:", response.error);
                inWishlist = !inWishlist;
                iconLight.src = inWishlist ? "wishlist/added.png" : "wishlist/wishlist.png";
                iconDark.src = inWishlist ? "wishlist/added (dark mode).png" : "wishlist/wishlist (dark mode).png";
              })
              .catch(err => console.error("Wishlist fetch error:", err));
          });

          card.addEventListener("click", (e) => {
            if (e.target.closest(".wishlist-btn")) return;
            window.location.href = `productpagina.html?id=${p.id}`;
          });

          container.appendChild(card);
        });
      }

      // --- Load related products ---
      async function loadOtherProducts(category, excludeId) {
        try {
          const products = await fetchWithLang(`get_related_products.php?category=${encodeURIComponent(category)}&exclude=${excludeId}`);
          renderProducts(products, "other-products-container");
        } catch (err) {
          console.error("Error loading related products:", err);
        }
      }

      // --- Last seen ---
      async function updateLastSeen(product) {
        const loggedIn = await isLoggedIn();
        if (loggedIn) {
          try {
            await fetch('last_seen.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: `product_id=${product.id}`
            });
          } catch (err) {
            console.error('Error saving last seen in DB:', err);
          }
        }

        let lastSeen = JSON.parse(sessionStorage.getItem("lastSeen") || "[]");
        lastSeen = lastSeen.filter(p => p.id !== product.id);
        lastSeen.unshift({ id: product.id, name: product.name, price: product.price, image: product.image });
        if (lastSeen.length > 6) lastSeen = lastSeen.slice(0, 6);
        sessionStorage.setItem("lastSeen", JSON.stringify(lastSeen));
      }

      async function loadLastSeen(currentProductId) {
        const loggedIn = await isLoggedIn();
        let products = [];
        if (loggedIn) {
          try {
            products = await fetchWithLang('get_last_seen.php');
          } catch (err) {
            console.error('Error loading last seen from DB:', err);
          }
        } else {
          products = JSON.parse(sessionStorage.getItem("lastSeen") || "[]");
        }
        renderProducts(products, "last-seen-products-container", currentProductId);
      }

      // --- Aanroepen na productload ---
      loadOtherProducts(product.category_id, product.id);
      await updateLastSeen(product);
      await loadLastSeen(product.id);

    } catch (err) {
      errorEl.setAttribute('data-i18n', 'script_product_load_error');
      applyTranslations(errorEl);
      console.error(err);
    }
  }

  // --- Eerste keer laden ---
  await loadProductData();

  // --- Event: taal gewijzigd ---
  document.addEventListener("languageChanged", async (e) => {
    currentLang = e.detail.lang;
    await loadProductData();
    await loadLastSeen();
  });
});

// --- Redirect als gebruiker niet ingelogd ---
// --- Redirect ingelogde gebruiker weg van "guest-only" pagina's ---
document.addEventListener('DOMContentLoaded', () => {
    const privatePages = [
        'gegevens.html', 'retourneren.html', 'cadeaubonnen.html','afrekenen.html',
        'bestellingen.html','mijn_stylisso.html','bedankt.html','afrekenen.php',
        'create_invoice.php','get_invoice.php','get_last_order_bedankt.php',
        'get_last_order.php','get_orders.php','get_returns.php','get_user_data.php',
        'get_vouchers.php','logout.php','redeem_voucher.php','retourneren.php',
        'submit_returns.php','update_profile.php','delete_account.php','processing_retours.html','staff_pages.html'
    ];

    const guestPages = [
        'login_registreren.html','reset_password.html','reset_success.html',
        'wachtwoord vergeten.html','login.php','register.php','reset_password.php',
        'wachtwoord vergeten.php','processing_retours.html','staff_pages.html'
    ];

    const loginPage = 'login_registreren.html';
    const homePage = 'index.html';
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';

    fetch('check_login.php')
        .then(res => res.json())
        .then(data => {
            const loggedIn = data.logged_in;
            const toegang = data.toegang;

            // Staff mag overal heen
            if (loggedIn && toegang === 'staff') {
                // --- Toon staff link ---
                  const staffLink = document.querySelector('.staff-link');
                  if (staffLink) {
                      staffLink.style.display = 'block';
                  }
                  const staffLink2 = document.querySelector('.staff-link2');
                  if (staffLink2) {
                      staffLink2.style.display = 'block';
                  }    
                return; // geen redirect
            }

            // Niet ingelogd en op private page → redirect naar login
            if (!loggedIn && privatePages.includes(currentPage)) {
                window.location.href = loginPage;
                return;
            }

            // Ingelogd, geen staff, en op guest-only pagina → redirect naar home
            if (loggedIn && toegang !== 'staff' && guestPages.includes(currentPage)) {
                window.location.href = homePage;
                return;
            }
        })
        .catch(err => {
            console.error('Fout bij login check:', err);
            // Optioneel: redirect naar login bij fout
            window.location.href = loginPage;
        });
});

document.addEventListener("DOMContentLoaded", () => {
  const modal = document.getElementById("confirmModal");
  const deleteBtn = document.getElementById("deleteBtn");
  const cancelBtn = document.getElementById("cancelDelete");

  if (deleteBtn && modal && cancelBtn) {
    deleteBtn.addEventListener("click", () => {
      modal.style.display = "flex";
    });

    cancelBtn.addEventListener("click", () => {
      modal.style.display = "none";
    });

    // Optioneel: klik buiten modal sluit hem ook
    window.addEventListener("click", (e) => {
      if (e.target === modal) {
        modal.style.display = "none";
      }
    });
  }
});

// ==============================
// --- FILTERS / PRODUCT-GRID2 ---
// ==============================
function loadProductGrid() {
  const filtersContainer = document.getElementById("filters-container");
  const productGrid = document.getElementById("product-grid2");
  const resetBtn = document.getElementById("reset-filters");
  const sortSelect = document.getElementById("sort-products");
  const categoryTitle = document.getElementById("category-title");

  if (productGrid && filtersContainer && categoryTitle) {
    const urlParams = new URLSearchParams(window.location.search);
    const categoryId = urlParams.get('cat') || 0;
    const subcategoryId = urlParams.get('sub') || 0;

    let products = [];
    let activeFilters = {};

    // --- Titel ophalen via categorie.php ---
    fetchWithLang(`categorie.php?cat=${categoryId}&sub=${subcategoryId}`)
      .then(data => {
        categoryTitle.textContent = data.selected.subcategory || data.selected.category || "Categorie";
      });

    // --- Filters ophalen ---
    fetch(`filters.php?cat=${categoryId}&sub=${subcategoryId}`)
      .then(res => res.json())
      .then(filters => {
        for (let key in filters) {
          const group = document.createElement('div');
          group.className = 'filter-group';
          group.innerHTML = `<h3>${key.charAt(0).toUpperCase() + key.slice(1)}</h3>`;
          filters[key].forEach(value => {
            const label = document.createElement('label');
            label.innerHTML = `<input type="checkbox" name="${key}" value="${value}"> ${value}`;
            group.appendChild(label);
          });
          filtersContainer.appendChild(group);
        }

        // Event listeners voor filters
        document.querySelectorAll('.filter-group input[type=checkbox]').forEach(cb => {
          cb.addEventListener('change', () => {
            activeFilters = {};
            document.querySelectorAll('.filter-group input[type=checkbox]:checked').forEach(chk => {
              if (!activeFilters[chk.name]) activeFilters[chk.name] = [];
              activeFilters[chk.name].push(chk.value);
            });
            renderProducts();
          });
        });
      });

    // --- Producten ophalen ---
    fetchWithLang(`fetch_products.php?cat=${categoryId}&sub=${subcategoryId}`)
      .then(data => {
        products = data;
        renderProducts();
      });

    function renderProducts() {
      let filtered = products.filter(p => {
        for (let key in activeFilters) {
          if (!activeFilters[key].includes(p[key])) return false;
        }
        return true;
      });

      // Sorteren
      if (sortSelect.value === 'prijs-oplopend') filtered.sort((a, b) => a.price - b.price);
      else if (sortSelect.value === 'prijs-aflopend') filtered.sort((a, b) => b.price - a.price);
      else if (sortSelect.value === 'nieuw') filtered.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

      // Productgrid vullen
      productGrid.innerHTML = '';
      filtered.forEach(p => {
        const card = document.createElement('div');
        card.className = 'product-card2';

        const inWishlist = p.in_wishlist;
        card.innerHTML = `
          <div class="wishlist-btn" data-id="${p.id}">
            <img src="${inWishlist ? 'wishlist/added.png' : 'wishlist/wishlist.png'}" class="wishlist-icon-light">
            <img src="${inWishlist ? 'wishlist/added (dark mode).png' : 'wishlist/wishlist (dark mode).png'}" class="wishlist-icon-dark">
          </div>
          <img src="${p.image}" alt="${p.name}">
          <h3>${p.name}</h3>
          <p>€${parseFloat(p.price).toFixed(2)}</p>
        `;

        // Klikbare kaart naar productpagina
        card.addEventListener("click", e => {
          if (e.target.closest(".wishlist-btn")) return;
          window.location.href = `productpagina.html?id=${p.id}`;
        });

        // Wishlist knop
        const wishlistBtn = card.querySelector(".wishlist-btn");
        wishlistBtn.addEventListener("click", e => {
          e.stopPropagation();
          const iconLight = wishlistBtn.querySelector(".wishlist-icon-light");
          const iconDark = wishlistBtn.querySelector(".wishlist-icon-dark");
          const action = iconLight.src.includes("added.png") ? "remove" : "add";
          const url = action === "add" ? "wishlist_add.php" : "wishlist_remove.php";

          const formData = new URLSearchParams();
          formData.append("product_id", p.id);

          fetch(url, { method: "POST", body: formData })
            .then(res => res.json())
            .then(response => {
              if (response.error) return console.error("Wishlist error:", response.error);
              if (action === "add") {
                iconLight.src = "wishlist/added.png";
                iconDark.src = "wishlist/added (dark mode).png";
              } else {
                iconLight.src = "wishlist/wishlist.png";
                iconDark.src = "wishlist/wishlist (dark mode).png";
              }
            })
            .catch(err => console.error("Wishlist fetch error:", err));
        });

        productGrid.appendChild(card);
      });
    }

    // --- Reset filters ---
    if (resetBtn) {
      resetBtn.addEventListener('click', () => {
        document.querySelectorAll('.filter-group input[type=checkbox]').forEach(cb => cb.checked = false);
        activeFilters = {};
        renderProducts();
      });
    }

    // --- Sorteren bij selectie ---
    if (sortSelect) sortSelect.addEventListener('change', renderProducts);
  }
}

// ==============================
// --- SEARCH GRID3 (ZOEKRESULTATEN) ---
// ==============================
function loadSearchGrid() {
  const grid3 = document.getElementById("product-grid3");
  const sortSelectSearch = document.getElementById("search-sort-products");

  if (grid3) {
    const params = new URLSearchParams(window.location.search);
    const q = params.get("q") || "";
    if (!q) {
      grid3.innerHTML = `
        <p data-i18n="script_search_no_query">Geen zoekterm opgegeven.</p>
      `;
      applyTranslations();
      return;
    }

    // Gebruik universele fetch die taal meestuurt
    fetchWithLang(`get_search_products.php?q=${encodeURIComponent(q)}`)
      .then(data => {
        if (!Array.isArray(data) || data.length === 0) {
          grid3.innerHTML = `
            <p data-i18n="script_search_no_results" data-i18n-vars='{"term":"${q}"}'>
              Geen resultaten gevonden voor "<strong>${q}</strong>"
            </p>
          `;
          applyTranslations();
          return;
        }

        searchProducts = data;
        renderSearchProducts();
      })
      .catch(err => {
        console.error("Fout bij ophalen producten grid3:", err);
        grid3.innerHTML = `
          <p style="color:red;" data-i18n="script_search_fetch_error">
            Fout bij ophalen producten
          </p>
        `;
        applyTranslations();
      });

    let searchProducts = [];

    function renderSearchProducts() {
      let filtered = [...searchProducts];

      // --- Sorteren ---
      if (sortSelectSearch && sortSelectSearch.value === "prijs-oplopend") {
        filtered.sort((a, b) => a.price - b.price);
      } else if (sortSelectSearch && sortSelectSearch.value === "prijs-aflopend") {
        filtered.sort((a, b) => b.price - a.price);
      } else if (sortSelectSearch && sortSelectSearch.value === "nieuw") {
        filtered.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
      } else if (sortSelectSearch && sortSelectSearch.value === "populariteit") {
        filtered.sort((a, b) => (b.popularity || 0) - (a.popularity || 0));
      }

      grid3.innerHTML = "";
      filtered.forEach(p => {
        const card = document.createElement("div");
        card.className = "product-card2";

        const inWishlist = !!p.in_wishlist;
        card.innerHTML = `
          <div class="wishlist-btn" data-id="${p.id}">
            <img src="${inWishlist ? "wishlist/added.png" : "wishlist/wishlist.png"}" class="wishlist-icon-light" alt="wishlist">
            <img src="${inWishlist ? "wishlist/added (dark mode).png" : "wishlist/wishlist (dark mode).png"}" class="wishlist-icon-dark" alt="wishlist dark">
          </div>
          <img src="${p.image}" alt="${p.name}" />
          <h3>${p.name}</h3>
          <p>€${parseFloat(p.price).toFixed(2)}</p>
        `;

        // Klikbare kaart (behalve wishlist)
        card.addEventListener("click", e => {
          if (e.target.closest(".wishlist-btn")) return;
          window.location.href = `productpagina.html?id=${p.id}`;
        });

        // Wishlist logica
        const wishlistBtn = card.querySelector(".wishlist-btn");
        wishlistBtn.addEventListener("click", e => {
          e.stopPropagation();
          const iconLight = wishlistBtn.querySelector(".wishlist-icon-light");
          const iconDark = wishlistBtn.querySelector(".wishlist-icon-dark");
          const action = iconLight.src.includes("added.png") ? "remove" : "add";
          const url = action === "add" ? "wishlist_add.php" : "wishlist_remove.php";

          const formData = new URLSearchParams();
          formData.append("product_id", p.id);

          fetch(url, { method: "POST", body: formData })
            .then(res => res.json())
            .then(response => {
              if (response.error) return console.error("Wishlist error:", response.error);
              if (action === "add") {
                iconLight.src = "wishlist/added.png";
                iconDark.src = "wishlist/added (dark mode).png";
              } else {
                iconLight.src = "wishlist/wishlist.png";
                iconDark.src = "wishlist/wishlist (dark mode).png";
              }
            })
            .catch(err => console.error("Wishlist fetch error:", err));
        });

        grid3.appendChild(card);
      });

      applyTranslations();
    }

    // --- Sorteren bij selectie ---
    if (sortSelectSearch) sortSelectSearch.addEventListener("change", renderSearchProducts);

    // --- Herlaad producten bij taalwijziging ---
    document.addEventListener("languageChanged", () => {
      fetchWithLang(`get_search_products.php?q=${encodeURIComponent(q)}`)
        .then(data => {
          searchProducts = data;
          renderSearchProducts();
        })
        .catch(err => console.error("Fout bij ophalen producten na taalwissel:", err));
    });
  }
};

document.addEventListener("DOMContentLoaded", () => {
  loadProductGrid();
  loadSearchGrid();
});

document.getElementById('password-reset-form').addEventListener('submit', function(e) {
    e.preventDefault();

    const form = this;
    const formData = new FormData(form);
    const messageDiv = document.getElementById('form-message');

    fetch('wachtwoord vergeten.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            messageDiv.style.color = 'green';
            messageDiv.textContent = data.success;
        } else if(data.error) {
            messageDiv.style.color = 'red';
            messageDiv.textContent = data.error;
        }
    })
    .catch(err => {
        messageDiv.style.color = 'red';
        messageDiv.textContent = 'Er is een fout opgetreden.';
    });
});

document.getElementById("reviewForm").addEventListener("submit", async function(e) {
  e.preventDefault();

  const formData = new FormData(this);

  try {
    const response = await fetch("reviews-mailing.php", {
      method: "POST",
      body: formData
    });

    const data = await response.json();
    const messageBox = document.getElementById("review-message");

    if (data.success) {
      messageBox.textContent = data.success;
      messageBox.style.color = "green";
    } else if (data.error) {
      messageBox.textContent = data.error;
      messageBox.style.color = "red";
    }

    messageBox.style.display = "block";
  } catch (err) {
    console.error("Error:", err);
  }
});

// ---------------- Nieuwste producten laden ----------------
async function loadNewProducts() {
  const container = document.getElementById('new-products');
  if (!container) return;

  try {
    // fetchWithLang haalt automatisch de huidige taal op
    const data = await fetchWithLang('get_new_products.php');

    if (!data.success || !data.products.length) {
      container.innerHTML = '<p>Geen nieuwe producten gevonden.</p>';
      return;
    }

    container.innerHTML = data.products
      .map(
        (p) => `
        <div class="product-card-index">
          <a href="productpagina.html?id=${p.id}">
            <img src="${p.image}" alt="${p.name}">
            <h3>${p.name}</h3>
            <p>€${p.price}</p>
          </a>
        </div>
      `
      )
      .join('');
  } catch (err) {
    console.error('Fout bij laden van nieuwe producten:', err);
    container.innerHTML = '<p>Er ging iets mis bij het laden van de producten.</p>';
  }
}

// ---------------- Populaire producten laden ----------------
async function loadPopularProducts() {
  const container = document.getElementById('popular-products');
  if (!container) return;

  try {
    const data = await fetchWithLang('get_popular_products.php?action=popular');

    if (!data.success || !data.products.length) {
      container.innerHTML = '<p>Geen populaire producten gevonden.</p>';
      return;
    }

    container.innerHTML = data.products
      .map(p => `
        <div class="product-card-index" data-product-id="${p.id}">
          <a href="productpagina.html?id=${p.id}">
            <img src="${p.image}" alt="${p.name}">
            <h3>${p.name}</h3>
            <p>€${p.price}</p>
          </a>
        </div>
      `)
      .join('');

    // GA4 tracking
    addGA4Tracking(container);
  } catch (err) {
    console.error(err);
    container.innerHTML = '<p>Fout bij laden van populaire producten.</p>';
  }
}

// ---------------- GA4 tracking toevoegen ----------------
function addGA4Tracking(container) {
  container.querySelectorAll('.product-card-index').forEach(card => {
    const productId = card.dataset.productId;
    const productName = card.querySelector('h3')?.textContent || '';
    const productPrice = parseFloat(card.querySelector('p')?.textContent.replace('€','')) || 0;

    // Event bij klikken
    card.addEventListener('click', () => {
      if (window.gtag) {
        gtag('event', 'popular_products', {
            product_id: productId,
            item_name: productName,
            price: productPrice
        });
      }
    });
  });
}

function applyTranslations() {
  // 1. Cookie uitlezen
  const cookieMatch = document.cookie.match(/(?:^|;\s*)siteLanguage=([^;]+)/);
  const lang = cookieMatch ? decodeURIComponent(cookieMatch[1]) : "be-nl"; // fallback

  // 2. Vertaling kiezen
  const dict = translations[lang] || translations["be-nl"];

  // 3. Alle elementen vervangen
  document.querySelectorAll("[data-i18n]").forEach(el => {
    const key = el.getAttribute("data-i18n");
    if (el.dataset.extraText) {
        el.textContent = `${dict[key]}, ${el.dataset.extraText}`;
    } else {
        el.textContent = dict[key];
    }
  });

  // Vertaal placeholders
  document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
    const key = el.getAttribute('data-i18n-placeholder');
    if (dict[key]) {
      el.setAttribute('placeholder', dict[key]);
    }
  });

  document.querySelectorAll('[data-i18n-href]').forEach(el => {
    const key = el.getAttribute('data-i18n-href');
    if (dict[key]) {
      el.setAttribute('href', dict[key]);
    }
  });
}

document.addEventListener("DOMContentLoaded", () => {
  applyTranslations();
});

// ============================================
// 🔥 Universele fetch-functie die taal meeneemt
// ============================================

function getCurrentLang() {
  const cookieMatch = document.cookie.match(/(?:^|;\s*)siteLanguage=([^;]+)/);
  return cookieMatch ? decodeURIComponent(cookieMatch[1]) : "be-nl";
}

async function fetchWithLang(url, options = {}) {
  const lang = getCurrentLang();
  const separator = url.includes("?") ? "&" : "?";
  const urlWithLang = `${url}${separator}lang=${encodeURIComponent(lang)}`;
  const response = await fetch(urlWithLang, options);
  if (!response.ok) throw new Error(`Fetch mislukt: ${response.status}`);
  return response.json();
}

function changeLanguage(langCode) {
  document.cookie = `siteLanguage=${langCode}; path=/; max-age=31536000`;
  applyTranslations(); // UI labels bijwerken
  document.dispatchEvent(
    new CustomEvent("languageChanged", { detail: { lang: langCode } })
  );
}