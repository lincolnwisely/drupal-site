/* Scripts for UCLA Continuing Education - UNEX */

(function ($, Drupal, drupalSettings) {

  'use strict';
  Drupal.behaviors.zeus = {
    attach: function (context, settings) {

      //Load more button on homepage
      //Move this to a separate file? -DF
      $('#homepage-load-more', context).each(function(){
        var exclude = $(this).data('exclude');
        var view_info = {
          view_name: 'category_grid',
          view_display_id: 'embed_2',
          view_args: exclude,
          view_dom_id: 'view'
        };

        // Details of the ajax action.
        var ajax_settings = {
          submit: view_info,
          url: '/views/ajax',
          element: this,
          event: 'click touchstart',
          progress: { type: 'fullscreen' }
        }

        Drupal.ajax(ajax_settings);
      }).on('click touchstart', function(){
        $(this).remove();
      });

      var $main_nav = $('#block-mainnavigation', context);
      // Main nav states (category, sub-categories) based on screen width and event types
      $('#block-mainnavigation .menu-item--expanded', context).on('click mouseover mouseout', function(e) {
        var width = $(window).width();
        switch (e.type) {
          case 'mouseover':
          if (width > 767) {
            $main_nav.find('.open').removeClass('open');
            $(this).addClass('open');
            $('main').append('<div id="overlay"></div>');
          } else {
            return;
          }
          break;

          case 'mouseout':
          if (width > 767 ) {
            $( this ).removeClass('open');
            $('#overlay').remove();
          } else {
            return;
          }
          break;

          case 'click':
          if (width <= 767 ) {
            if ($(this).hasClass('open')) {
              return;
            }
            else {
              e.preventDefault();
              $main_nav.find('.open').removeClass('open');
              $(this).addClass('open');
            }
          } else {
            return;
          }
          break;
        }
      });


      $(window).resize(function() {
        $('.open-sesame').removeClass('open-sesame');
      });

      // Trigger Mobile nav w/ hamberder menu
      $('#hamburger').once('zeus').on('click', function() {
        $(this).toggleClass('open-sesame');
        $('#block-mainnavigation').toggleClass('open-sesame');
        $('#block-secondarynavigation').toggleClass('open-sesame');
        $('.region-menu .open-search').toggleClass('open-sesame');
        $('.menu-item--expanded').removeClass('open');
      });

      $('.secondary-menu-container span', context).on('click', function(e){
          e.stopPropagation();
          var openCategory = $('.menu-item.menu-item--expanded');
          $(openCategory).removeClass('open');
      });

      var didScroll;

      var lastScrollTop = 0;
      var delta = 5;
      var navbarHeight = $('#block-mainnavigation').outerHeight() + 130;

      var pathname = window.location.pathname;
      if (pathname == '/') {
        navbarHeight = 400;
      }


      // 55.
      // on scroll, let the interval function know the user has scrolled
      $(window).scroll(function(event){
        didScroll = true;
      });
      // run hasScrolled() and reset didScroll status
      setInterval(function() {
        if (didScroll) {
          hasScrolled();
          didScroll = false;
        }
      }, 250);

      $(window).resize(function() {
        var width = $(this).width();
        if (width <=767) {
          $('.region-header').removeClass('fade');
          $('#block-mainnavigation').removeClass('grow nav-up nav-down');
        }
      });

      function hasScrolled() {
        var width = $(window).width();

        var scrollTop = $(window).scrollTop();

        $('block-mainnavigation').addClass('nav-down');
        // Fade out low-priority header elements, increase height of main menu.

        // If homepage...
        if (pathname == '/') {
          // AND you've scrolled past the CTA block...
          if (scrollTop >= 220){
            // AND on desktop...
            if (width >= 767) {
              $('.region-header').addClass('fade');
              $('#block-ppbhomepagenavctablock').addClass('fade');
              $('#block-mainnavigation').addClass('grow');
            } else {
              // Else on mobile...
              // $('.region-menu').addClass('nav-down');

              // Don't add more than one sticky logo link.
              if ($('a.sticky').length) {
                return;
              } else {
                $('.region-menu').prepend('<a class="sticky" href="/"></a>');
              }
            }

          }
          // Else if you've scrolled back up to the top, reset everything.
          else {
            $('.region-header').removeClass('fade');
            $('#block-ppbhomepagenavctablock').removeClass('fade');
            $('#block-mainnavigation').removeClass('grow');
            $('#block-mainnavigation').removeClass('nav-up');
            $('.region-menu').removeClass('nav-down');

          }
        }

        // Else if it's not homepage (I.E. there is no Nav CTA Block in header)...
        else {
        // AND you've scrolled past the top header elements...
        if (scrollTop >= 15){
          // AND on desktop...
          if (width >= 767) {
            $('.region-header').addClass('fade');
            if (scrollTop >= 25){
              $('#block-mainnavigation').addClass('grow');
            }

          } else {
            // Else on mobile...
            // Don't add more than one sticky logo link.
            if ($('a.sticky').length) {
              return;
            } else {
              $('.region-menu').prepend('<a class="sticky" href="/"></a>');
            }
          }

        }
        // Else if you've scrolled back up to the top, reset everything.
        else {
          $('.region-header').removeClass('fade');
          $('#block-mainnavigation').removeClass('grow');
          $('#block-mainnavigation').removeClass('nav-up');
          $('.region-menu').removeClass('nav-down');
        }
      }

        // If current position > last position AND scrolled past navbar...
        // This is for the nav up/ nav down functionality.

        if (scrollTop > lastScrollTop && scrollTop > navbarHeight){
          if (width >= 767) {
            // Scroll Down
            $('#block-mainnavigation').addClass('grow');
            $('#block-mainnavigation').removeClass('nav-down').addClass('nav-up');
          }

        } else {
          // Scroll Up
          // If did not scroll past the document (possible on mac)...
            if(scrollTop + $(window).height() < $(document).height()) {
              if (width >= 767) {
                $('#block-mainnavigation').removeClass('nav-up').addClass('nav-down');
              }
            }
          }
        lastScrollTop = scrollTop;

        // MOBILE! Too many issues in earlier function
        var position = $(window).scrollTop();

        $(window).scroll(function() {
          var scrolly = $(window).scrollTop();
          if (position <= 50) {
            $('.region-menu').removeClass('nav-down');


          } else {
            if(scrolly > position) {
              $('.region-menu').removeClass('nav-down').addClass('nav-up');
            } else {
              $('.region-menu').removeClass('nav-up').addClass('nav-down');
            }
          }
          position = scrolly;
        });
      }


      // Search in header
      $('#edit-search-api-fulltext', context).click(function() {
        $(this).val('');
      });
      $('#search-overlay, #exit-search', context).click(function(){
        $('#block-searchblock').attr('hidden', 'hidden');
        //on search, overlay begins hidden. After first close, make it work again.
        $('#search-overlay').removeAttr('hidden');
      });
      $('.open-search', context).click(function(){
        $('#block-searchblock').removeAttr('hidden');
        $('#edit-search-api-fulltext').focus();
      });

      // Slider
      $('#slideshow #cover', context).click(function(e){
        $(this).toggle();
        $('.slick').toggle();
        // COUNTER
        var slideCount = $('.slick__slider .slide').length;
        if ($('.slick-slider').length > 0) {
          $('.slick-slider').slick('reinit');
        }

        $('<div class="slide-count-wrap"><h5><span>Step </span><span class="current">1</span></h5></div>').prependTo('.paragraph--type--slide .paragraph--type--zeus-text');

        $('.slick__slider').on('beforeChange', function(event, slick, currentSlide, nextSlide){
          setCurrentSlideNumber(nextSlide);
        });


        function setCurrentSlideNumber(currentSlide) {
            var $el = $('.slide-count-wrap').find('.current');
            $el.text(currentSlide + 1);
        }
      });

      //pdf paragraph
      $('.pdf-cta', context).click(function(){
        if($(window).width() > 767){
          var pid = $(this).data('pid');
          $('html, body').animate({
            scrollTop: $('#pdf-' + pid ).offset().top
          }, 500);
          return false;
        }
      });

      $('.pdf-mobile-header', context).click(function(){
        $(this).next().slideToggle();
        $(this).toggleClass('open');
      });
      $('.pdf-desktop', context).removeAttr('hidden').appendTo('.field--name-field-page-layout');


      // "You Might Also Like" mobile treatment: Print first view result after second 'section' in main article.
      // If there are fewer than two sections
      var paragraphs = $('.field--name-field-page-layout > .field__item', context).length;
      if (paragraphs > 2) {
        var $relatedContentClone = $('#block-views-block-related-content-related-content', context).clone();
        $relatedContentClone.find('.views-row:nth-child(3)').remove();
        $relatedContentClone.find('.views-row:nth-child(2)').remove();
        $relatedContentClone.attr("id", "related-content-mobile").insertAfter('.field--name-field-page-layout > .field__item:nth-child(2)');
        $('#block-views-block-related-content-related-content .views-row:nth-child(1)').addClass('hide-on-mobile');
      }
    }
  };
})(jQuery, Drupal, drupalSettings);
