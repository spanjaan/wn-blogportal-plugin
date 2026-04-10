(function (window, document) {
    'use strict';

    /**
     * Config
     */
    var POST_ID = window.POST_ID || null;

    var SHARE_URLS = {
        facebook: function (url) {
            return 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);
        },
        twitter: function (url) {
            return 'https://twitter.com/intent/tweet?url=' + encodeURIComponent(url) +
                   '&text=' + encodeURIComponent(document.title);
        },
        linkedin: function (url) {
            return 'https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(url);
        },
        whatsapp: function (url) {
            return 'https://wa.me/?text=' + encodeURIComponent(url);
        }
    };

    /**
     * Open share popup
     */
    function openShareWindow(platform, url) {
        if (!SHARE_URLS[platform]) return;

        window.open(
            SHARE_URLS[platform](url),
            'shareWindow',
            'width=600,height=400,noopener,noreferrer'
        );
    }

    /**
     * Update share count UI
     */
    function updateShareCount(response) {
        if (!response || !response.shareCounts) return;

        var el = document.getElementById('total-share-count');
        if (el && response.shareCounts.total !== undefined) {
            el.textContent = response.shareCounts.total;
        }
    }

    /**
     * Share button click
     */
    function handleShareClick(e) {
        var button = e.currentTarget;
        var platform = button.getAttribute('data-platform');
        var shareUrl = window.location.href;

        if (!platform) return;

        button.disabled = true;

        Snowboard.request(null, 'onShare', {
            data: {
                platform: platform,
                postId: POST_ID
            },

            success: function (response) {
                updateShareCount(response);
                openShareWindow(platform, shareUrl);
            },

            error: function () {
                console.error('Share failed:', platform);
            },

            complete: function () {
                button.disabled = false;
            }
        });
    }

    /**
     * Show "Copied!" text on button
     */
    function showCopiedText(button) {
        var originalText = button.innerHTML;
        var copiedText = '<span class="relative z-10 flex items-center gap-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg><span class="inline-block">Copied!</span></span>';
        
        button.innerHTML = copiedText;
        
        setTimeout(function() {
            button.innerHTML = originalText;
        }, 2000);
    }

    /**
     * Copy link
     */
    function handleCopyLink(e) {
        var button = e.currentTarget;
        var url = window.location.href;

        if (!navigator.clipboard) {
            fallbackCopy(url, button);
            return;
        }

        navigator.clipboard.writeText(url).then(function () {
            showCopiedText(button);

            Snowboard.request(null, 'onShare', {
                data: {
                    platform: 'copy',
                    postId: POST_ID
                },
                success: updateShareCount
            });
        });
    }

    /**
     * Fallback copy (old browsers)
     */
    function fallbackCopy(text, button) {
        var input = document.createElement('input');
        input.value = text;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);

        showCopiedText(button);
    }

    /**
     * Init events
     */
    function bindEvents() {
        var buttons = document.querySelectorAll('#share-buttons .share-btn');

        for (var i = 0; i < buttons.length; i++) {
            buttons[i].addEventListener('click', handleShareClick);
        }

        var copyBtn = document.getElementById('copy-link-btn');
        if (copyBtn) {
            copyBtn.addEventListener('click', handleCopyLink);
        }
    }

    /**
     * DOM Ready
     */
    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    /**
     * Init
     */
    ready(function () {
        bindEvents();
    });

})(window, document);