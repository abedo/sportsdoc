// Miejsca jako karty: strzałki, klawiatura lewo/prawo, przesunięcie palcem.
// Aktualne miejsce trzymamy w adresie (?place=ID), żeby odświeżenie i powrót z innych ekranów wracały na tę samą kartę.
(function () {
    var el = document.querySelector('.places-swiper');
    if (!el || typeof Swiper === 'undefined') { return; }

    var swiper = new Swiper(el, {
        initialSlide: parseInt(el.dataset.initial || '0', 10),
        keyboard: { enabled: true },
        navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
        pagination: { el: '.swiper-pagination', clickable: true },
        spaceBetween: 8,
        autoHeight: true
    });

    function remember() {
        var slide = swiper.slides[swiper.activeIndex];
        if (!slide || !slide.dataset.placeId) { return; }
        var url = new URL(window.location.href);
        url.searchParams.set('place', slide.dataset.placeId);
        window.history.replaceState(null, '', url);
    }
    swiper.on('slideChange', remember);
    remember();
})();
