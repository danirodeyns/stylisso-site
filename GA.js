(function() {
  // Functie om cookie consent te controleren
  function hasConsent() {
    const cookie = document.cookie.split('; ').find(row => row.startsWith('cookieConsent='));
    if (!cookie) return false;

    try {
        const value = JSON.parse(decodeURIComponent(cookie.split('=')[1]));
        return value.analytics === true; // GA mag alleen als analytics=true
    } catch(e) {
        return false;
    }
  }

  // GA alleen laden als toestemming aanwezig is
  if (hasConsent()) {
    // Voeg GA-script dynamisch toe
    const gaScript = document.createElement('script');
    gaScript.async = true;
    gaScript.src = "https://www.googletagmanager.com/gtag/js?id=G-33YQ0QLLQS";
    document.head.appendChild(gaScript);

    // Init GA
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    window.gtag = gtag; // zodat andere scripts gtag() kunnen gebruiken
    gtag('js', new Date());
    gtag('config', 'G-33YQ0QLLQS');
  }
})();