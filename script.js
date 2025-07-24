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