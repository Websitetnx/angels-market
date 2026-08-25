/**
 * Angel's Beauty Co. - Main JavaScript
 */
$(document).ready(function() {
    const csrfToken = $('meta[name="csrf-token"]').attr('content') || '';

    // Toast notification
    window.showToast = function(message, type = 'success') {
        const id = 'toast-' + Date.now();
        const icons = { success:'bi-check-circle-fill', error:'bi-x-circle-fill', warning:'bi-exclamation-triangle-fill', info:'bi-info-circle-fill' };
        const colors = { success:'#00bfa5', error:'#ee4d2d', warning:'#ffc107', info:'#2196f3' };
        const html = `<div id="${id}" class="toast align-items-center border-0 show" role="alert" style="border-left:4px solid ${colors[type]}">
            <div class="d-flex"><div class="toast-body d-flex align-items-center gap-2">
            <i class="bi ${icons[type]}" style="color:${colors[type]};font-size:20px"></i>
            <span>${message}</span></div>
            <button type="button" class="btn-close me-2 m-auto" onclick="$('#${id}').fadeOut(300,function(){$(this).remove()})"></button>
            </div></div>`;
        if (!$('.toast-container').length) {
            $('body').append('<div class="toast-container"></div>');
        }
        $('.toast-container').append(html);
        setTimeout(() => { $('#' + id).fadeOut(300, function() { $(this).remove(); }); }, 3500);
    };

    // Add to cart via AJAX
    $(document).on('click', '.btn-add-to-cart', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const btn = $(this);
        const originalHtml = btn.html();
        const productId = parseInt(btn.data('product-id'), 10);
        const sizeRequired = $('.size-options .size-btn').length > 0;
        const colorRequired = $('.color-options .color-btn').length > 0;
        const size = btn.data('size') || $('.size-btn.active').data('size') || '';
        const color = btn.data('color') || $('.color-btn.active').data('color') || '';
        const qty = Math.max(1, parseInt($('#qty-input').val(), 10) || 1);

        if (!productId) {
            showToast('Invalid product selected.', 'error');
            return;
        }
        if (sizeRequired && !size) {
            showToast('Please select a size or product option.', 'warning');
            return;
        }
        if (colorRequired && !color) {
            showToast('Please select a color.', 'warning');
            return;
        }

        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.ajax({
            url: baseUrl + 'client/ajax/cart_action.php',
            method: 'POST',
            data: {
                action: 'add',
                product_id: productId,
                quantity: qty,
                size: size,
                color: color,
                csrf_token: csrfToken
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    showToast(res.message, 'success');
                    if (res.cart_count !== undefined) {
                        $('.cart-badge').text(res.cart_count);
                    }
                    const redirectUrl = btn.data('redirect');
                    if (redirectUrl) {
                        window.location.href = redirectUrl;
                    }
                } else {
                    showToast(res.message, 'error');
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Something went wrong.';
                showToast(message, 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html(originalHtml);
            }
        });
    });

    // Cart quantity update
    $(document).on('click', '.cart-qty-btn', function() {
        const cartId = $(this).data('cart-id');
        const action = $(this).data('action');
        $.ajax({
            url: baseUrl + 'client/ajax/cart_action.php',
            method: 'POST',
            data: { action: action, cart_id: cartId, csrf_token: csrfToken },
            dataType: 'json',
            success: function(res) {
                if (res.success) { location.reload(); }
                else { showToast(res.message, 'error'); }
            }
        });
    });

    // Remove cart item
    $(document).on('click', '.cart-remove-btn', function() {
        const cartId = $(this).data('cart-id');
        if (confirm('Remove this item from cart?')) {
            $.ajax({
                url: baseUrl + 'client/ajax/cart_action.php',
                method: 'POST',
                data: { action: 'remove', cart_id: cartId, csrf_token: csrfToken },
                dataType: 'json',
                success: function(res) {
                    if (res.success) { location.reload(); }
                    else { showToast(res.message, 'error'); }
                }
            });
        }
    });

    // Product detail - size selection
    $(document).on('click', '.size-btn', function() {
        $('.size-btn').removeClass('active');
        $(this).addClass('active');
    });

    // Product detail - color selection
    $(document).on('click', '.color-btn', function() {
        $('.color-btn').removeClass('active');
        $(this).addClass('active');
    });

    // Product detail - quantity
    $(document).on('click', '.qty-minus', function() {
        let input = $('#qty-input');
        let val = parseInt(input.val());
        if (val > 1) input.val(val - 1);
    });
    $(document).on('click', '.qty-plus', function() {
        let input = $('#qty-input');
        let val = parseInt(input.val());
        let max = parseInt(input.data('max')) || 999;
        if (val < max) input.val(val + 1);
    });

    // Gallery thumbnail click
    $(document).on('click', '.gallery-thumbs img', function() {
        const src = $(this).data('full') || $(this).attr('src');
        $('.gallery-main img').attr('src', src);
        $('.gallery-thumbs img').removeClass('active');
        $(this).addClass('active');
    });

    // Payment method selection
    $(document).on('click', '.payment-option', function() {
        $('.payment-option').removeClass('selected');
        $(this).addClass('selected');
        $(this).find('input[type=radio]').prop('checked', true);
    });

    // Admin sidebar toggle on mobile
    $(document).on('click', '#sidebarToggle', function() {
        $('.admin-sidebar').toggleClass('show');
    });

    // Search form
    $('#searchForm').on('submit', function(e) {
        const q = $(this).find('input[name=q]').val().trim();
        if (!q) { e.preventDefault(); showToast('Please enter a search term', 'warning'); }
    });

    // Confirm delete
    $(document).on('click', '.btn-delete-confirm', function(e) {
        if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
            e.preventDefault();
        }
    });

    // Product card click - navigate to detail
    $(document).on('click', '.product-card', function(e) {
        if ($(e.target).closest('.card-actions').length) return;
        const url = $(this).data('url');
        if (url) window.location.href = url;
    });

    // Image preview on admin product form
    $(document).on('change', '#productImages', function() {
        const preview = $('#imagePreview');
        preview.empty();
        const files = this.files;
        for (let i = 0; i < files.length; i++) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.append(`<img src="${e.target.result}" class="rounded me-2 mb-2" style="width:80px;height:80px;object-fit:cover">`);
            };
            reader.readAsDataURL(files[i]);
        }
    });
});
