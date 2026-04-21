var swiper = new Swiper(".trending-content", {
  slidesPerView: 1,
  spaceBetween: 10,
  pagination: {
    el: ".swiper-pagination",
    clickable: true,
  },
  breakpoints: {
    640: { 
        slidesPerView: 2,
        spaceBetween: 10 },
    768: { 
        slidesPerView: 3,
        spaceBetween: 15 },
    1024: { 
        slidesPerView: 5,
        spaceBetween: 20 },
    
  },
});
