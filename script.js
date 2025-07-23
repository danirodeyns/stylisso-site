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
    if (e.target.dataset.value) {
      flag.textContent = e.target.textContent.split(' ')[0]; // Alleen vlag tonen
      flag.setAttribute('data-value', e.target.dataset.value);
      select.classList.remove('open');
    }
  });

  // Sluit dropdown als je buiten klikt
  document.addEventListener('click', function (e) {
    if (!select.contains(e.target)) {
      select.classList.remove('open');
    }
  });
});
