// Custom JS extracted from userhome.php

// select2 init (requires select2 loaded earlier)
if (window.jQuery) {
    (function($){
        $(document).ready(function(){
            $(".js-select2").each(function(){
                $(this).select2({
                    minimumResultsForSearch: 20,
                    dropdownParent: $(this).next('.dropDownSelect2')
                });
            });

            // parallax
            try { $('.parallax100').parallax100(); } catch(e){}

            // magnific popup init
            try {
                $('.gallery-lb').each(function() {
                    $(this).magnificPopup({
                        delegate: 'a',
                        type: 'image',
                        gallery: { enabled:true },
                        mainClass: 'mfp-fade'
                    });
                });
            } catch(e){}

            // wishlist buttons (existing behavior)
            $('.js-addwish-b2').on('click', function(e){
                e.preventDefault();
            });

            $('.js-addwish-b2').each(function(){
                var nameProduct = $(this).parent().parent().find('.js-name-b2').html();
                $(this).on('click', function(){
                    swal(nameProduct, "is added to wishlist !", "success");
                    $(this).addClass('js-addedwish-b2');
                    $(this).off('click');
                });
            });

            $('.js-addwish-detail').each(function(){
                var nameProduct = $(this).parent().parent().parent().find('.js-name-detail').html();
                $(this).on('click', function(){
                    swal(nameProduct, "is added to wishlist !", "success");
                    $(this).addClass('js-addedwish-detail');
                    $(this).off('click');
                });
            });

            /* Add cart detail */
            $('.js-addcart-detail').each(function(){
                var nameProduct = $(this).parent().parent().parent().parent().find('.js-name-detail').html();
                $(this).on('click', function(){
                    swal(nameProduct, "is added to cart !", "success");
                });
            });

            // perfect-scrollbar
            $('.js-pscroll').each(function(){
                $(this).css('position','relative');
                $(this).css('overflow','hidden');
                var ps = new PerfectScrollbar(this, {
                    wheelSpeed: 1,
                    scrollingThreshold: 1000,
                    wheelPropagation: false,
                });

                $(window).on('resize', function(){
                    ps.update();
                });
            });
        });
    })(jQuery);
}

// Wishlist functions (fetch)
function addToWishlist(productId) {
    fetch('add_to_wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'product_id=' + productId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            swal("Product", "is added to wishlist !", "success");
            updateWishlistCount();
        } else {
            swal("Error", data.message || 'Có lỗi xảy ra!', "error");
        }
    })
    .catch(error => {
        console.error('Error:', error);
        swal("Error", 'Có lỗi xảy ra!', "error");
    });
}

function updateWishlistCount() {
    fetch('get_wishlist_count.php')
    .then(response => response.json())
    .then(data => {
        const countElement = document.querySelector('.icon-header-noti');
        if (countElement && data.count !== undefined) {
            countElement.setAttribute('data-notify', data.count);
        }
    })
    .catch(error => console.error('Error updating wishlist count:', error));
}

// Initialize page helpers
document.addEventListener('DOMContentLoaded', function() {
    // Update wishlist count on page load
    updateWishlistCount();
});