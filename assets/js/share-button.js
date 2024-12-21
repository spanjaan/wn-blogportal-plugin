document.querySelectorAll('.share-buttons button').forEach(function (button) {
    button.removeEventListener('click', onShareClick);
    button.addEventListener('click', onShareClick);
});

function onShareClick(event) {
    event.preventDefault();

    var button = event.currentTarget;
    button.disabled = true; // Disable the button to prevent multiple clicks

    var platform = button.getAttribute('data-platform');
    var postId = document.getElementById('post-id').value;

    if (!platform || !postId) {
        console.error('Platform or Post ID is missing.');
        button.disabled = false;
        return;
    }

    Snowboard.request(null, 'onShare', {
        data: { platform: platform, postId: postId },
        success: function (response) {
            // console.log('Share count updated:', response.shareCounts);

            if (response.shareCounts && response.shareCounts.total !== undefined) {
                document.getElementById('total-share-count').textContent = response.shareCounts.total;
            }

            // Open the share window only after the share count has been updated
            openShareWindow(platform, window.location.href);
        },
        error: function () {
            console.error('An error occurred while processing the share request.');
        },
        complete: function () {
            button.disabled = false; // Re-enable the button
        },
    });
}

function openShareWindow(platform, url) {
    var shareUrl;
    switch (platform) {
        case 'facebook':
            shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
            break;
        case 'twitter':
            shareUrl = `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(document.title)}`;
            break;
        case 'linkedin':
            shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(url)}`;
            break;
        case 'whatsapp':
            shareUrl = `https://wa.me/?text=${encodeURIComponent(url)}`;
            break;
        default:
            console.error('Unknown platform: ' + platform);
            return;
    }
    window.open(shareUrl, 'shareWindow', 'width=600,height=400');
}
