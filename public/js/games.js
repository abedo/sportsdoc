// Gry miejsca jako slider: najnowsza pierwsza, strzałka w prawo / przesunięcie w lewo / klawisz → pokazuje starsze gry.
// Adres strony zmienia się na /game/ID aktualnego slajdu, żeby odświeżenie wracało na tę samą grę.
(function () {
    var el = document.querySelector('.games-swiper');
    if (!el || typeof Swiper === 'undefined') { return; }

    var swiper = new Swiper(el, {
        initialSlide: parseInt(el.dataset.initial || '0', 10),
        keyboard: { enabled: true },
        navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
        // Gier może być bardzo dużo: zamiast kropek pokazujemy "3 / 27".
        pagination: { el: '.swiper-pagination', type: 'fraction' },
        spaceBetween: 16,
        autoHeight: true,
        // Nie przechwytujemy przesunięcia na polach tekstowych i przyciskach.
        noSwipingSelector: 'input, textarea, select',
        threshold: 10
    });

    function remember() {
        var slide = swiper.slides[swiper.activeIndex];
        if (!slide || !slide.dataset.url) { return; }
        window.history.replaceState(null, '', slide.dataset.url);
        var date = slide.querySelector('h1');
        if (date) { document.title = date.textContent.trim() + ' | Sportsdoc'; }
    }
    window.addEventListener('load', function () { swiper.update(); });
    swiper.on('slideChange', remember);
    remember();
})();
