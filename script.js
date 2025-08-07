// Stylisso site script
console.log("Stylisso site loaded.");

document.addEventListener('DOMContentLoaded', function () {
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
  const emptyCartMsg = document.querySelector('.empty-cart');
  const subtotalDisplay = document.getElementById('cart-subtotal');

  let cart = JSON.parse(localStorage.getItem('cart')) || [];

  function saveCart() {
    localStorage.setItem('cart', JSON.stringify(cart));
  }

  function calculateSubtotal() {
    const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    subtotalDisplay.textContent = `€${total.toFixed(2)}`;
  }

  function renderCartItems() {
    if (!cartItemsContainer) return;
    cartItemsContainer.innerHTML = "";

    if (cart.length === 0) {
      emptyCartMsg.style.display = "block";
      subtotalDisplay.textContent = "€0,00";
      cartSummary.style.display = "none";
      return;
    } else {
  cartSummary.style.display = "block";
    }

    emptyCartMsg.style.display = "none";

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
            <input type="number" value="${item.quantity}" min="1" data-id="${item.id}" class="quantity-input">
          </label>
          <button class="remove-item" data-id="${item.id}">
            <img src="trash bin/trash bin.png" class="remove-icon remove-icon-light" alt="Verwijderen">
            <img src="trash bin/trash bin (dark mode).png" class="remove-icon remove-icon-dark" alt="Verwijderen">
          </button>
        </div>
      `;
      cartItemsContainer.appendChild(itemDiv);
    });

    calculateSubtotal();
  }

  function renderCartDropdown() {
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

  function updateQuantity(id, quantity) {
    const item = cart.find(p => p.id === id);
    if (item) {
      item.quantity = quantity;
      saveCart();
      renderCartItems();
      renderCartDropdown();
    }
  }

  document.addEventListener('change', function (e) {
    if (e.target.classList.contains('quantity-input')) {
      const id = parseInt(e.target.dataset.id, 10);
      const quantity = parseInt(e.target.value, 10);
      if (!isNaN(quantity) && quantity > 0) {
        updateQuantity(id, quantity);
      }
    }
  });

  document.addEventListener('click', function (e) {
    if (e.target.closest('.remove-item')) {
      const id = parseInt(e.target.closest('.remove-item').dataset.id, 10);
      cart = cart.filter(item => item.id !== id);
      saveCart();
      renderCartItems();
      renderCartDropdown();
    }
  });

  renderCartItems();
  renderCartDropdown();
});