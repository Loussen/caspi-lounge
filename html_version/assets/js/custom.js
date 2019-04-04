/**
 * Created by Eyvazoff on 24/10/2017.
 */

$('#home-slider').lightSlider({
    gallery: false,
    item: 1,
    loop: true,
    slideMargin: 0,
    enableDrag: true,
    pager: false,
    currentPagerPosition: 'left'
});


$('.events-slider').slick({
    infinite: true,
    slidesToShow: 4,
    slidesToScroll: 2
});


$('.first-slider').slick({
    infinite: true,
    slidesToShow: 1,
    prevArrow: '<button type="button" class="prev-s bnt-slide">Previous</button>',
    nextArrow: '<button type="button" class="next-s bnt-slide">Next</button>'
});


$('.menu-slider').slick({
    infinite: true,
    slidesToShow: 1,
    prevArrow: '<button type="button" class="prev-s2 bnt-slide">Previous</button>',
    nextArrow: '<button type="button" class="next-s2 bnt-slide">Next</button>'
});

$('.gallery-slider').slick({
    infinite: true,
    slidesToShow: 4,
    slidesToScroll: 2,
    prevArrow: '<button type="button" class="prev-s2 r2 bnt-slide">Previous</button>',
    nextArrow: '<button type="button" class="next-s2 r2 bnt-slide">Next</button>'
});

$('.c-menu-list').slimScroll({
    height: '430px'
});
$('.order-list').slimScroll({
    height: '250px'
});
