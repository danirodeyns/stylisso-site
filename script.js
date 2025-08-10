console.log("Stylisso site loaded.");

document.addEventListener('DOMContentLoaded', function () {
  // --- Header inladen via fetch ---
  fetch('header.html')
    .then(response => {
      if (!response.ok) throw new Error('Header kon niet geladen worden');
      return response.text();
    })
    .then(html => {
      // Plaats header in de juiste container in je index.html
      // Zorg dat je index.html een div heeft met id="header-placeholder"
      const headerContainer = document.getElementById('header-placeholder');
      if (headerContainer) {
        headerContainer.innerHTML = html;
      } else {
        console.warn('Geen container gevonden voor header');
      }
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

  // --- Taalkeuze vlag dropdown ---
  const select = document.querySelector('.custom-lang-select');
  const flag = document.getElementById('selectedFlag');
  const dropdown = document.getElementById('flagDropdown');

  select.addEventListener('click', function () {
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

  // --- Winkelwagen beheer ---
  const cartDropdown = document.getElementById('cartDropdown');
  const cartItemsContainer = document.getElementById('cart-items');
  const subtotalDisplay = document.getElementById('cart-subtotal');
  const cartSummary = document.getElementById('cart-summary');

  // Haal cart op van server
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

  // Update hoeveelheid via AJAX naar server
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

  // Verwijder item via AJAX naar server
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

  // Eventlisteners
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

  // Initialiseer cart weergave
  fetchCart();

  // Logout functie
function handleLogout() {
  fetch('logout.php', { method: 'POST' })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const currentPage = window.location.pathname.split('/').pop();
        const redirectPages = ['profielinstellingen.php', 'andere_pagina.php'];

        if (redirectPages.includes(currentPage)) {
          window.location.href = 'login_registreren.html';
        } else {
          window.location.reload();
        }
      }
    });
}

// Logout knop event listener (zorg dat je header logout knop deze ID heeft!)
const logoutBtn = document.getElementById('logoutBtn');
if (logoutBtn) {
  logoutBtn.addEventListener('click', function(e) {
    e.preventDefault();
    handleLogout();
  });
}
});