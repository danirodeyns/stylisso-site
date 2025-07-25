// Hier kun je later dynamisch producten laden via de BigBuy API
console.log("Stylisso site loaded.");

document.addEventListener('DOMContentLoaded', function () {
  const select = document.querySelector('.custom-lang-select');
  const flag = document.getElementById('selectedFlag');
  const dropdown = document.getElementById('flagDropdown');

  select.addEventListener('click', function () {
    select.classList.toggle('open');
  });

  dropdown.addEventListener('click', function (e) {
    const item = e.target.closest('div[data-value]');
    if (item) {
      // Controleer of de gekozen pagina verschilt van de huidige
      const targetHref = item.dataset.href;
      const currentPage = window.location.pathname.split('/').pop();
      if (targetHref && targetHref !== currentPage) {
        // Ga naar de gekozen pagina, vlag wordt daar automatisch aangepast
        window.location.href = targetHref;
      } else {
        // Sluit alleen de dropdown, vlag blijft hetzelfde
        select.classList.remove('open');
      }
    }
  });

  document.addEventListener('click', function (e) {
    if (!select.contains(e.target)) {
      select.classList.remove('open');
    }
  });
});
  // Dummy-items (vervang later door echte uit localStorage of API)
  let cart = [
    { id: 1, name: "T-shirt", price: "€29,99" },
    { id: 2, name: "Sneakers", price: "€89,00" }
  ];

  const cartDropdown = document.getElementById('cartDropdown');
  const cartItemsList = document.querySelector('.cart-items');
  const emptyCartMsg = document.querySelector('.empty-cart');

  function renderCart() {
    cartItemsList.innerHTML = "";
    if (cart.length === 0) {
      emptyCartMsg.style.display = "block";
    } else {
      emptyCartMsg.style.display = "none";
      cart.forEach(item => {
        const li = document.createElement('li');
        li.innerHTML = `
          <span class="item-text">${item.name} <span class="item-price">€${item.price}</span>
          </span>
          <button class="remove-item" data-id="${item.id}">
            <img src="trash bin/trash bin.png" class="remove-icon remove-icon-light" alt="Verwijderen">
            <img src="trash bin/trash bin (dark mode).png" class="remove-icon remove-icon-dark" alt="Verwijderen">
          </button>
        `;
        cartItemsList.appendChild(li);
      });
    }
  }

  // Verwijder item
  cartItemsList.addEventListener('click', function (e) {
    if (e.target.classList.contains('remove-item')) {
      const id = parseInt(e.target.dataset.id, 10);
      cart = cart.filter(item => item.id !== id);
      renderCart();
    }
  });

  renderCart(); // initieel tonen