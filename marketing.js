(function() {
  // Functie om cookie consent te controleren
  function hasMarketingConsent() {
    const cookie = document.cookie.split('; ').find(row => row.startsWith('cookieConsent='));
    if (!cookie) return false;

    try {
      const value = JSON.parse(decodeURIComponent(cookie.split('=')[1]));
      return value.marketing === true; // marketing mag alleen als marketing=true
    } catch(e) {
      return false;
    }
  }

  // Marketing scripts alleen laden als toestemming aanwezig is
  if (hasMarketingConsent()) {
    console.log("Marketing scripts geladen");

    // Google Ads remarketing script
    const adsScript = document.createElement('script');
    adsScript.async = true;
    // Hier vul je straks je eigen Google Ads ID in (AW-XXXXXXXXX)
    adsScript.src = "https://www.googletagmanager.com/gtag/js?id=AW-XXXXXXXXX";
    document.head.appendChild(adsScript);

    // Init remarketing
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    window.gtag = gtag;

    gtag('js', new Date());
    gtag('config', 'AW-XXXXXXXXX'); // je Google Ads ID
  }
})();