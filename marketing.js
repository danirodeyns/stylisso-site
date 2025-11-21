(function() {
  // ===========================
  // Cookie consent check
  // ===========================
  function hasMarketingConsent() {
    const cookie = document.cookie.split('; ').find(row => row.startsWith('cookieConsent='));
    if (!cookie) return false;

    try {
      const value = JSON.parse(decodeURIComponent(cookie.split('=')[1]));
      return value.marketing === true;
    } catch(e) {
      return false;
    }
  }

  // ===========================
  // Event tracking functie
  // ===========================
  function trackEvent(eventName, value = 0, currency = 'EUR', contentId = null) {
    if (!hasMarketingConsent()) return;

    // Dubbele events voorkomen per sessie
    const key = 'event_' + eventName + (contentId ? '_' + contentId : '');
    if (sessionStorage.getItem(key)) return;
    sessionStorage.setItem(key, '1');

    // Google Ads
    if (window.gtag) {
      gtag('event', eventName, {
        transaction_id: '' + new Date().getTime(),
        value: value,
        currency: currency,
        items: contentId ? [{id: contentId}] : undefined
      });
    }

    // Meta Ads (Facebook Pixel)
    if (window.fbq) {
      fbq('track', eventName, { value: value, currency: currency });
    }

    // TikTok Ads
    if (window.ttq) {
      ttq.track(eventName === 'Purchase' ? 'CompletePayment' : eventName, { value: value, currency: currency });
    }
  }

  // ===========================
  // Marketing scripts laden
  // ===========================
  if (hasMarketingConsent()) {
    console.log("Marketing scripts geladen");

    // Google Ads
    const googleScript = document.createElement('script');
    googleScript.async = true;
    googleScript.src = "https://www.googletagmanager.com/gtag/js?id=AW-XXXXXXXXX";
    document.head.appendChild(googleScript);

    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    window.gtag = gtag;
    gtag('js', new Date());
    gtag('config', 'AW-XXXXXXXXX');

    // Meta Ads (Facebook Pixel)
    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', 'XXXXXXXXXXXXXXX');
    fbq('track', 'PageView');

    // TikTok Ads
    !function (w, d, t) {
      w.TiktokAnalyticsObject = t;
      var ttq = w[t] = w[t] || [];
      ttq.methods = ["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie"];
      ttq.setAndDefer = function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}; 
      for(var i=0;i<ttq.methods.length;i++){ttq.setAndDefer(ttq,ttq.methods[i])}
      ttq.instance = function(t){var e=ttq._i[t]||[];return e}; 
      ttq.load = function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";
        ttq._i=ttq._i||{};ttq._i[e]=[];ttq._i[e]._u=i;ttq._t=ttq._t||{};ttq._t[e]=+new Date;ttq._o=ttq._o||{};ttq._o[e]=n||{};
        var o=document.createElement("script");o.type="text/javascript";o.async=!0;o.src=i;
        var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a);
      };
      ttq.load('XXXXXXXXXXXXXXX');
      ttq.page();
    }(window, document, 'ttq');

    // Page-specifieke events
    const pathname = window.location.pathname;
    if (pathname.includes('productpagina.html')) {
      const urlParams = new URLSearchParams(window.location.search);
      const productId = urlParams.get('id');
      trackEvent('ViewContent', 0, 'EUR', productId);
    }
  }

  // ===========================
  // Globale functies voor script.js
  // ===========================
  window.trackAddToCart = function(value = 0, currency = 'EUR', productId = null) {
    trackEvent('AddToCart', value, currency, productId);
  };

  window.trackPurchaseOrder = function(orderData) {
    if (!orderData || !orderData.id) return;

    // Check of event al is gestuurd (dubbel voorkomen bij refresh)
    if (sessionStorage.getItem('event_Purchase_' + orderData.id)) return;

    const value = parseFloat(orderData.total_price) || 0;
    trackEvent('Purchase', value, 'EUR', orderData.id);
  };

})();