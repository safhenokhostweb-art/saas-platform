(function($) {

  "use strict";

  var initPreloader = function() {
    $(document).ready(function($) {
    var Body = $('body');
        Body.addClass('preloader-site');
    });
    $(window).load(function() {
        $('.preloader-wrapper').fadeOut();
        $('body').removeClass('preloader-site');
    });
  }

  // init Chocolat light box
	var initChocolat = function() {
		Chocolat(document.querySelectorAll('.image-link'), {
		  imageSize: 'contain',
		  loop: true,
		})
	}

  var initSwiper = function(root) {
    root = root && root.querySelectorAll ? root : document;

    var initOne = function(el, options) {
      if (el.swiper && typeof el.swiper.destroy === 'function') {
        el.swiper.destroy(true, true);
      }
      return new Swiper(el, options);
    };

    root.querySelectorAll(".main-swiper").forEach(function(el) {
      initOne(el, {
      speed: 500,
      pagination: {
        el: el.querySelector(".swiper-pagination"),
        clickable: true,
      },
      });
    });

    root.querySelectorAll(".category-carousel").forEach(function(el) {
      var section = el.closest('section') || document;
      initOne(el, {
      slidesPerView: 6,
      spaceBetween: 30,
      speed: 500,
      navigation: {
        nextEl: section.querySelector(".category-carousel-next"),
        prevEl: section.querySelector(".category-carousel-prev"),
      },
      breakpoints: {
        0: {
          slidesPerView: 2,
        },
        768: {
          slidesPerView: 3,
        },
        991: {
          slidesPerView: 4,
        },
        1500: {
          slidesPerView: 6,
        },
      },
      });
    });

    root.querySelectorAll(".brand-carousel").forEach(function(el) {
      var section = el.closest('section') || document;
      initOne(el, {
      slidesPerView: 4,
      spaceBetween: 30,
      speed: 500,
      navigation: {
        nextEl: section.querySelector(".brand-carousel-next"),
        prevEl: section.querySelector(".brand-carousel-prev"),
      },
      breakpoints: {
        0: {
          slidesPerView: 2,
        },
        768: {
          slidesPerView: 2,
        },
        991: {
          slidesPerView: 3,
        },
        1500: {
          slidesPerView: 4,
        },
      },
      });
    });

    root.querySelectorAll(".products-carousel").forEach(function(el) {
      var section = el.closest('section') || document;
      initOne(el, {
      slidesPerView: 5,
      spaceBetween: 30,
      speed: 500,
      navigation: {
        nextEl: section.querySelector(".products-carousel-next"),
        prevEl: section.querySelector(".products-carousel-prev"),
      },
      breakpoints: {
        0: {
          slidesPerView: 1,
        },
        768: {
          slidesPerView: 3,
        },
        991: {
          slidesPerView: 4,
        },
        1500: {
          slidesPerView: 6,
        },
      },
      });
    });
  }

  var initProductQty = function(){

    $('.product-qty').each(function(){

      var $el_product = $(this);
      var quantity = 0;

      $el_product.find('.quantity-right-plus').click(function(e){
          e.preventDefault();
          var quantity = parseInt($el_product.find('#quantity').val());
          $el_product.find('#quantity').val(quantity + 1);
      });

      $el_product.find('.quantity-left-minus').click(function(e){
          e.preventDefault();
          var quantity = parseInt($el_product.find('#quantity').val());
          if(quantity>0){
            $el_product.find('#quantity').val(quantity - 1);
          }
      });

    });

  }

  // init jarallax parallax
  var initJarallax = function() {
    jarallax(document.querySelectorAll(".jarallax"));

    jarallax(document.querySelectorAll(".jarallax-keep-img"), {
      keepImg: true,
    });
  }

  // document ready
  $(document).ready(function() {
    
    initPreloader();
    initSwiper();
    initProductQty();
    initJarallax();
    initChocolat();

  }); // End of a document

  document.addEventListener('theme:content-updated', function(event) {
    initSwiper(event.detail && event.detail.root ? event.detail.root : document);
    initProductQty();
  });

})(jQuery);
