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
    if (e.target.dataset.value && e.target.dataset.href) {
      // Alleen redirecten, niet de vlag aanpassen op deze pagina
      if (window.location.pathname.endsWith(e.target.dataset.href)) {
        // Zelfde pagina, sluit alleen dropdown
        select.classList.remove('open');
      } else {
        // Ga naar de gekozen pagina
        window.location.href = e.target.dataset.href;
      }
    }
  });

  // Sluit dropdown als je buiten klikt
  document.addEventListener('click', function (e) {
    if (!select.contains(e.target)) {
      select.classList.remove('open');
    }
  });
});
