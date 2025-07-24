// Hier kun je later dynamisch producten laden via de BigBuy API
console.log("Stylisso site loaded.");

document.addEventListener('DOMContentLoaded', function () {
  const select = document.querySelector('.custom-lang-select');
  const flag = document.getElementById('selectedFlag');
  const dropdown = document.getElementById('flagDropdown');

  select.addEventListener('click', function (e) {
    select.classList.toggle('open');
  });

  dropdown.addEventListener('click', function (e) {
    if (e.target.closest('div[data-value]')) {
      const item = e.target.closest('div[data-value]');
      const img = item.querySelector('img').cloneNode();
      flag.innerHTML = '';
      flag.appendChild(img);
      flag.setAttribute('data-value', item.dataset.value);
      select.classList.remove('open');
      if (item.dataset.href) {
        window.location.href = item.dataset.href;
      }
    }
  });

  document.addEventListener('click', function (e) {
    if (!select.contains(e.target)) {
      select.classList.remove('open');
    }
  });
});