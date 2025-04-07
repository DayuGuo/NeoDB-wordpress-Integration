jQuery(document).ready(function($) {
    let isLoading = false;

    function loadContent(category, type, page, append = false) {
        if (isLoading) return;
        isLoading = true;

        // 如果是新分类或新类型，重置页面
        if (!append) {
            $('.neodb-grid').attr('data-page', '1').attr('data-type', type);
            $('.load-more-button, .no-more-items').hide();
        }

        // 显示加载状态
        if (!append) {
            $('.neodb-grid').html('<p>加载中...</p>');
        } else {
            $('.load-more-button').addClass('loading').text('加载中...');
        }

        // 发送 Ajax 请求
        $.ajax({
            url: neodb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'neodb_get_category',
                category: category,
                type: type,
                page: page,
                nonce: neodb_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // 更新内容
                    if (append) {
                        if (response.data.has_data) {
                            $('.neodb-grid').append(response.data.html);
                        }
                    } else {
                        if (response.data.has_data) {
                            $('.neodb-grid').html(response.data.html);
                        } else {
                            $('.neodb-grid').empty();
                        }
                    }

                    // 更新加载更多按钮状态
                    if (response.data.has_more) {
                        $('.load-more-button').show().removeClass('loading').text('加载更多');
                        $('.no-more-items').hide();
                    } else {
                        $('.load-more-button').hide();
                        $('.no-more-items').show();
                    }

                    // 更新页码
                    if (append && response.data.has_data) {
                        $('.neodb-grid').attr('data-page', page);
                    }
                } else {
                    if (!append) {
                        $('.neodb-grid').empty();
                    }
                    $('.load-more-button').removeClass('loading').text('加载更多');
                }
                isLoading = false;
            },
            error: function() {
                if (!append) {
                    $('.neodb-grid').empty();
                }
                $('.load-more-button').removeClass('loading').text('加载更多');
                isLoading = false;
            }
        });
    }

    // 分类切换事件
    $('.neodb-nav-item').on('click', function() {
        const $this = $(this);
        const category = $this.data('category');
        const type = $('.neodb-type-item.active').data('type');
        
        // 更新活动状态
        $('.neodb-nav-item').removeClass('active');
        $this.addClass('active');

        // 加载新分类的内容
        loadContent(category, type, 1, false);
    });

    // 类型切换事件
    $('.neodb-type-item').on('click', function() {
        const $this = $(this);
        const type = $this.data('type');
        const category = $('.neodb-nav-item.active').data('category');
        
        // 更新活动状态
        $('.neodb-type-item').removeClass('active');
        $this.addClass('active');

        // 加载新类型的内容
        loadContent(category, type, 1, false);
    });

    // 加载更多按钮点击事件
    $('.load-more-button').on('click', function() {
        if ($(this).hasClass('loading')) return;
        
        const $grid = $('.neodb-grid');
        const category = $('.neodb-nav-item.active').data('category');
        const type = $('.neodb-type-item.active').data('type');
        const currentPage = parseInt($grid.attr('data-page')) || 1;
        const nextPage = currentPage + 1;

        loadContent(category, type, nextPage, true);
    });
});
